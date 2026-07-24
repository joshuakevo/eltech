<?php

namespace App\Http\Controllers;

use App\Models\SavingsInterestTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingsInterestTierController extends Controller
{
    public function edit()
    {
        $tiers = SavingsInterestTier::orderBy('min_balance')->get();
        return view('savings-interest-tiers.edit', compact('tiers'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'tiers'                  => 'required|array|min:1',
            'tiers.*.min_balance'    => 'required|numeric|min:0',
            'tiers.*.max_balance'    => 'nullable|numeric|gt:tiers.*.min_balance',
            'tiers.*.rate'           => 'required|numeric|min:0|max:100',
        ]);

        $rows = collect($data['tiers'])
            ->sortBy('min_balance')
            ->values();

        DB::transaction(function () use ($rows) {
            SavingsInterestTier::query()->delete();
            foreach ($rows as $row) {
                SavingsInterestTier::create([
                    'min_balance' => $row['min_balance'],
                    'max_balance' => $row['max_balance'] ?: null,
                    'rate'        => $row['rate'],
                ]);
            }
        });

        return redirect()->route('savings-interest-tiers.edit')->with('success', 'Savings interest tiers updated successfully.');
    }
}
