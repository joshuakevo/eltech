# ElTech Finance — Claude Instructions

## Project Overview
Full-stack financial management system (SACCO/MFI) built with Laravel 9, PHP 8.0, MySQL.
App name: **ElTech Finance** | DB: `eltech_finance` | Bootstrap 5.3 CDN (no npm build needed)

---

## Dev Commands
```bash
php artisan serve          # Start development server
php artisan migrate        # Run migrations
php artisan db:seed        # Seed all (accounts, roles, admin user)
php artisan migrate:fresh --seed  # Full reset
```

---

## Architecture

### Service Layer (business logic lives here — not in controllers)
| Service | Responsibility |
|---------|---------------|
| `app/Services/AccountingService.php` | Double-entry GL posting, balance validation |
| `app/Services/LoanService.php` | Loan calculations, amortization schedules, repayments |
| `app/Services/SavingsService.php` | Savings transactions, interest posting |
| `app/Services/FixedDepositService.php` | FD creation, maturity, interest (simple interest) |

### Key Files
- **Routes:** `routes/web.php`
- **Layout:** `resources/views/layouts/app.blade.php`
- **Seeder:** `database/seeders/ChartOfAccountsSeeder.php` (45 GL accounts)
- **Settings:** `app/Models/SystemSetting.php` (org name, etc.)

### Models (core)
`Account`, `Transaction`, `TransactionLine`, `Client`, `Loan`, `LoanProduct`, `LoanSchedule`, `LoanRepayment`, `LoanGuarantor`, `SavingsAccount`, `SavingsProduct`, `SavingsTransaction`, `FixedDeposit`, `FixedDepositProduct`, `MemberShare`, `ShareTransaction`, `Group`, `GroupMember`, `GroupTransaction`, `Branch`, `Employee`, `PayrollRun`, `PayrollItem`, `AuditLog`, `User`, `SystemSetting`

### Key schema additions (recent)
- `transaction_lines.client_id` — nullable FK to `clients`, tags a GL line to a specific member
- `member_shares`: added `liquidated_at`, `liquidation_notes`, `liquidated_by`, `status=liquidated`
- `share_transactions`: audit trail for share payments, revaluations, liquidations

---

## Accounting Rules (CRITICAL — enforce always)

1. **Every financial action must post a double-entry journal entry** via `AccountingService`
2. **Transactions must balance**: `sum(debits) == sum(credits)` — enforced in `AccountingService::validateLines()`
3. **Repayment allocation order**: interest → principal → penalty (penalty last). **Exception — Locked-Up Loans** (recoveries): principal → interest (no schedule, no penalty)
4. **Savings are liabilities**: Deposit = DR Cash (1001) / CR Savings Liability
5. **FD interest formula**: `P × R × T` (simple interest, not compound)
6. **Loan schedules** are generated on **disbursement**, not on loan creation
7. **Default GL account codes**: Cash = `1001`, FD Interest Payable = `2003`

---

## Loan Provisions
- `LoanProvisionService` + `loan_provisions` / `loan_provision_lines`. Tabs (General / Specific) shown on the Loans page via `loans/_tabs.blade.php`.
- **General**: rate (default 1%, setting `general_provision_rate`) × outstanding principal as at a date, **excluding Locked-Up Loans**. As-at outstanding = current `outstanding_principal` + `principal_paid` on repayments after the date (imported loans have no repayment history).
- Each run posts only the movement to bring GL **1110** to the required level: increase DR **5120** / CR 1110, decrease DR 1110 / CR 5120. One posted run per date; runs cannot be back-dated before the latest posted run.
- **Specific**: loans (excl. Locked-Up) in arrears as at the date — days since the oldest installment due and unpaid at that date (payments after the date are peeled back off the schedule). **> 90 days → 50%**, **≥ 365 days → 100%** of outstanding principal. Posts DR **5119** / CR **1109**. Sized against the sum of previous specific runs' adjustments only — the old-system opening balance in 1109 (covers the Locked-Up book) is deliberately left untouched. Loans with a specific provision **stay in the general 1% base** (user decision).

## Loan Balance Corrections
- "Correct Balance" pop-up on the loan page (`loans/_correction.blade.php`, permission `correct loans`), logic in `LoanCorrectionService`.
- Inputs: as-at date (default 31/07/2026 transfer date), correct outstanding principal and interest at that date. Interest field defaults to the current figure (current + interest repaid since); "use this" fills the loan-terms calculation.
- Rebuilds installments from the as-at date to the **existing maturity** on the loan's due-day pattern, rate and method; interest above the natural curve is arrears on installment 1. **Repayments after the as-at date are replayed** onto the new schedule (repayment records unchanged).
- Principal difference posts DR/CR the product receivable (fallback 1101) vs **3004 Opening Balance Equity** (or 3002), module `loan_correction`. Interest needs no journal (interest is recognised when received).
- Each correction stores a snapshot (loan figures, schedule, repayment ids) so Undo / journal reversal restores exactly.

## Journal Entry — Client Sub-Ledger Rule (CRITICAL)

When a manual journal entry line has a `client_id` attached (`transaction_lines.client_id`), the system **must also update the corresponding sub-ledger record** for that client. GL posting alone is not sufficient — the member's individual account balance and transaction history must stay in sync.

### Rules by account type:

| GL Account Type | Sub-ledger to update | How to detect |
|---|---|---|
| Savings liability account | `savings_accounts.balance` + insert `savings_transactions` row | Account linked to a `SavingsProduct` via `savings_liability_account_id` |
| Loan receivable account | `loans.outstanding_principal` | Account linked to a loan product receivable |
| Fixed deposit account | `fixed_deposits` balance | Account linked to an FD product |
| Share capital account | `member_shares.amount_paid` + insert `share_transactions` row | Account code `3001` or share capital accounts |
| Membership fee income | `clients.membership_fee_paid` + `membership_fee_status` | Account code `4008` or membership fee income accounts |

### Implementation (TransactionController — automatic)
- After `AccountingService::post()`, `syncSubLedgersFromLines()` runs on the request lines (each line may include `client_id`, persisted on `transaction_lines`).
- **Savings**: account matches a `SavingsProduct.savings_liability_account_id`, or legacy codes `2001` / `2005` → first matching active `savings_accounts` row for that client; credit = deposit, debit = withdrawal; always creates `savings_transactions` with `transaction_id`.
- **Fixed deposits**: account matches `FixedDepositProduct.deposit_liability_account_id` → most recent **active** `fixed_deposits` for that client/product family; credit increases `principal`, debit decreases it (manual corrections; does not recalc maturity figures).
- **Loans**: **credit** to `LoanProduct.receivable_account_id` or legacy `1101`–`1103` → reduces `outstanding_principal` on the latest active/defaulted loan for that client (matching product when linked).
- **Shares**: account code `3001` → updates `member_shares` (unpaid/partial row) and `share_transactions` with `journal_transaction_id`.
- **Membership fee**: account code `4008` (credit) → updates `clients.membership_fee_paid` / `membership_fee_status`.
- **Reversal / delete** (`module = manual`): `reverseManualSubLedgers()` reverses savings rows by `transaction_id`, share rows by `journal_transaction_id`, loan principal, FD `principal`, and membership fee paid, using the original lines.

### Key principle:
> A journal line tagged to a client is a **claim** that the client's sub-ledger is affected. Never post to a member-linked GL account without also updating the member's individual balance and statement. The GL and sub-ledger must always agree.

---

## Journal reversal & delete — sub-ledgers (CRITICAL)

**Reversing or permanently deleting** a journal entry must unwind **both** the GL and any linked **member sub-ledgers** (savings statements, loan repayment records, FD side effects, etc.). Only adjusting `transaction_lines` is not enough.

`TransactionController::reverse()` and `destroy()` call `reverseModuleImpact()` which dispatches on `transactions.module`:

| Module | Handler | What gets reversed |
|--------|---------|-------------------|
| `savings` | `reverseSavingsImpact` | All `savings_transactions` with `transaction_id` = this journal; savings `balance` rolled back |
| `payroll` | `reversePayrollImpact` | Same as savings (salary deposits are linked to the payroll journal); **`payroll_runs`** set back to **`draft`** and `processed_at` / `processed_by` cleared |
| `loan` | `reverseLoanImpact` | **`reverseSavingsImpact`** first (covers fees deducted from savings when `transaction_id` is set on those rows). If description is **loan repayment**: `loan_repayments`, schedule lines, outstanding. If **loan disbursement**: schedules deleted, loan reset to **`pending`**, disbursement/maturity cleared, outstanding zeroed — **blocked** (reverse or destroy) if any repayments exist. |
| `member_share` | `reverseMemberShareImpact` | **`reverseSavingsImpact`**, then each **`share_transactions`** row with `journal_transaction_id` = this journal (payment / revaluation / liquidation unwound; row deleted). Revaluations store **`amount_paid_before`** on `share_transactions` for accurate rollback. |
| `fixed_deposit` | `reverseFixedDepositImpact` | Placement-from-savings / creation: restore savings, **soft-delete** FD if still active. Interest accrual / early-break interest reversal: adjust **`accrued_interest`** using FD payable **2003** lines. Maturity / early-break principal-to-savings: reverse linked **`savings_transactions`**, reopen FD (`active`, clear `closed_date`). |
| `groups` | `reverseGroupTransactionImpact` | `group_transactions` + any linked savings rows |
| `manual` | `reverseManualSubLedgers` | Auto-detected manual journal sub-ledgers (savings, shares, loans, FD principal, membership fee) |
| `client` | `reverseMembershipFeeImpact` | Membership fee paid on client (when description matches fee flow) |
| `loan_provision` | `reverseLoanProvisionImpact` | Marks the `loan_provisions` run `reversed` (GL unwound by the reversal entry) |
| `loan_correction` | `reverseLoanCorrectionImpact` | Restores the loan + schedule from the `loan_corrections.snapshot` (blocked if repayments were added/removed since, or a later correction exists) |

**Payroll journal** (`PayrollController::buildJournal`, shared by the pre-process preview and the posting): DR **5003** Staff Salaries / **5103** Agency Commissions (gross, by employee `pay_type`), DR **5104** NSSF Expense (10%), DR **5110** Local Travel (transport); CR **2011** NSSF Liability (5% + 10%), CR **2012** PAYE Liability, CR **4007** Other Income (lunch), CR Staff Savings (deposited into the savings account set in setting `payroll_staff_savings_account`, default `SA-SK00037` "Staff Saving Account" — one `savings_transactions` row per employee, linked via `transaction_id`), CR each employee's savings liability (net pay). Net = Gross − PAYE − NSSF 5% − Lunch − Staff Savings + Transport. PAYE/NSSF are editable per employee (blank = statutory calc).

**Payroll implementation rule:** `PayrollController::process` posts the GL journal **first**, then creates each salary **`savings_transactions`** row with **`transaction_id`** pointing at that journal so payroll reversals update **client savings statements and balances**.

**Loan fees from savings:** `LoanService` posts the fee journal then sets **`savings_transactions.transaction_id`** (and reference) on the withdrawal row so reversal unwinds the savings statement.

**Legacy rows** (e.g. savings/share/loan journals created before `transaction_id` / `journal_transaction_id` / `amount_paid_before` were populated) may not fully unwind automatically; fix data manually or post correcting entries.

---

## Modules & Routes

| Module | Route Prefix | Controller |
|--------|-------------|------------|
| Dashboard | `/dashboard` | `DashboardController` |
| Clients | `/clients` | `ClientController` |
| Chart of Accounts | `/accounts` | `AccountController` |
| Journal Entries | `/transactions` | `TransactionController` |
| Loan Products | `/loan-products` | `LoanProductController` |
| Loans | `/loans` | `LoanController` |
| Loan Provisions | `/loan-provisions` | `LoanProvisionController` |
| Savings Products | `/savings-products` | `SavingsProductController` |
| Savings Accounts | `/savings` | `SavingsAccountController` |
| FD Products | `/fd-products` | `FixedDepositProductController` |
| Fixed Deposits | `/fixed-deposits` | `FixedDepositController` |
| Reports | `/reports` | `ReportController` |
| Quick Teller | `/teller` | `TellerController` |
| Employees | `/employees` | `EmployeeController` |
| Payroll | `/payroll` | `PayrollController` |
| Users | `/users` | `UserController` |
| Branches | `/branches` | `BranchController` |
| Settings | `/settings` | `SettingsController` |
| Audit Logs | `/audit` | `AuditLogController` |

---

## Access Control
- Uses **Spatie Laravel Permission** (`spatie/laravel-permission ^6.24`)
- All protected routes use permission middleware
- Roles/permissions seeded via `database/seeders/RolesAndPermissionsSeeder.php`
- When adding new routes, add matching permissions in the seeder

---

## UI Conventions
- **Bootstrap 5.3.2** via CDN — no npm or Vite build steps required
- **Bootstrap Icons** for all icons
- **Tom Select 2.3.1** for searchable dropdowns
- Layout: sidebar + topbar, org name pulled from `SystemSetting`
- PDF generation: `barryvdh/laravel-dompdf ^2.2` (templates in `resources/views/pdf/`)

---

## Adding a New Module (checklist)
1. Create migration + model (`app/Models/`)
2. Create service in `app/Services/` for business logic
3. Create controller in `app/Http/Controllers/`
4. Add resource routes in `routes/web.php` with permission middleware
5. Create Blade views in `resources/views/{module}/`
6. Add permissions to `RolesAndPermissionsSeeder.php`
7. For financial operations: use `AccountingService` for all GL postings

---

## Database
- **Engine**: MySQL
- **Database**: `eltech_finance`
- **Host**: `127.0.0.1:3306`
- **25+ migrations** covering all modules
- Chart of accounts has **45 seeded accounts** across Assets, Liabilities, Equity, Income, Expenses

---

## Dependencies
- `laravel/framework ^9.19`
- `spatie/laravel-permission ^6.24` — RBAC
- `barryvdh/laravel-dompdf ^2.2` — PDF reports
- `guzzlehttp/guzzle ^7.2` — HTTP client
