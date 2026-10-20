<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\SavingsAccount;
use App\Models\SavingsInterestRun;
use App\Models\SavingsInterestTier;
use App\Models\SavingsProduct;
use App\Services\SavingsInterestRunService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** Accounting → Running Interest: preview, run, report and undo savings interest per product. */
class SavingsInterestController extends Controller
{
    public function __construct(protected SavingsInterestRunService $runs) {}

    public function index(Request $request)
    {
        $products = SavingsProduct::where('is_active', true)->orderByRaw("name like '%Interest Wallet%' desc")->orderBy('name')->get();
        $tab      = $request->get('tab', $products->first() ? 'p' . $products->first()->id : 'groups');
        $product  = str_starts_with($tab, 'p') ? $products->firstWhere('id', (int) substr($tab, 1)) : null;

        $data = compact('products', 'tab', 'product');
        if ($product) {
            $latest     = $this->runs->latestRun($product);
            $periodEnd  = $request->get('period_end') ?: now()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $from       = $latest ? null : $request->get('from');
            $preview    = $request->boolean('preview') ? $this->runs->calculate($product, $periodEnd, $from) : null;
            $history    = SavingsInterestRun::with('createdBy')->where('savings_product_id', $product->id)->orderByDesc('period_end')->orderByDesc('id')->get();
            $bands      = $this->runs->bands($product);
            $tiers      = SavingsInterestTier::orderBy('min_balance')->get();
            $specials   = SavingsAccount::with('client')->where('product_id', $product->id)->whereNotNull('interest_rate_override')->orderBy('account_number')->get();
            // Picker for a new special rate: the product's active accounts, searchable by member name / number / account
            $pickAccounts = SavingsAccount::with('client:id,name,client_number')->where('product_id', $product->id)->where('status', 'active')
                ->whereNull('interest_rate_override')->get(['id', 'client_id', 'account_number', 'balance'])
                ->sortBy(fn ($a) => $a->client->name ?? '')->values();
            $label = fn ($id) => ($a = \App\Models\Account::find($id)) ? "{$a->account_code} {$a->account_name}" : 'not set';
            try {
                $expense = $label($this->runs->expenseAccountId($product));
            } catch (\InvalidArgumentException $e) {
                $expense = 'not set — add an interest expense account to the product';
            }
            $posting = ['expense' => $expense, 'liability' => $label($product->savings_liability_account_id)];
            $data += compact('pickAccounts');
            $data += compact('latest', 'periodEnd', 'from', 'preview', 'history', 'bands', 'tiers', 'specials', 'posting');
        } else {
            $data['groups'] = Group::orderBy('name')->get();
        }

        if ($product && $request->get('format') === 'excel' && !empty($data['preview'])) {
            return $this->previewCsv($product, $data['periodEnd'], $data['preview']['rows']);
        }

        return view('savings-interest.index', $data);
    }

    public function run(Request $request, SavingsProduct $product)
    {
        $request->validate([
            'period_end' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'expected_total' => 'required|numeric',
            'from'           => 'nullable|date|before_or_equal:period_end',
        ]);
        $from = $request->from ?: null;

        // Guard against posting something different from what was previewed (e.g. a deposit came in meanwhile)
        $calc = $this->runs->calculate($product, $request->period_end, $from);
        if (abs($calc['rows']->sum('interest') - (float) $request->expected_total) > 0.01) {
            return redirect()->route('savings-interest.index', ['tab' => 'p' . $product->id, 'period_end' => $request->period_end, 'from' => $from, 'preview' => 1])
                ->with('error', 'Balances changed since the preview — check the new figures and run again.');
        }

        try {
            $run = $this->runs->run($product, $request->period_end, $from);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('savings-interest.show', $run)
            ->with('success', "Interest posted: " . number_format($run->total_interest, 2) . " to {$run->accounts_count} {$product->name} accounts.");
    }

    public function show(Request $request, SavingsInterestRun $run)
    {
        $run->load(['product', 'createdBy', 'lines.account', 'lines.client']);
        $lines = $run->lines->sortBy(fn ($l) => $l->client->name ?? '')->values();

        if ($request->get('format') === 'excel') {
            $rows = [["{$run->product->name} interest to {$run->period_end->format('Y-m-d')}", 'Run #' . $run->id], [],
                ['#', 'Member No.', 'Member', 'Account', 'From', 'To', 'Days', 'Average balance', 'Closing balance', 'Rate', 'Interest']];
            foreach ($lines as $i => $l) {
                $rows[] = [$i + 1, $l->client->client_number ?? '', $l->client->name ?? '', $l->account->account_number ?? '', $l->period_start->format('Y-m-d'),
                    $l->period_end->format('Y-m-d'), $l->days, $l->average_balance, $l->closing_balance, $l->rate_label, $l->interest];
            }
            $rows[] = ['', '', 'Total', '', '', '', '', '', '', '', round($lines->sum('interest'), 2)];
            return $this->csvDownload($rows, 'interest-' . str($run->product->name)->slug() . '-' . $run->period_end->format('Y-m-d'));
        }
        if ($request->get('format') === 'pdf') {
            return Pdf::loadView('pdf.savings-interest-run', compact('run', 'lines'))->setPaper('a4', 'landscape')
                ->download('interest-' . str($run->product->name)->slug() . '-' . $run->period_end->format('Y-m-d') . '.pdf');
        }

        $isLatest = (int) optional($this->runs->latestRun($run->product))->id === (int) $run->id;
        return view('savings-interest.show', compact('run', 'lines', 'isLatest'));
    }

    public function undo(Request $request, SavingsInterestRun $run)
    {
        $request->validate(['confirm' => 'required|in:UNDO'], ['confirm.in' => 'Type UNDO to confirm.']);
        $productId = $run->savings_product_id;
        try {
            $this->runs->undo($run);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('savings-interest.index', ['tab' => 'p' . $productId])
            ->with('success', 'Interest run undone — its journal and every member\'s interest row were deleted. You can run it again.');
    }

    /** Interest Wallet style bands: whole balance at the rate of the highest band it reaches. */
    public function saveBands(Request $request, SavingsProduct $product)
    {
        $data = $request->validate([
            'bands'        => 'array',
            'bands.*.min'  => 'required|numeric|min:0',
            'bands.*.rate' => 'required|numeric|min:0|max:100',
        ]);
        $bands = collect($data['bands'] ?? [])->map(fn ($b) => ['min' => (float) $b['min'], 'rate' => (float) $b['rate']])->sortByDesc('min')->values()->all();
        $product->update(['interest_run_method' => 'bands', 'interest_bands' => $bands]);
        \App\Models\AuditLog::record('savings_interest_bands', "{$product->name} interest bands: " . collect($bands)->map(fn ($b) => number_format($b['min']) . '+ ' . $b['rate'] . '%')->implode(', '), 'savings');

        return back()->with('success', "{$product->name} interest rates saved.");
    }

    /** A special rate for one account (blank removes it). */
    public function saveSpecial(Request $request, SavingsProduct $product)
    {
        $data = $request->validate([
            'account_number' => 'required|string',
            'rate'           => 'nullable|numeric|min:0|max:100',
        ]);
        $account = SavingsAccount::where('product_id', $product->id)->where('account_number', trim($data['account_number']))->first();
        if (!$account) {
            return back()->with('error', "No {$product->name} account {$data['account_number']}.");
        }
        $rate = $data['rate'] === null || $data['rate'] === '' ? null : (float) $data['rate'];
        $account->update(['interest_rate_override' => $rate]);
        \App\Models\AuditLog::record('savings_interest_special', "{$account->account_number} special interest rate " . ($rate === null ? 'removed' : "set to {$rate}%"), 'savings');

        return back()->with('success', $rate === null ? "{$account->account_number} now follows the normal rates." : "{$account->account_number} now earns {$rate}% p.a.");
    }

    private function previewCsv(SavingsProduct $product, string $periodEnd, $rows)
    {
        $out = [["{$product->name} interest preview to {$periodEnd}", 'Not posted'], [],
            ['#', 'Member No.', 'Member', 'Account', 'From', 'To', 'Days', 'Average balance', 'Closing balance', 'Rate', 'Interest']];
        foreach ($rows as $i => $r) {
            $out[] = [$i + 1, $r['account']->client->client_number ?? '', $r['account']->client->name ?? '', $r['account']->account_number,
                $r['period_start'], $r['period_end'], $r['days'], $r['average_balance'], $r['closing_balance'], $r['rate_label'], $r['interest']];
        }
        $out[] = ['', '', 'Total', '', '', '', '', '', '', '', round($rows->sum('interest'), 2)];
        return $this->csvDownload($out, 'interest-preview-' . str($product->name)->slug() . '-' . $periodEnd);
    }
}
