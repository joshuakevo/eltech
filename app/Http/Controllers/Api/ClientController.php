<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    private function resolveClient(Request $request): ?\App\Models\Client
    {
        return $request->user()->client;
    }

    private function noClientResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No client profile linked to this account.',
        ], 404);
    }

    // GET /api/client/profile
    public function profile(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if (!$client) return $this->noClientResponse();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                     => $client->id,
                'client_number'          => $client->client_number,
                'client_type'            => $client->client_type,
                'name'                   => $client->name,
                'first_name'             => $client->first_name,
                'middle_name'            => $client->middle_name,
                'last_name'              => $client->last_name,
                'gender'                 => $client->gender,
                'date_of_birth'          => $client->date_of_birth?->toDateString(),
                'phone'                  => $client->phone,
                'alt_phone'              => $client->alt_phone,
                'email'                  => $client->email,
                'address'                => $client->address,
                'district'               => $client->district,
                'village'                => $client->village,
                'id_number'              => $client->id_number,
                'employment_status'      => $client->employment_status,
                'status'                 => $client->status,
                'joining_date'           => $client->joining_date?->toDateString(),
                'membership_fee'         => $client->membership_fee,
                'membership_fee_paid'    => $client->membership_fee_paid,
                'membership_fee_status'  => $client->membership_fee_status,
                'next_of_kin_name'       => $client->next_of_kin_name,
                'next_of_kin_relationship' => $client->next_of_kin_relationship,
                'next_of_kin_phone'      => $client->next_of_kin_phone,
                'branch'                 => $client->branch?->name,
            ],
        ]);
    }

    // GET /api/client/accounts
    public function accounts(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if (!$client) return $this->noClientResponse();

        $savings = $client->savingsAccounts()
            ->with('product:id,name,interest_rate,interest_frequency')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'account_number' => $a->account_number,
                'product'        => $a->product?->name,
                'balance'        => round($a->balance, 2),
                'status'         => $a->status,
                'opened_date'    => $a->opened_date,
                'interest_rate'  => $a->product?->interest_rate,
            ]);

        $fixedDeposits = $client->fixedDeposits()
            ->with('product:id,name')
            ->get()
            ->map(fn($fd) => [
                'id'             => $fd->id,
                'deposit_number' => $fd->deposit_number,
                'product'        => $fd->product?->name,
                'principal'      => round($fd->principal, 2),
                'interest_rate'  => $fd->interest_rate,
                'term_months'    => $fd->term_months,
                'start_date'     => $fd->start_date,
                'maturity_date'  => $fd->maturity_date,
                'interest_amount' => round($fd->interest_amount, 2),
                'maturity_amount' => round($fd->maturity_amount, 2),
                'accrued_interest' => round($fd->accrued_interest, 2),
                'status'         => $fd->status,
            ]);

        $shares = $client->shares()
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'share_number' => $s->share_number,
                'share_value' => round($s->share_value, 2),
                'amount_paid' => round($s->amount_paid, 2),
                'status'      => $s->status,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'total_savings'        => round($savings->sum('balance'), 2),
                    'total_fixed_deposits' => round($fixedDeposits->sum('principal'), 2),
                    'total_shares'         => round($shares->sum('amount_paid'), 2),
                ],
                'savings_accounts' => $savings,
                'fixed_deposits'   => $fixedDeposits,
                'shares'           => $shares,
            ],
        ]);
    }

    // GET /api/client/transactions
    public function transactions(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if (!$client) return $this->noClientResponse();

        $perPage = min((int) $request->get('per_page', 20), 100);
        $accountId = $request->get('account_id');

        $accountIds = $client->savingsAccounts()->pluck('id');

        if ($accountIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'meta'    => ['total' => 0, 'per_page' => $perPage, 'current_page' => 1, 'last_page' => 1],
            ]);
        }

        $query = SavingsTransaction::with('savingsAccount:id,account_number')
            ->whereIn('savings_account_id', $accountIds)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($accountId) {
            $query->where('savings_account_id', $accountId);
        }

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(fn($tx) => [
            'id'               => $tx->id,
            'account_number'   => $tx->savingsAccount?->account_number,
            'transaction_type' => $tx->transaction_type,
            'amount'           => round($tx->amount, 2),
            'balance_before'   => round($tx->balance_before, 2),
            'balance_after'    => round($tx->balance_after, 2),
            'transaction_date' => $tx->transaction_date,
            'reference'        => $tx->reference,
            'description'      => $tx->description,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    // GET /api/client/loans
    public function loans(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if (!$client) return $this->noClientResponse();

        $loans = $client->loans()
            ->with([
                'product:id,name',
                'schedules:id,loan_id,installment_no,due_date,principal_due,interest_due,total_due,principal_paid,interest_paid,status',
                'repayments:id,loan_id,amount,payment_date,reference,notes',
            ])
            ->latest()
            ->get()
            ->map(function ($loan) {
                $nextInstallment = $loan->schedules
                    ->whereIn('status', ['pending', 'partial'])
                    ->sortBy('installment_no')
                    ->first();

                return [
                    'id'                  => $loan->id,
                    'loan_number'         => $loan->loan_number,
                    'product'             => $loan->product?->name,
                    'principal'           => round($loan->principal, 2),
                    'interest_rate'       => $loan->interest_rate,
                    'interest_method'     => $loan->interest_method,
                    'term_months'         => $loan->term_months,
                    'repayment_frequency' => $loan->repayment_frequency,
                    'disbursement_date'   => $loan->disbursement_date?->toDateString(),
                    'maturity_date'       => $loan->maturity_date?->toDateString(),
                    'outstanding_principal' => round($loan->outstanding_principal, 2),
                    'outstanding_interest'  => round($loan->outstanding_interest, 2),
                    'outstanding_penalty'   => round($loan->outstanding_penalty, 2),
                    'total_outstanding'     => round($loan->total_outstanding, 2),
                    'status'              => $loan->status,
                    'is_overdue'          => $loan->isOverdue(),
                    'next_installment'    => $nextInstallment ? [
                        'installment_no' => $nextInstallment->installment_no,
                        'due_date'       => $nextInstallment->due_date,
                        'total_due'      => round($nextInstallment->total_due, 2),
                        'status'         => $nextInstallment->status,
                    ] : null,
                    'schedule'   => $loan->schedules->map(fn($s) => [
                        'installment_no' => $s->installment_no,
                        'due_date'       => $s->due_date,
                        'principal_due'  => round($s->principal_due, 2),
                        'interest_due'   => round($s->interest_due, 2),
                        'total_due'      => round($s->total_due, 2),
                        'principal_paid' => round($s->principal_paid, 2),
                        'interest_paid'  => round($s->interest_paid, 2),
                        'status'         => $s->status,
                    ]),
                    'repayments' => $loan->repayments->map(fn($r) => [
                        'amount'       => round($r->amount, 2),
                        'payment_date' => $r->payment_date,
                        'reference'    => $r->reference,
                        'notes'        => $r->notes,
                    ]),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $loans,
        ]);
    }
}
