<?php

namespace App\Http\Controllers;

use App\Models\OverdraftRun;
use App\Services\OverdraftService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Accounting → Overdraft Charges: month-end run (preview → run → undo). Permission: run savings interest. */
class OverdraftRunController extends Controller
{
    public function __construct(protected OverdraftService $service) {}

    public function index(Request $request)
    {
        // Default: the last month end that has passed
        $date = $request->date ? Carbon::parse($request->date)->endOfMonth()->startOfDay() : today()->subMonthNoOverflow()->endOfMonth()->startOfDay();
        if (today()->isLastOfMonth() && !$request->date) {
            $date = today();
        }
        $rate    = $request->filled('rate') ? (float) $request->rate : $this->service->rate();
        $minimum = $request->filled('minimum') ? (float) $request->minimum : $this->service->minimum();

        $rows = $this->service->preview($date->toDateString(), $rate, $minimum);
        $runs = OverdraftRun::with('createdBy')->latest('id')->limit(12)->get();
        $latestApplied = OverdraftRun::where('status', 'applied')->latest('id')->first();

        return view('overdraft.run', compact('date', 'rate', 'minimum', 'rows', 'runs', 'latestApplied'));
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'date'      => 'required|date',
            'rate'      => 'required|numeric|min:0|max:100',
            'minimum'   => 'required|numeric|min:0',
            'pick'      => 'required|array|min:1',
            'pick.*'    => 'integer',
            'amount'    => 'nullable|array',
            'amount.*'  => 'nullable|numeric|min:0',
        ], ['pick.required' => 'Tick at least one account to charge.']);

        $amounts = [];
        foreach ($data['pick'] as $id) {
            $amounts[(int) $id] = $data['amount'][$id] ?? null;
        }
        try {
            $run = $this->service->run($data['date'], (float) $data['rate'], (float) $data['minimum'], $amounts);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('overdraft.run', ['date' => $run->run_date->toDateString()])
            ->with('success', "Overdraft charges for {$run->run_date->format('d M Y')}: {$run->accounts} account(s), total " . number_format($run->total, 2) . '.');
    }

    public function undo(OverdraftRun $run)
    {
        try {
            $this->service->undoRun($run);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('overdraft.run', ['date' => $run->run_date->toDateString()])->with('success', 'Overdraft run for ' . $run->run_date->format('d M Y') . ' undone.');
    }
}
