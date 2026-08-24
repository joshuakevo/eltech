<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\SmsSubscriptionPayment;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gates SMS sending behind a free trial then a paid monthly subscription,
 * billed org-wide via MarzPay mobile money collection (reuses the same
 * MarzPayService/webhook pattern as savings mobile money -- see
 * MobileMoneyService for the sibling implementation this mirrors).
 */
class SmsSubscriptionService
{
    public function __construct(protected MarzPayService $marzPay) {}

    public function freeTrialLimit(): int
    {
        return (int) SystemSetting::get('sms_free_trial_count', 5);
    }

    public function freeTrialUsed(): int
    {
        return SmsLog::where('status', 'sent')->count();
    }

    public function freeTrialRemaining(): int
    {
        return max(0, $this->freeTrialLimit() - $this->freeTrialUsed());
    }

    public function activeSubscription(): ?SmsSubscriptionPayment
    {
        return SmsSubscriptionPayment::where('status', 'successful')
            ->whereDate('period_end', '>=', today())
            ->orderByDesc('period_end')
            ->first();
    }

    public function canSend(): bool
    {
        return $this->freeTrialRemaining() > 0 || $this->activeSubscription() !== null;
    }

    public function subscriptionPrice(): float
    {
        return (float) SystemSetting::get('sms_subscription_price', 100000);
    }

    /**
     * Charge the given phone number via MarzPay for one month of SMS access.
     * The client approves the charge themselves via USSD/PIN prompt; final
     * confirmation happens in reconcile(), never trusting this call's
     * immediate response alone (mirrors MobileMoneyService::initiateDeposit()).
     */
    public function subscribe(string $phone, ?User $user): array
    {
        $amount    = $this->subscriptionPrice();
        $reference = $this->marzPay->generateReference();

        $payment = SmsSubscriptionPayment::create([
            'phone_number' => $phone,
            'amount'       => $amount,
            'reference'    => $reference,
            'status'       => 'pending',
            'initiated_by' => $user?->id,
        ]);

        $orgName = SystemSetting::get('org_name', config('app.name'));
        $result  = $this->marzPay->collect(
            $phone,
            $amount,
            $reference,
            "SMS subscription — {$orgName}",
            route('marzpay.webhook')
        );

        if ($result['success']) {
            $payment->update([
                'status'             => 'processing',
                'provider_reference' => $result['provider_reference'] ?? null,
            ]);

            return ['success' => true, 'message' => 'Payment request sent. Approve it on your phone to activate SMS sending.'];
        }

        $payment->update(['status' => 'failed', 'failure_reason' => $result['message'] ?? 'Unknown error']);

        return ['success' => false, 'message' => $result['message'] ?? 'Could not initiate payment.'];
    }

    /**
     * Re-verify a non-final payment directly against MarzPay (never trust a webhook
     * payload alone) and, on success, activate a 30-day subscription window from now.
     */
    public function reconcile(SmsSubscriptionPayment $payment): void
    {
        if ($payment->isFinal()) {
            return;
        }

        $result = $this->marzPay->checkStatusByReference($payment->reference, $payment->provider_reference);
        if (!$result['success']) {
            return;
        }

        $status = $result['status'];

        DB::transaction(function () use ($payment, $status) {
            $fresh = SmsSubscriptionPayment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$fresh || $fresh->isFinal()) {
                return;
            }

            if (in_array($status, ['successful', 'completed'], true)) {
                $fresh->update([
                    'status'       => 'successful',
                    'period_start' => today(),
                    'period_end'   => today()->addDays(30),
                ]);
            } elseif (in_array($status, ['failed', 'cancelled'], true)) {
                $fresh->update(['status' => $status, 'failure_reason' => 'MarzPay reported: ' . $status]);
            }
        });
    }

    /**
     * Opportunistically reconcile recent non-final payments (no guaranteed cron on
     * shared hosting -- same reasoning as MobileMoneyController::index()).
     */
    public function reconcileRecent(): void
    {
        SmsSubscriptionPayment::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '>=', now()->subDays(3))
            ->get()
            ->each(fn($p) => $this->reconcile($p));
    }
}
