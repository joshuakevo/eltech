<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Loans → Schedule Generator: try out amounts, rates, terms and products before giving a loan. Nothing is saved.
 * The schedule comes from LoanService::buildScheduleRows — the same calculation a real disbursement uses.
 * "What can they afford?" turns an installment the member can pay into the largest principal for those terms.
 */
class LoanCalculatorController extends Controller
{
    public function index(Request $request, LoanService $loans)
    {
        $products = LoanProduct::where('is_active', true)->where('name', '!=', 'Locked-Up Loans')->orderBy('name')->get();
        $product  = $products->firstWhere('id', (int) $request->loan_product_id);

        $in = [
            'mode'        => $request->get('mode', 'amount'),
            'principal'   => $request->filled('principal') ? (float) str_replace(',', '', $request->principal) : null,
            'installment' => $request->filled('installment') ? (float) str_replace(',', '', $request->installment) : null,
            // Rate is entered per month (e.g. 2.8); the schedule maths uses the annual rate (× 12)
            'monthly'     => $request->filled('rate') ? (float) $request->rate : round((float) ($product->interest_rate ?? 0) / 12, 4),
            'method'      => $request->get('method') ?: ($product->interest_method ?? 'reducing'),
            'term'        => $request->filled('term') ? (int) $request->term : (int) ($product->term_months ?? 12),
            'frequency'   => $request->get('frequency') ?: ($product->repayment_frequency ?? 'monthly'),
            'date'        => $request->get('date') ?: today()->toDateString(),
            'mgmt_rate'   => $request->filled('mgmt_rate') ? (float) $request->mgmt_rate : 1.5,
            'ins_rate'    => $request->filled('ins_rate') ? (float) $request->ins_rate : 1.5,
            'app_fee'     => $request->filled('app_fee') ? (float) str_replace(',', '', $request->app_fee) : 0.0,
        ];

        $in['rate'] = round($in['monthly'] * 12, 4);

        $result = null;
        $error  = null;
        $step    = Loan::stepMonthsFor($in['frequency']);
        $periods = intdiv(max(0, $in['term']), $step);

        // Affordability: the largest principal whose installment fits what the member can pay
        if ($in['mode'] === 'afford' && $in['installment'] > 0 && $periods > 0) {
            if ($in['method'] === 'flat') {
                $in['principal'] = floor($in['installment'] * $periods / (1 + $in['rate'] / 100 * $in['term'] / 12));
            } else {
                $pr = $in['rate'] / 100 / (12 / $step);
                $in['principal'] = floor($pr == 0 ? $in['installment'] * $periods : $in['installment'] * (1 - pow(1 + $pr, -$periods)) / $pr);
            }
        }

        if ($in['principal'] > 0) {
            if ($periods < 1) {
                $error = 'The term must cover at least one installment (' . (Loan::FREQUENCIES[$in['frequency']][0] ?? 'monthly') . ').';
            } else {
                $loan = new Loan([
                    'principal' => $in['principal'], 'interest_rate' => $in['rate'], 'interest_method' => $in['method'],
                    'term_months' => $in['term'], 'repayment_frequency' => $in['frequency'],
                ]);
                $rows = $loans->buildScheduleRows($loan, Carbon::parse($in['date']));
                $interest = round(array_sum(array_column($rows, 'interest_due')), 2);
                $fees = [
                    'management' => round($in['principal'] * $in['mgmt_rate'] / 100, 2),
                    'insurance'  => round($in['principal'] * $in['ins_rate'] / 100, 2),
                    'application'=> round($in['app_fee'], 2),
                ];
                $result = [
                    'rows'        => $rows,
                    'installment' => $rows[0]['total_due'] ?? 0,
                    'last'        => end($rows)['total_due'] ?? 0,
                    'periods'     => count($rows),
                    'interest'    => $interest,
                    'repayable'   => round($in['principal'] + $interest, 2),
                    'fees'        => $fees,
                    'net'         => round($in['principal'] - array_sum($fees), 2),
                    'maturity'    => Carbon::parse($in['date'])->addMonths($in['term']),
                    'outOfRange'  => $product && (($product->min_amount && $in['principal'] < $product->min_amount) || ($product->max_amount && $in['principal'] > $product->max_amount)),
                ];
            }
        }

        if ($result && $request->get('format') === 'excel') {
            $out = [['Loan schedule (estimate)', $product->name ?? 'Custom terms'],
                ['Principal', $in['principal'], 'Rate % per month', $in['monthly'], 'Rate % p.a.', $in['rate'], 'Method', $in['method'], 'Term (months)', $in['term'], 'Frequency', $in['frequency']], [],
                ['#', 'Due date', 'Principal', 'Interest', 'Installment', 'Balance after']];
            foreach ($result['rows'] as $r) {
                $out[] = [$r['installment_no'], $r['due_date'], $r['principal_due'], $r['interest_due'], $r['total_due'], $r['balance_after']];
            }
            $out[] = ['', 'Total', $in['principal'], $result['interest'], $result['repayable'], ''];
            return $this->csvDownload($out, 'loan-schedule-estimate-' . now()->format('Y-m-d'));
        }

        if ($result && $request->get('format') === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.loan-schedule-estimate', compact('product', 'in', 'result'))->setPaper('a4', 'portrait')
                ->download('loan-schedule-estimate-' . (int) $in['principal'] . '-' . now()->format('Y-m-d') . '.pdf');
        }

        return view('loans.calculator', compact('products', 'product', 'in', 'result', 'error'));
    }
}
