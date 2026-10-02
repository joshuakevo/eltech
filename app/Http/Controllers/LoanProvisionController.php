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
     * General / Specific provisions tabs. The General tab previews the run for the
     * chosen as-at date (default: last month end) and lists previous runs.
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

        $preview = null;
        if ($type === 'general') {
            $preview = $this->provisionService->previewGeneral($asAt, $rate);

            if ($request->format === 'excel') {
                return $this->previewCsv($preview);
            }
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
        $data = $request->validate([
            'as_at_date' => ['required', 'date', new DateInOpenPeriod()],
            'rate'       => 'required|numeric|gt:0|max:100',
            'notes'      => 'nullable|string|max:500',
        ]);

        $asAt = Carbon::parse($data['as_at_date'])->toDateString();
        $run  = $this->provisionService->runGeneral($asAt, (float) $data['rate'], $data['notes'] ?? null);

        AuditLog::record('created', "Posted general loan provision as at {$asAt} @ {$run->rate}% — required "
            . number_format($run->required_provision, 2) . ', adjustment ' . number_format($run->adjustment, 2), 'loan_provisions');

        $msg = abs($run->adjustment) < 0.005
            ? 'Provision run saved. The GL already holds the required provision, so no journal entry was needed.'
            : 'General provision posted. Journal entry: ' . optional($run->transaction)->reference;

        return redirect()->route('loan-provisions.show', $run)->with('success', $msg);
    }

    public function show(Request $request, LoanProvision $loanProvision)
    {
        $loanProvision->load('transaction', 'createdBy');
        $lines = $loanProvision->lines()
            ->with('loan.product', 'client')
            ->orderByDesc('outstanding_principal')
            ->get();

        if ($request->format === 'excel') {
            $rows = [['Loan #', 'Client', 'Client #', 'Product', 'Disbursed', 'Outstanding Principal', 'Rate %', 'Provision']];
            foreach ($lines as $l) {
                $rows[] = [
                    optional($l->loan)->loan_number,
                    optional($l->client)->name,
                    optional($l->client)->client_number,
                    optional(optional($l->loan)->product)->name,
                    optional(optional($l->loan)->disbursement_date)->format('Y-m-d'),
                    $l->outstanding_principal,
                    $l->rate,
                    $l->provision_amount,
                ];
            }
            $rows[] = ['', '', '', '', 'TOTAL', $loanProvision->total_outstanding, '', $loanProvision->required_provision];
            return $this->csvDownload($rows, 'general-provision-' . $loanProvision->as_at_date->format('Y-m-d'));
        }

        return view('loan-provisions.show', ['run' => $loanProvision, 'lines' => $lines]);
    }

    private function previewCsv(array $preview)
    {
        $rows = [['Loan #', 'Client', 'Client #', 'Product', 'Disbursed', 'Outstanding Principal', 'Rate %', 'Provision']];
        foreach ($preview['lines'] as $line) {
            $loan = $line['loan'];
            $rows[] = [
                $loan->loan_number,
                optional($loan->client)->name,
                optional($loan->client)->client_number,
                optional($loan->product)->name,
                optional($loan->disbursement_date)->format('Y-m-d'),
                $line['outstanding'],
                $line['rate'],
                $line['provision_amount'],
            ];
        }
        $rows[] = ['', '', '', '', 'TOTAL', $preview['total_outstanding'], '', $preview['required']];
        return $this->csvDownload($rows, 'general-provision-preview-' . $preview['as_at']);
    }

    private function defaultAsAt(): string
    {
        $today = today();
        return $today->isLastOfMonth()
            ? $today->toDateString()
            : $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
    }
}
