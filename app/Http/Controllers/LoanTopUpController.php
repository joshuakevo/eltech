<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanTopUp;
use App\Services\LoanTopUpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** "Top up loan" on the loan page (pop-up loans/_topup). Top-up: permission disburse loans; undo: correct loans. */
class LoanTopUpController extends Controller
{
    private const FIELDS = ['amount', 'topup_date', 'interest_rate', 'interest_method', 'term_months', 'installment',
        'payout', 'payout_account_id', 'savings_account_id',
        'application_fee_amount', 'application_fee_method', 'management_fee_rate', 'management_fee_method',
        'insurance_fee_rate', 'insurance_fee_method'];

    public function __construct(protected LoanTopUpService $service) {}

    public function preview(Request $request, Loan $loan)
    {
        return response()->json($this->service->preview($loan, $request->only(self::FIELDS)));
    }

    public function store(Request $request, Loan $loan)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            $topUp = $this->service->topUp($loan, $request->only(self::FIELDS), $request->reason);
        } catch (ValidationException $e) {
            return redirect()->route('loans.show', $loan)->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Topped up by ' . number_format($topUp->amount, 2)
            . ': the loan is now ' . number_format($topUp->new_principal, 2) . " over {$topUp->term_months} months from "
            . $topUp->topup_date->format('d M Y') . '.');
    }

    public function undo(Loan $loan, LoanTopUp $topUp)
    {
        abort_unless((int) $topUp->loan_id === (int) $loan->id, 404);
        try {
            $this->service->undo($topUp);
        } catch (ValidationException $e) {
            return redirect()->route('loans.show', $loan)->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Top-up undone: the loan is back as it was, and the payout journal is reversed.');
    }
}
