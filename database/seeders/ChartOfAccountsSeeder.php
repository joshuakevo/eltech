<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ─── ASSETS ──────────────────────────────────────────────────────
            ['code' => '1000', 'name' => 'Current Assets',              'type' => 'asset',     'parent' => null],
            ['code' => '1001', 'name' => 'Cash on Hand',                'type' => 'asset',     'parent' => '1000'],
            ['code' => '1002', 'name' => 'Cash at Bank',                'type' => 'asset',     'parent' => '1000'],
            ['code' => '1003', 'name' => 'Mobile Money Wallet',         'type' => 'asset',     'parent' => '1000'],
            ['code' => '1009', 'name' => 'Cash at Bank Centenary',      'type' => 'asset',     'parent' => '1000', 'payment_source' => true],
            ['code' => '1010', 'name' => 'Cash at Hand Kairos',         'type' => 'asset',     'parent' => '1000', 'payment_source' => true],
            ['code' => '1011', 'name' => 'Petty Cash',                  'type' => 'asset',     'parent' => '1000', 'payment_source' => true],
            ['code' => '1100', 'name' => 'Loan Receivables',            'type' => 'asset',     'parent' => null],
            ['code' => '1101', 'name' => 'Loans Receivable — General',  'type' => 'asset',     'parent' => '1100'],
            ['code' => '1102', 'name' => 'Loans Receivable — Business', 'type' => 'asset',     'parent' => '1100'],
            ['code' => '1103', 'name' => 'Loans Receivable — Emergency','type' => 'asset',     'parent' => '1100'],
            ['code' => '1104', 'name' => 'Locked-Up Loans Receivable',  'type' => 'asset',     'parent' => '1100'],
            ['code' => '1105', 'name' => 'Loan Interest Receivable',    'type' => 'asset',     'parent' => '1100'],
            ['code' => '1106', 'name' => 'Accrued Interest',            'type' => 'asset',     'parent' => '1100'],
            ['code' => '1107', 'name' => 'Penalty Receivable',          'type' => 'asset',     'parent' => '1100'],
            ['code' => '1108', 'name' => 'Accrued Penalty Charge',      'type' => 'asset',     'parent' => '1100'],
            ['code' => '1109', 'name' => 'Loan Provisions — Specific',  'type' => 'asset',     'parent' => '1100'],
            ['code' => '1110', 'name' => 'Loan Provisions — General',   'type' => 'asset',     'parent' => '1100'],
            ['code' => '1111', 'name' => 'Overdraft Fees Receivable',   'type' => 'asset',     'parent' => '1100'],
            ['code' => '1112', 'name' => 'Locked-Up Loans Interest Receivable', 'type' => 'asset', 'parent' => '1100'],
            ['code' => '1200', 'name' => 'Fixed Assets',                'type' => 'asset',     'parent' => null],
            ['code' => '1201', 'name' => 'Office Equipment',            'type' => 'asset',     'parent' => '1200'],
            ['code' => '1202', 'name' => 'Computers & IT Equipment',    'type' => 'asset',     'parent' => '1200'],
            ['code' => '1203', 'name' => 'Furniture & Fittings',        'type' => 'asset',     'parent' => '1200'],
            ['code' => '1204', 'name' => 'Motor Vehicle',               'type' => 'asset',     'parent' => '1200'],
            ['code' => '1205', 'name' => 'Accumulated Depreciation',    'type' => 'asset',     'parent' => '1200'],
            ['code' => '1300', 'name' => 'Other Assets',                'type' => 'asset',     'parent' => null],
            ['code' => '1301', 'name' => 'Prepaid Expenses',            'type' => 'asset',     'parent' => '1300'],
            ['code' => '1302', 'name' => 'Accrued Income',              'type' => 'asset',     'parent' => '1300'],
            ['code' => '1400', 'name' => 'Investments',                 'type' => 'asset',     'parent' => null],
            ['code' => '1401', 'name' => 'Short Term Investments',      'type' => 'asset',     'parent' => '1400'],
            ['code' => '1402', 'name' => 'Insurance Premium Reserve',   'type' => 'asset',     'parent' => '1400'],
            ['code' => '1403', 'name' => 'Capital Reserve Fund',        'type' => 'asset',     'parent' => '1400'],
            ['code' => '1404', 'name' => 'Treasury Bonds',              'type' => 'asset',     'parent' => '1400'],

            // ─── LIABILITIES ─────────────────────────────────────────────────
            ['code' => '2000', 'name' => 'Current Liabilities',         'type' => 'liability', 'parent' => null],
            ['code' => '2001', 'name' => 'Member Savings (General)',     'type' => 'liability', 'parent' => '2000'],
            ['code' => '2002', 'name' => 'Fixed Deposit Liabilities',   'type' => 'liability', 'parent' => '2000'],
            ['code' => '2003', 'name' => 'Interest Payable (FD)',       'type' => 'liability', 'parent' => '2000'],
            ['code' => '2004', 'name' => 'Accrued Expenses',            'type' => 'liability', 'parent' => '2000'],
            ['code' => '2005', 'name' => 'Group Member Savings',        'type' => 'liability', 'parent' => '2000'],
            ['code' => '2006', 'name' => 'Savings Interest Payable (Accrued, Uncredited)', 'type' => 'liability', 'parent' => '2000'],
            ['code' => '2011', 'name' => 'NSSF Liability',              'type' => 'liability', 'parent' => '2000'],
            ['code' => '2012', 'name' => 'PAYE Liability',               'type' => 'liability', 'parent' => '2000'],
            ['code' => '2013', 'name' => 'Unknown Funds',                'type' => 'liability', 'parent' => '2000'],
            ['code' => '2014', 'name' => 'Audit Fees Payable',           'type' => 'liability', 'parent' => '2000'],
            ['code' => '2015', 'name' => 'Other Payables',               'type' => 'liability', 'parent' => '2000'],
            ['code' => '2016', 'name' => 'Group Interest Payable',       'type' => 'liability', 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Long-Term Liabilities',       'type' => 'liability', 'parent' => null],
            ['code' => '2101', 'name' => 'Borrowings',                  'type' => 'liability', 'parent' => '2100'],

            // ─── EQUITY ──────────────────────────────────────────────────────
            ['code' => '3000', 'name' => 'Equity',                      'type' => 'equity',    'parent' => null],
            ['code' => '3001', 'name' => 'Share Capital',               'type' => 'equity',    'parent' => '3000'],
            ['code' => '3002', 'name' => 'Retained Earnings',           'type' => 'equity',    'parent' => '3000'],
            ['code' => '3003', 'name' => 'Statutory Reserve Fund',      'type' => 'equity',    'parent' => '3000'],
            ['code' => '3004', 'name' => 'Opening Balance Equity',      'type' => 'equity',    'parent' => '3000'],

            // ─── REVENUE ─────────────────────────────────────────────────────
            ['code' => '4000', 'name' => 'Revenue',                     'type' => 'revenue',   'parent' => null],
            ['code' => '4001', 'name' => 'Interest Income — General Loans','type' => 'revenue','parent' => '4000'],
            ['code' => '4002', 'name' => 'Interest Income — Business Loans','type' => 'revenue','parent' => '4000'],
            ['code' => '4003', 'name' => 'Interest Income — Emergency Loans','type' => 'revenue','parent' => '4000'],
            ['code' => '4004', 'name' => 'Penalty Income',              'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4005', 'name' => 'Processing Fees',             'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4006', 'name' => 'Withdrawal Fee Income',       'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4007', 'name' => 'Other Income',                'type' => 'revenue',   'parent' => '4000'],

            // ─── PRIOR PERIOD INCOME (Jan-Jul 2026, old system) ────────────────
            ['code' => '4100', 'name' => 'Prior Period Income (Jan–Jul 2026)', 'type' => 'revenue', 'parent' => null],
            ['code' => '4101', 'name' => 'Loan Interest Income (Jan–Jul 2026)',    'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4102', 'name' => 'Loan Application Fee',                   'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4103', 'name' => 'Withdrawal Fees (Jan–Jul 2026)',         'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4104', 'name' => 'Account Close Fee',                      'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4105', 'name' => 'Membership Fees (Jan–Jul 2026)',         'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4106', 'name' => 'Loan Management Fee',                    'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4107', 'name' => 'Penalty Income (Jan–Jul 2026)',          'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4108', 'name' => 'Other Income (Jan–Jul 2026)',            'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4109', 'name' => 'Interest From Investments',              'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4110', 'name' => 'Interest From Capital Reserve',          'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4111', 'name' => 'Overdraft Income',                       'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4112', 'name' => 'Insurance Premium Interest',             'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4113', 'name' => 'Training and Business Advisory Income',  'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4114', 'name' => 'Treasury Bond Interest',                 'type' => 'revenue', 'parent' => '4100'],
            ['code' => '4115', 'name' => 'Konnect Investment Income',              'type' => 'revenue', 'parent' => '4100'],

            // ─── EXPENSES ────────────────────────────────────────────────────
            ['code' => '5000', 'name' => 'Expenses',                    'type' => 'expense',   'parent' => null],
            ['code' => '5001', 'name' => 'Interest Expense — Savings',  'type' => 'expense',   'parent' => '5000'],
            ['code' => '5002', 'name' => 'Interest Expense — Fixed Deposits','type' => 'expense','parent' => '5000'],
            ['code' => '5010', 'name' => 'Interest Expense — Groups',    'type' => 'expense',   'parent' => '5000'],
            ['code' => '5003', 'name' => 'Staff Salaries',              'type' => 'expense',   'parent' => '5000'],
            ['code' => '5004', 'name' => 'Rent & Utilities',            'type' => 'expense',   'parent' => '5000'],
            ['code' => '5005', 'name' => 'Office Supplies',             'type' => 'expense',   'parent' => '5000'],
            ['code' => '5006', 'name' => 'Bad Debt Provision',          'type' => 'expense',   'parent' => '5000'],
            ['code' => '5007', 'name' => 'Depreciation',                'type' => 'expense',   'parent' => '5000'],
            ['code' => '5008', 'name' => 'Bank Charges',                'type' => 'expense',   'parent' => '5000'],
            ['code' => '5009', 'name' => 'Miscellaneous Expenses',      'type' => 'expense',   'parent' => '5000'],

            // ─── PRIOR PERIOD EXPENSES (Jan-Jul 2026, old system) ──────────────
            ['code' => '5100', 'name' => 'Prior Period Expenses (Jan–Jul 2026)',   'type' => 'expense', 'parent' => null],
            ['code' => '5101', 'name' => 'Interest on Savings (Jan–Jul 2026)',     'type' => 'expense', 'parent' => '5100'],
            ['code' => '5102', 'name' => 'Salaries (Jan–Jul 2026)',                'type' => 'expense', 'parent' => '5100'],
            ['code' => '5103', 'name' => 'Agency Commissions',                     'type' => 'expense', 'parent' => '5100'],
            ['code' => '5104', 'name' => 'NSSF Expense (10%)',                     'type' => 'expense', 'parent' => '5100'],
            ['code' => '5105', 'name' => 'Staff Welfare',                          'type' => 'expense', 'parent' => '5100'],
            ['code' => '5106', 'name' => 'Audit and Supervision Fees (Registrar)', 'type' => 'expense', 'parent' => '5100'],
            ['code' => '5107', 'name' => 'Marketing Costs',                        'type' => 'expense', 'parent' => '5100'],
            ['code' => '5108', 'name' => 'Legal Fees',                             'type' => 'expense', 'parent' => '5100'],
            ['code' => '5109', 'name' => 'Communication Expenses',                 'type' => 'expense', 'parent' => '5100'],
            ['code' => '5110', 'name' => 'Local Travel',                           'type' => 'expense', 'parent' => '5100'],
            ['code' => '5111', 'name' => 'Other Operating Expenses',               'type' => 'expense', 'parent' => '5100'],
            ['code' => '5112', 'name' => 'Rent Expenses (Jan–Jul 2026)',           'type' => 'expense', 'parent' => '5100'],
            ['code' => '5113', 'name' => 'Car Repair & Service',                   'type' => 'expense', 'parent' => '5100'],
            ['code' => '5114', 'name' => 'Car Insurance',                          'type' => 'expense', 'parent' => '5100'],
            ['code' => '5115', 'name' => 'Internet Costs',                         'type' => 'expense', 'parent' => '5100'],
            ['code' => '5116', 'name' => 'Computer Expenses',                      'type' => 'expense', 'parent' => '5100'],
            ['code' => '5117', 'name' => 'Bank Charges (Jan–Jul 2026)',            'type' => 'expense', 'parent' => '5100'],
            ['code' => '5118', 'name' => 'Mobile Money Charges',                   'type' => 'expense', 'parent' => '5100'],
            ['code' => '5119', 'name' => 'Specific Loan Provision Expense',        'type' => 'expense', 'parent' => '5100'],
            ['code' => '5120', 'name' => 'General Loan Provision Expense',         'type' => 'expense', 'parent' => '5100'],
            ['code' => '5121', 'name' => 'Bad Loan Write-Off',                     'type' => 'expense', 'parent' => '5100'],
            ['code' => '5122', 'name' => 'Overdrawn Savings Write-Off',            'type' => 'expense', 'parent' => '5100'],
            ['code' => '5123', 'name' => 'Donation Expense',                       'type' => 'expense', 'parent' => '5100'],
            ['code' => '5124', 'name' => 'Learning and Development',               'type' => 'expense', 'parent' => '5100'],
            ['code' => '5125', 'name' => 'Investment Expenses',                    'type' => 'expense', 'parent' => '5100'],
            ['code' => '5126', 'name' => 'CRB Expense',                            'type' => 'expense', 'parent' => '5100'],
        ];

        $created = [];

        // First pass: create accounts without parents
        foreach ($accounts as $acc) {
            if ($acc['parent'] === null) {
                $attrs = ['account_name' => $acc['name'], 'account_type' => $acc['type'], 'parent_id' => null, 'is_active' => true];
                if (isset($acc['payment_source'])) {
                    $attrs['is_payment_source'] = $acc['payment_source'];
                }
                $model = Account::updateOrCreate(['account_code' => $acc['code']], $attrs);
                $created[$acc['code']] = $model->id;
            }
        }

        // Second pass: create child accounts
        foreach ($accounts as $acc) {
            if ($acc['parent'] !== null) {
                $parentId = $created[$acc['parent']] ?? null;
                $attrs = ['account_name' => $acc['name'], 'account_type' => $acc['type'], 'parent_id' => $parentId, 'is_active' => true];
                if (isset($acc['payment_source'])) {
                    $attrs['is_payment_source'] = $acc['payment_source'];
                }
                $model = Account::updateOrCreate(['account_code' => $acc['code']], $attrs);
                $created[$acc['code']] = $model->id;
            }
        }

        $this->command->info('Chart of Accounts seeded: ' . count($accounts) . ' accounts.');
    }
}
