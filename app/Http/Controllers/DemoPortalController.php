<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Public investor-portal demo (/demo/investor) for showing prospects what members see.
 * Everything is SAMPLE data built in memory from today's date: no login, nothing read
 * from or written to the database, no journals. The screens and PDFs mirror the real
 * client portal (client-portal/*, pdf/investment-certificate, pdf/fd-monthly-statement).
 */
class DemoPortalController extends Controller
{
    private function client(): Client
    {
        $client = new Client(['name' => 'Demo Investor', 'client_number' => 'DEMO-0001', 'status' => 'active']);
        $client->id = 0;
        $client->joining_date = today()->subYears(2)->startOfMonth();

        return $client;
    }

    /** Sample placements: a monthly-rate venture placement and an ordinary fixed deposit. */
    private function investments(): Collection
    {
        $client = $this->client();

        $venture = new FixedDepositProduct([
            'name' => 'Venture Capital Studio', 'interest_rate' => 24, 'rate_basis' => 'monthly',
            'accrual_method' => 'anniversary', 'asset_class' => 'Investment certificate', 'term_months' => 12,
        ]);
        $fdProduct = new FixedDepositProduct([
            'name' => 'Fixed Deposit (6 months)', 'interest_rate' => 12, 'rate_basis' => 'annual',
            'accrual_method' => 'prorata', 'term_months' => 6,
        ]);

        $make = function (int $n, FixedDepositProduct $product, float $principal, float $rate, int $months, $start, string $ref) use ($client) {
            $fd = new FixedDeposit([
                'deposit_number' => 'FD-DEMO-' . str_pad($n, 3, '0', STR_PAD_LEFT), 'principal' => $principal,
                'interest_rate' => $rate, 'compounding' => 'simple', 'term_months' => $months,
                'start_date' => $start, 'maturity_date' => $start->copy()->addMonthsNoOverflow($months),
                'interest_amount' => FixedDeposit::interestFor($principal, $rate, $months),
                'status' => 'active', 'certificate_reference' => $ref,
                'asset_class' => $product->asset_class,
            ]);
            $fd->maturity_amount = $principal + $fd->interest_amount;
            $fd->id = $n;
            $fd->setRelation('product', $product);
            $fd->setRelation('client', $client);

            return $fd;
        };

        return collect([
            1 => $make(1, $venture, 10000000, 24, 12, today()->subMonthsNoOverflow(5)->day(min(15, today()->day)), 'VCS-DEMO-001'),
            2 => $make(2, $fdProduct, 5000000, 12, 6, today()->subMonthsNoOverflow(2)->startOfMonth()->addDays(2), ''),
        ]);
    }

    /** Sample savings account with six months of activity (oldest first, running balance). */
    private function savings(): SavingsAccount
    {
        $account = new SavingsAccount(['account_number' => 'SA-DEMO-001', 'balance' => 0, 'status' => 'active']);
        $account->id = 1;
        $account->opened_date = today()->subYears(2)->startOfMonth();
        $account->setRelation('product', new SavingsProduct(['name' => 'Ordinary Savings', 'interest_rate' => 4]));

        $moves = [
            [6, 5, 'deposit', 800000, 'Opening balance'],
            [5, 3, 'deposit', 350000, 'Cash deposit'],
            [5, 30, 'interest', 2666, 'Interest credited'],
            [4, 12, 'withdrawal', 200000, 'Cash withdrawal'],
            [4, 30, 'interest', 3166, 'Interest credited'],
            [3, 8, 'deposit', 500000, 'Mobile money deposit'],
            [2, 18, 'withdrawal', 150000, 'Cash withdrawal'],
            [1, 6, 'deposit', 250000, 'Cash deposit'],
            [0, 2, 'deposit', 120000, 'Mobile money deposit'],
        ];

        $balance = 0;
        $rows = collect();
        foreach ($moves as $i => [$monthsAgo, $day, $type, $amount, $desc]) {
            $date = today()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $date = $date->day(min($day, $date->daysInMonth));
            if ($date->gt(today())) {
                $date = today();
            }
            $before = $balance;
            $balance += $type === 'withdrawal' ? -$amount : $amount;
            $txn = new SavingsTransaction([
                'transaction_type' => $type, 'amount' => $amount, 'description' => $desc,
                'reference' => 'DEMO' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'balance_before' => $before, 'balance_after' => $balance, 'transaction_date' => $date,
            ]);
            $rows->push($txn);
        }
        $account->balance = $balance;
        $account->setRelation('transactions', $rows);

        return $account;
    }

    private function shared(): array
    {
        return ['client' => $this->client(), 'investments' => $this->investments(), 'savings' => $this->savings()];
    }

    public function overview()
    {
        $data = $this->shared();
        $txns = $data['savings']->transactions;

        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = today()->startOfMonth()->subMonths($i);
            $net = $txns->filter(fn ($t) => $t->transaction_date->isSameMonth($month))
                ->sum(fn ($t) => $t->transaction_type === 'withdrawal' ? -$t->amount : $t->amount);
            $trend[] = ['label' => $month->format('M Y'), 'amount' => (float) $net];
        }
        $thisMonth = $txns->filter(fn ($t) => $t->transaction_date->isSameMonth(today()));

        return view('demo.investor.overview', $data + [
            'trend' => $trend,
            'depositsThisMonth' => $thisMonth->where('transaction_type', 'deposit')->sum('amount'),
            'withdrawalsThisMonth' => $thisMonth->where('transaction_type', 'withdrawal')->sum('amount'),
        ]);
    }

    public function statements()
    {
        return view('demo.investor.statements', $this->shared());
    }

    public function investment(int $id)
    {
        $data = $this->shared();
        abort_unless($data['investments']->has($id), 404);

        return view('demo.investor.investment', $data + ['fixedDeposit' => $data['investments'][$id]]);
    }

    public function savingsStatement()
    {
        return view('demo.investor.savings', $this->shared());
    }

    public function monthlyStatementPdf(int $id)
    {
        $fixedDeposit = $this->investments()->get($id) ?? abort(404);
        $schedule = $fixedDeposit->monthlySchedule();

        return Pdf::loadView('pdf.fd-monthly-statement', compact('fixedDeposit', 'schedule'))
            ->setPaper('a4', 'portrait')->stream("demo-statement-{$fixedDeposit->deposit_number}.pdf");
    }

    public function certificatePdf(int $id)
    {
        $fixedDeposit = $this->investments()->get($id) ?? abort(404);
        $client = $fixedDeposit->client;
        $view = $fixedDeposit->product->isMonthlyRate() || $fixedDeposit->product->isAnniversary()
            ? 'pdf.investment-certificate' : 'pdf.fd-certificate';

        return Pdf::loadView($view, compact('client', 'fixedDeposit'))
            ->setPaper('a4', 'portrait')->stream("demo-certificate-{$fixedDeposit->deposit_number}.pdf");
    }
}
