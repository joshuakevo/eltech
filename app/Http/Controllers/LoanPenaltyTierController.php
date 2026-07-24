<?php

namespace App\Http\Controllers;

use App\Models\LoanPenaltyTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanPenaltyTierController extends Controller
{
    public function edit()
    {
        $tiers = LoanPenaltyTier::orderBy('min_installment')->get();
        return view('loan-penalty-tiers.edit', compact('tiers'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'tiers'                     => 'required|array|min:1',
            'tiers.*.min_installment'   => 'required|numeric|min:0',
            'tiers.*.max_installment'   => 'nullable|numeric|gt:tiers.*.min_installment',
            'tiers.*.penalty_amount'    => 'required|numeric|min:0',
        ]);

        $rows = collect($data['tiers'])
            ->sortBy('min_installment')
            ->values();

        DB::transaction(function () use ($rows) {
            LoanPenaltyTier::query()->delete();
            foreach ($rows as $row) {
                LoanPenaltyTier::create([
                    'min_installment' => $row['min_installment'],
                    'max_installment' => $row['max_installment'] ?: null,
                    'penalty_amount'  => $row['penalty_amount'],
                ]);
            }
        });

        return redirect()->route('loan-penalty-tiers.edit')->with('success', 'Loan penalty tiers updated successfully.');
    }
}
