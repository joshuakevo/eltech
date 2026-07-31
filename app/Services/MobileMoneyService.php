<?php

namespace App\Services;

use App\Models\Client;
use App\Models\MobileMoneyTransaction;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MobileMoneyService
{
    public function __construct(
        protected MarzPayService $marzPay,
        protected SavingsService $savingsService,
    ) {}

    /**
     * Client-initiated deposit: charges the client's own mobile money immediately.
     * The client approves it themselves via their phone's PIN prompt -- no staff step.
     */
    public function initiateDeposit(Client $client, SavingsAccount $account, float $amount, string $phone): MobileMoneyTransaction
    {
        $phone = $this->normalizePhoneOrFail($phone);
        $reference = $this->marzPay->generateReference();

        $mm = MobileMoneyTransaction::create([
            'client_id'          => $client->id,
            'savings_account_id' => $account->id,
            'type'               => 'deposit',
            'amount'             => $amount,
            'phone_number'       => $phone,
            'reference'          => $reference,
            'status'             => 'pending',
            'description'        => "Mobile money deposit - {$account->account_number}",
        ]);

        $result = $this->marzPay->collect(
            $phone,
            $amount,
            $reference,
            "Deposit to {$account->account_number}",
            route('marzpay.webhook')
        );

        if ($result['success']) {
            $mm->update([
                'provider_reference' => $result['provider_reference'],
                'status'             => 'processing',
            ]);
        } else {
            $mm->update(['status' => 'failed', 'failure_reason' => $result['message']]);
        }

        return $mm->fresh();
    }

    /**
     * Client-initiated withdrawal request: queues for staff approval. No MarzPay call and
     * no ledger debit happens until a staff member explicitly approves it.
     */
    public function requestWithdrawal(Client $client, SavingsAccount $account, float $amount, string $phone): MobileMoneyTransaction
    {
        $phone = $this->normalizePhoneOrFail($phone);
        $reference = $this->marzPay->generateReference();

        return MobileMoneyTransaction::create([
            'client_id'          => $client->id,
            'savings_account_id' => $account->id,
            'type'               => 'withdrawal',
            'amount'             => $amount,
            'phone_number'       => $phone,
            'reference'          => $reference,
            'status'             => 'pending_approval',
            'description'        => "Mobile money withdrawal - {$account->account_number}",
        ]);
    }

    /**
     * Staff approves a pending withdrawal: debits the member's account now (same as any
     * other withdrawal) and only then calls MarzPay to actually pay out. If the payout call
     * itself fails outright, the debit is reversed immediately in the same transaction.
     */
    public function approveWithdrawal(MobileMoneyTransaction $mm, User $approver): array
    {
        if ($mm->status !== 'pending_approval') {
            return ['success' => false, 'message' => 'This request is not pending approval.'];
        }

        $account = $mm->savingsAccount;
        if (!$account->canWithdraw($mm->amount)) {
            return ['success' => false, 'message' => 'Insufficient balance to complete this withdrawal.'];
        }

        return DB::transaction(function () use ($mm, $approver, $account) {
            $txn = $this->savingsService->withdraw(
                $account,
                $mm->amount,
                today()->toDateString(),
                $mm->description,
                $mm->reference
            );

            $mm->update([
                'status'                 => 'processing',
                'approved_by'            => $approver->id,
                'approved_at'            => now(),
                'savings_transaction_id' => $txn->id,
            ]);

            $result = $this->marzPay->disburse($mm->phone_number, $mm->amount, $mm->reference, $mm->description);

            if ($result['success']) {
                $mm->update(['provider_reference' => $result['provider_reference']]);
                return ['success' => true, 'message' => 'Withdrawal approved and payout initiated.'];
            }

            $this->reverseFailedWithdrawal($mm->fresh());
            $mm->update(['status' => 'failed', 'failure_reason' => $result['message']]);

            return ['success' => false, 'message' => 'Payout failed and the debit was reversed: ' . $result['message']];
        });
    }

    public function rejectWithdrawal(MobileMoneyTransaction $mm): array
    {
        if ($mm->status !== 'pending_approval') {
            return ['success' => false, 'message' => 'This request is not pending approval.'];
        }

        $mm->update(['status' => 'cancelled']);

        return ['success' => true, 'message' => 'Withdrawal request rejected. No money was moved.'];
    }

    /**
     * Re-verify a transaction's TRUE status directly from MarzPay -- called from the webhook
     * receiver (which only ever uses the payload to know WHICH transaction to check, never to
     * decide the outcome) and from manual/scheduled reconciliation. Row-locked and idempotent:
     * safe to call repeatedly or concurrently without double-crediting/double-reversing.
     */
    public function reconcile(MobileMoneyTransaction $mm): void
    {
        if (in_array($mm->status, ['successful', 'failed', 'cancelled', 'pending_approval'], true)) {
            return;
        }

        $result = $this->marzPay->checkStatusByReference($mm->reference, $mm->provider_reference);
        if (!$result['success']) {
            return;
        }

        $status = $result['status'];

        DB::transaction(function () use ($mm, $status) {
            $fresh = MobileMoneyTransaction::whereKey($mm->id)->lockForUpdate()->first();
            if (!$fresh || $fresh->isFinal()) {
                return;
            }

            // MarzPay's own /transactions endpoint reports 'completed' in practice (confirmed
            // via a real test call) even though their docs' example showed 'successful' --
            // accept both so we're not relying on a single unverified vocabulary.
            if (in_array($status, ['successful', 'completed'], true)) {
                if ($fresh->type === 'deposit') {
                    $txn = $this->savingsService->deposit(
                        $fresh->savingsAccount,
                        $fresh->amount,
                        today()->toDateString(),
                        $fresh->description,
                        $fresh->reference
                    );
                    $fresh->update(['status' => 'successful', 'savings_transaction_id' => $txn->id]);
                } else {
                    // Withdrawal: already debited at approval time -- just finalize.
                    $fresh->update(['status' => 'successful']);
                }
            } elseif (in_array($status, ['failed', 'cancelled'], true)) {
                if ($fresh->type === 'withdrawal' && $fresh->savings_transaction_id) {
                    $this->reverseFailedWithdrawal($fresh);
                }
                $fresh->update(['status' => $status, 'failure_reason' => 'MarzPay reported: ' . $status]);
            }
            // Otherwise still pending/processing on MarzPay's side -- leave as-is, check again later.
        });
    }

    /**
     * Credit back whatever was ACTUALLY debited for this withdrawal -- not $mm->amount,
     * since SavingsService::withdraw() may have added a withdrawal fee on top of it (the
     * client is owed that back too; a failed payout must make them completely whole).
     */
    protected function reverseFailedWithdrawal(MobileMoneyTransaction $mm): void
    {
        $debited = $mm->savingsTransaction?->amount ?? $mm->amount;

        $this->savingsService->deposit(
            $mm->savingsAccount,
            $debited,
            today()->toDateString(),
            "Mobile money withdrawal reversed (payout failed) - {$mm->reference}",
            $mm->reference . '-REV'
        );
    }

    /**
     * MarzPay rejects anything that isn't E.164 (+256...). Client::phone has no enforced
     * format, so normalize here -- the single point of entry for both deposit and
     * withdrawal -- and fail loudly rather than send a malformed number to the API.
     */
    protected function normalizePhoneOrFail(string $phone): string
    {
        $normalized = PhoneNumber::normalize($phone);

        if (!$normalized) {
            throw new InvalidArgumentException("Unrecognized phone number format: {$phone}");
        }

        return $normalized;
    }
}
