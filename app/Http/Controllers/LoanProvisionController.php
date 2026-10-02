<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoanProvision;
use App\Models\SystemSetting;
use App\Rules\DateInOpenPeriod;
use App\Services\LoanProvisionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoanProvisionController extends Controller
{
    public function __construct(protected LoanProvisionService $provisionService)
    {
    }

    /**
     * General / Specific provisions tabs. Each tab previews the run for the chosen
     * as-at date (default: last month end) and lists previous runs.
     */
    public function index(Request $request)
    {
        $type = $request->type === 'specific' ? 'specific' : 'general';

        $asAt = $request->as_at_date
            ? Carbon::parse($request->as_at_date)->toDateString()
            : $this->defaultAsAt();
        $rate = $request->filled('rate')
            ? (float) $request->rate
            : (float) SystemSetting::get('general_provision_rate', LoanProvisionService::DEFAULT_GENERAL_RATE);

        $preview = $this->provisionService->preview($type, $asAt, $rate);

        if ($request->format === 'excel') {
            return $this->csvDownload(
                $this->breakdownRows($type, $preview['lines']->map(fn ($l) => [
                    'loan' => $l['loan'], 'client' => $l['loan']->client, 'outstanding' => $l['outstanding'],
                    'rate' => $l['rate'], 'provision' => $l['provision_amount'],
                    'days' => $l['days_in_arrears'], 'arrears_date' => $l['arrears_date'],
                ]), $preview['total_outstanding'], $preview['required']),
                "{$type}-provision-preview-{$asAt}"
            );
        }

        $runs = LoanProvision::with('transaction', 'createdBy')
            ->where('provision_type', $type)
            ->orderByDesc('as_at_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $alreadyPosted = LoanProvision::where('provision_type', $type)
            ->where('status', 'posted')
            ->whereDate('as_at_date', $asAt)
            ->first();

        return view('loan-provisions.index', compact('type', 'asAt', 'rate', 'preview', 'runs', 'alreadyPosted'));
    }

    public function store(Request $request)
    {
        $type = $request->provision_type === 'specific' ? 'specific' : 'general';

        $data = $request->validate([
            'as_at_date' => ['required', 'date', new DateInOpenPeriod()],
            'rate'       => $type === 'general' ? 'required|numeric|gt:0|max:100' : 'nullable',
            'notes'      => 'nullable|string|max:500',
        ]);

        $asAt = Carbon::parse($data['as_at_date'])->toDateString();
        $run  = $type === 'specific'
            ? $this->provisionService->runSpecific($asAt, $data['notes'] ?? null)
            : $this->provisionService->runGeneral($asAt, (float) $data['rate'], $data['notes'] ?? null);

        AuditLog::record('created', "Posted {$type} loan provision as at {$asAt}"
            . ($type === 'general' ? " @ {$run->rate}%" : '')
            . ' — required ' . number_format($run->required_provision, 2)
            . ', adjustment ' . number_format($run->adjustment, 2), 'loan_provisions');

        $msg = abs($run->adjustment) < 0.005
            ? 'Provision run saved. The required provision is already held, so no journal entry was needed.'
            : ucfirst($type) . ' provision posted. Journal entry: ' . optional($run->transaction)->reference;

        return redirect()->route('loan-provisions.show', $run)->with('success', $msg);
    }

    public function show(Request $request, LoanProvision $loanProvision)
    {
        $loanProvision->load('transaction', 'createdBy');
        $lines = $loanProvision->lines()
            ->with('loan.product', 'client')
            ->when($loanProvision->provision_type === 'specific',
                fn ($q) => $q->orderByDesc('days_in_arrears'),
                fn ($q) => $q->orderByDesc('outstanding_principal'))
            ->get();

        if ($request->format === 'excel') {
            return $this->csvDownload(
                $this->breakdownRows($loanProvision->provision_type, $lines->map(fn ($l) => [
                    'loan' => $l->loan, 'client' => $l->client, 'outstanding' => $l->outstanding_principal,
                    'rate' => $l->rate, 'provision' => $l->provision_amount,
                    'days' => $l->days_in_arrears, 'arrears_date' => $l->oldest_arrears_date,
                ]), $loanProvision->total_outstanding, $loanProvision->required_provision),
                "{$loanProvision->provision_type}-provision-" . $loanProvision->as_at_date->format('Y-m-d')
            );
        }

        return view('loan-provisions.show', ['run' => $loanProvision, 'lines' => $lines]);
    }

    private function breakdownRows(string $type, $lines, float $totalOutstanding, float $required): array
    {
        $specific = $type === 'specific';
        $rows = [array_merge(
            ['Loan #', 'Client', 'Client #', 'Product', 'Disbursed'],
            $specific ? ['Oldest Arrears Date', 'Days in Arrears'] : [],
            ['Outstanding Principal', 'Rate %', 'Provision']
        )];
        foreach ($lines as $l) {
            $rows[] = array_merge(
                [
                    optional($l['loan'])->loan_number,
                    optional($l['client'])->name,
                    optional($l['client'])->client_number,
                    optional(optional($l['loan'])->product)->name,
                    optional(optional($l['loan'])->disbursement_date)->format('Y-m-d'),
                ],
                $specific ? [$l['arrears_date'] ? Carbon::parse($l['arrears_date'])->format('Y-m-d') : '', $l['days']] : [],
                [$l['outstanding'], $l['rate'], $l['provision']]
            );
        }
        $rows[] = array_merge(['', '', '', '', 'TOTAL'], $specific ? ['', ''] : [], [$totalOutstanding, '', $required]);
        return $rows;
    }

    private function defaultAsAt(): string
    {
        $today = today();
        return $today->isLastOfMonth()
            ? $today->toDateString()
            : $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
    }
}
