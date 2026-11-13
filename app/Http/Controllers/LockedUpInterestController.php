<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LockedUpInterestCharge;
use App\Services\LockedUpInterestService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** "Charge interest" on a Locked-Up loan (pop-up loans/_locked-up-interest, permission: correct loans). */
class LockedUpInterestController extends Controller
{
    public function __construct(protected LockedUpInterestService $service) {}

    public function store(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'charge_date' => 'required|date',
            'from_date'   => 'nullable|date',
            'principal'   => 'nullable|numeric|min:0',
            'rate'        => 'nullable|numeric|min:0|max:1000',
            'amount'      => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string|max:200',
        ]);

        try {
            $charge = $this->service->charge($loan, $data);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first())->withInput();
        }

        return back()->with('success', 'Interest of ' . number_format($charge->amount, 2) . ' charged to ' . $loan->loan_number
            . ' on ' . $charge->charge_date->format('d M Y') . '. It shows on the statement and is income when recovered.');
    }

    public function destroy(Loan $loan, LockedUpInterestCharge $charge)
    {
        abort_unless((int) $charge->loan_id === (int) $loan->id, 404);

        try {
            $this->service->undo($charge);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Interest charge of ' . number_format($charge->amount, 2) . ' undone.');
    }
}
