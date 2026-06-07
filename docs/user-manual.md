# ElTech Finance — User Manual

**Version 1.0 | June 2026**

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Dashboard](#2-dashboard)
3. [Clients](#3-clients)
4. [Chart of Accounts](#4-chart-of-accounts)
5. [Journal Entries (Manual Transactions)](#5-journal-entries-manual-transactions)
6. [Quick Teller](#6-quick-teller)
7. [Loans](#7-loans)
8. [Savings Accounts](#8-savings-accounts)
9. [Fixed Deposits](#9-fixed-deposits)
10. [Groups](#10-groups)
11. [Member Shares](#11-member-shares)
12. [Payroll](#12-payroll)
13. [Reports](#13-reports)
14. [User Management](#14-user-management)
15. [System Settings](#15-system-settings)
16. [Audit Log](#16-audit-log)
17. [Client Portal](#17-client-portal)
18. [Group Portal](#18-group-portal)
19. [Roles & Permissions Reference](#19-roles--permissions-reference)

---

## 1. Getting Started

### 1.1 Accessing the System

Open your web browser and navigate to the system URL provided by your administrator. The login page will appear.

### 1.2 Logging In

1. Enter your **Email Address**
2. Enter your **Password**
3. Tick **Remember Me** if you are on a private computer (keep this unticked on shared computers)
4. Click **Sign In**

If your credentials are correct, the system will redirect you to your assigned portal (Staff Dashboard, Client Portal, or Group Portal depending on your role).

> **Note:** If you see the message *"Your account has been deactivated"*, contact your system administrator.

### 1.3 Logging Out

Click your name or the **Sign Out** button in the top-right corner of any page. Always log out when leaving your workstation.

### 1.4 Navigation

The system uses a **sidebar** on the left for navigation. The sidebar groups related modules together. Click any item to open that module. On mobile devices, tap the menu icon (≡) in the top bar to show the sidebar.

---

## 2. Dashboard

The Dashboard is the first screen you see after logging in. It gives a live summary of the organisation's financial position.

### What You Will See

| Card | What It Shows |
|------|--------------|
| Total Loans Disbursed | Cumulative principal given out |
| Total Savings | Sum of all savings account balances |
| Active Members | Number of clients with active accounts |
| Fixed Deposits | Total principal in active FDs |

Below the cards you will find **quick-access links** to the most frequently used actions and a **recent activity feed** showing the latest transactions posted.

---

## 3. Clients

Clients are the people and groups your organisation serves. Every loan, savings account, or fixed deposit must belong to a client.

### 3.1 Viewing All Clients

Go to **Clients** in the sidebar. You will see a table with:
- Client Number, Name, Type (Individual / Group), Phone, Email, Status

Use the **search bar** to find a client by name, phone, or client number. Use the **Status filter** to show only Active, Inactive, or Blacklisted clients.

### 3.2 Registering an Individual Client

1. Click **New Client** (top-right of the Clients page)
2. Select **Individual Member**
3. Complete the 5-step wizard:

**Step 1 — Personal Information**
- First Name, Middle Name, Last Name
- Gender, Date of Birth, Marital Status
- Nationality and National ID / Passport Number
- Profile Photo (optional, image files only)

**Step 2 — Contact Information**
- Primary Phone, Alternative Phone, Email
- District, Village/Parish, Physical Address

**Step 3 — Employment & Membership**
- Employment Status (Employed, Self-Employed, Business Owner, Farmer, Student, Unemployed)
- Purpose of Joining (Savings, Loan, Investment, Business Financing)
- Expected Monthly Savings amount
- Interested in Loans? (Yes / No)

**Step 4 — Next of Kin**
- Full Name, Relationship, Phone, Address of next of kin

**Step 5 — Preferences & Account Settings**
- Branch (if your organisation has branches)
- Account Status (defaults to Active)
- Date Joined
- Preferred Communication (SMS, Email, WhatsApp, Phone Call)

Click **Submit** on Step 5 to save the client.

### 3.3 Registering a Group Client

1. Click **New Client**, select **Group**
2. Choose the Group Type:
   - **Savings Group** — each member maintains an individual balance and earns interest
   - **Pooled Group** — all members contribute to a shared pool
3. Fill in Group Name, Phone, Email, Branch, Status, Joining Date, and Membership Fee
4. For Savings Groups: enter the **Monthly Interest Rate (%)**
5. For Pooled Groups: enter the **Expected Contribution** amount and **Contribution Cycle** (Weekly, Bi-weekly, Monthly, Quarterly)
6. Click **Save Group**

### 3.4 Viewing a Client Profile

Click the **view icon (eye)** next to any client. The profile page shows:
- All personal details
- Linked loans, savings accounts, and fixed deposits
- Membership fee status and payment history
- Member shares and their payment status

### 3.5 Editing a Client

Click the **edit icon (pencil)** on the client list or the **Edit** button on the profile page. Update the required fields and save.

### 3.6 Sending a Portal Invitation

Clients can be given their own online portal to view their accounts. To invite a client:
1. Open the client profile
2. Click the **envelope icon** (or **Invite to Portal** button)
3. Confirm the prompt — the system will send a login link to the client's email

> The client will receive an email with a link to set their password and access the Client Portal.

### 3.7 Membership Fees

If your organisation charges a membership fee:
1. Open the client profile
2. Find the **Membership Fee** section
3. See the required amount, amount already paid, and outstanding balance
4. Click **Record Payment** to record a new payment
5. Choose the payment source (a GL cash account or deduct from a savings account), enter the amount, date, and receipt number
6. Click **Save**

---

## 4. Chart of Accounts

The Chart of Accounts lists all General Ledger (GL) accounts used in the system's double-entry bookkeeping.

### 4.1 Viewing Accounts

Go to **Chart of Accounts** in the sidebar. Accounts are organised by type: Assets, Liabilities, Equity, Income, Expenses.

Each account shows: Account Code, Account Name, Type, and current Balance.

### 4.2 Adding a New Account

1. Click **New Account**
2. Enter the Account Code (must be unique), Account Name, Account Type, and Description
3. Click **Save**

> **Important:** Only add accounts you genuinely need. Do not duplicate existing accounts. Contact your accountant if unsure.

### 4.3 Editing an Account

Click **Edit** next to any account. You can change the name and description, but not the account code once created.

---

## 5. Journal Entries (Manual Transactions)

Manual journal entries let you post any financial transaction directly into the General Ledger using double-entry principles.

> **When to use this:** Use manual journals for corrections, adjustments, membership fee income, inter-account transfers, and any transaction not covered by a dedicated module (Loans, Savings, FD).

### 5.1 Posting a Journal Entry

1. Go to **Journal Entries** → **New Entry**
2. Fill in:
   - **Date** (cannot be a future date)
   - **Description** (what the transaction is for)
   - **Receipt / Reference** (unique reference number)
3. Add journal lines (minimum 2):
   - For each line, select an **Account**, optionally tag a **Client**, add a description, and enter either a **Debit** or **Credit** amount
   - Click **Add Line** for more rows
4. Watch the **Totals bar** at the bottom — the entry can only be posted when Total Debits = Total Credits (it shows a green "Balanced" indicator)
5. Click **Post Transaction**

> **Client tagging:** If you tag a client on a savings or loan account line, the system automatically updates that client's sub-ledger (their savings balance, loan balance, etc.). Always tag the client when the journal line affects an individual member's account.

### 5.2 Viewing Journal Entries

Go to **Journal Entries** (list view). Use the date range, reference search, and filters to find specific entries.

Click any entry to see the full details and all journal lines.

### 5.3 Reversing an Entry

Open the journal entry, then click **Reverse**. The system will post an equal and opposite entry and mark the original as reversed. This is the correct way to correct a posted entry — never delete entries unless absolutely necessary.

### 5.4 Deleting an Entry

Only the **Super Admin** can permanently delete a journal entry. Go to the entry detail page and click **Delete**. This action is irreversible.

---

## 6. Quick Teller

Quick Teller is a fast-access screen for cashiers to process deposits and withdrawals without navigating the full Savings module.

### 6.1 Using the Teller Screen

1. Go to **Quick Teller** in the sidebar
2. In the **left panel**, type the client's name, phone, or account number in the search box
3. Click on the matching result to load the account in the right panel

### 6.2 Posting a Deposit

1. Make sure the **Deposit** tab is selected in the right panel
2. Enter the **Amount**
3. Confirm the **Date** (defaults to today)
4. Select the **Payment Source** (which cash GL account the money is coming from)
5. Enter the **Receipt / Reference** number
6. Add a **Narration** if needed (defaults to "Cash deposit")
7. Click **Post Deposit**

### 6.3 Posting a Withdrawal

1. Select the **Withdraw** tab
2. Enter the **Amount**
3. Confirm the **Date**
4. Enter the **Withdrawal Fee** (set to 0 to waive)
5. Select the **Payment Source** (the cash GL account)
6. Enter the **Receipt / Reference** number
7. Click **Post Withdrawal**

---

## 7. Loans

### 7.1 Loan Products

Before creating loans, the organisation must have loan products configured. Go to **Loan Products** to see available products. Each product defines the default interest rate, interest method (Flat or Reducing Balance), and loan term.

### 7.2 Creating a Loan Application

1. Go to **Loans** → **New Loan**
2. Complete the 3-step wizard:

**Step 1 — Client & Product**
- Select the **Client** (type to search)
- Select the **Loan Product**
- Enter the **Principal Amount**
- Add **Application Notes** (optional)

**Step 2 — Loan Terms**
- Leave these fields blank to use the product defaults, or override:
  - Interest Rate (%)
  - Interest Method (Flat or Reducing Balance)
  - Term in Months

**Step 3 — Guarantors** (Optional)
- Click **Add Guarantor** for each guarantor
- Enter their Full Name, Phone, ID Number, Relationship, Employer, Monthly Income, and Address
- Click **Submit Application**

The loan is created with **Pending** status.

### 7.3 Disbursing a Loan

A loan must be disbursed before repayments can be recorded.

1. Open the loan detail page
2. Click **Disburse Loan**
3. Review the **Fees & Deductions** table:
   - **Application Fee** — flat amount deducted once
   - **Management Fee** — percentage of principal
   - **Insurance Fee** — percentage of principal
   - For each fee, choose whether to deduct it from the **loan amount** or from the **client's savings account**
4. Review the **Fee Summary** (shows net cash the client will actually receive)
5. Set the **Disbursement Date**
6. If any fees are deducted from savings, select the **Savings Account**
7. Click **Disburse**

The loan status changes to **Active** and the repayment schedule is automatically generated.

### 7.4 Recording a Loan Repayment

1. Open the loan (or use the **Repay** action from the loans list)
2. Click **Record Repayment**
3. Enter:
   - **Amount** paid
   - **Payment Date**
   - **Payment Method** (Cash, Bank Transfer, Mobile Money, etc.)
   - **Receipt / Reference**
4. Click **Save**

Repayments are allocated automatically in this order: **Penalty → Interest → Principal**.

### 7.5 Loan Statuses

| Status | Meaning |
|--------|---------|
| Pending | Application submitted, not yet disbursed |
| Active | Disbursed and repayment is ongoing |
| Closed | Fully repaid |
| Defaulted | Overdue beyond the allowed grace period |

### 7.6 Loan Reports & Statements

From the loan detail page, use the **Export** dropdown to:
- View the **Loan Statement** (on screen)
- Download the **Loan Statement (PDF)**
- Download the **Repayment Schedule (PDF)**

---

## 8. Savings Accounts

### 8.1 Savings Products

Savings products define the interest rate, minimum balance, and GL accounts. Go to **Savings Products** to see configured products.

### 8.2 Opening a Savings Account

1. Go to **Savings** → **Open Account**
2. Select the **Client** (type to search)
3. Select the **Savings Product**
4. Set the **Opening Date**
5. Click **Open Account**

### 8.3 Depositing into a Savings Account

1. Open the savings account (from **Savings** list, click view)
2. Click **Deposit**
3. Enter:
   - **Amount**
   - **Date**
   - **Payment Source** (cash GL account)
   - **Receipt / Reference**
   - **Narration** (optional)
4. Click **Post Deposit**

Alternatively, use the **Quick Teller** screen for faster processing.

### 8.4 Withdrawing from a Savings Account

1. Open the savings account
2. Click **Withdraw**
3. Enter Amount, Date, Fee (if any), Payment Source, Reference, and Narration
4. Click **Post Withdrawal**

> The system will block withdrawals that would take the balance below the **minimum balance** set on the product.

### 8.5 Transferring Between Accounts

1. Open the source savings account
2. Click **Transfer**
3. Select the **Destination Account** and enter Amount, Date, and Reference
4. Click **Post Transfer**

### 8.6 Posting Interest

**For a single account:**
1. Open the account → click **Post Interest**
2. Confirm the posting date → click **Post**

**Bulk (all accounts at once):**
1. On the Savings list page, click **Post Interest** (top right)
2. Select the **Posting Date**
3. Click **Post Interest to All Eligible Accounts**

Interest is calculated based on the daily balance and the product's annual interest rate.

### 8.7 Viewing a Statement

Open any savings account and scroll down to see the full transaction history. To download, click **Export Statement (PDF)**.

---

## 9. Fixed Deposits

### 9.1 Fixed Deposit Products

Go to **FD Products** to see available products. Each product defines the default interest rate, term, and GL accounts.

### 9.2 Creating a Fixed Deposit

1. Go to **Fixed Deposits** → **New Fixed Deposit**
2. Select the **Client**
3. Select the **FD Product**
4. Enter **Principal Amount**
5. Override **Interest Rate (%)** and **Term (months)** if different from product defaults
6. Set the **Start Date**
7. The system shows a live **Maturity Calculation** (projected interest, maturity amount, and maturity date)
8. Select **Funding Source**:
   - **Cash** — select the cash GL account
   - **Deduct from Savings** — select the client's savings account (must have sufficient balance)
9. Enter the **Receipt / Reference**
10. Click **Create Fixed Deposit**

**Interest formula:** Simple Interest = Principal × Rate × (Term ÷ 12)

### 9.3 Processing Maturity Payout

When a fixed deposit reaches its maturity date:

1. Open the fixed deposit (a warning banner will appear if it has matured)
2. Click **Process Maturity Payout**
3. Choose where to pay the maturity amount (savings account or cash)
4. Confirm the payout date and reference
5. Click **Process**

The FD status changes to **Matured/Closed** and the maturity amount is credited to the chosen destination.

### 9.4 Breaking a Fixed Deposit Early

If a client needs funds before the maturity date:

1. Open the fixed deposit → click **Break FD**
2. Read the warning: only the **principal** is returned; interest is **forfeited**
3. Choose the payout destination (savings or cash)
4. Confirm the break date and reference
5. Click **Break Deposit**

### 9.5 FD Certificate

Open any fixed deposit and click **FD Certificate** to download a PDF certificate for the client.

---

## 10. Groups

Groups are clients that operate as a collective. The system supports two types:
- **Savings Groups** — each member has an individual balance and earns interest
- **Pooled Groups** — all members contribute to a shared fund

### 10.1 Viewing Groups

Go to **Groups** in the sidebar. The list shows each group's name, number, member count, total balance, interest rate, and status.

### 10.2 Creating a Group

Groups are created through the **Clients** module (see Section 3.3). Once created, the group appears in both the Clients list and the Groups list.

### 10.3 Managing Group Members

1. Open the group → scroll to the **Members** section
2. Click **Add Member** to add a new member:
   - Enter Full Name, Phone, National ID
   - Tick **Is Leader** to designate them as group leader
   - Enter a **Portal Email** if they will use the Group Member Portal (default password: `Welcome@123`)
3. Click **Save**

To edit a member: click the **edit icon** on their row. You can update their details, change their password, or promote/demote them as leader.

### 10.4 Recording Transactions (Savings Group)

From the group detail page:

- **Deposit** — record a deposit for one or more members
- **Withdraw** — record a withdrawal for a member
- **Post Interest** — calculate and post monthly interest to all members

### 10.5 Recording Transactions (Pooled Group)

- **Record Contribution** — mark a member's contribution for the current cycle
- **Pool Withdrawal** — record a withdrawal from the shared pool

### 10.6 Transferring a Member Balance to Savings

If a member wants their group balance moved to a personal savings account:
1. Click the **Transfer to Savings** icon next to the member
2. Select the **Target Savings Account**
3. Enter the **Amount** and **Date**
4. Click **Transfer**

### 10.7 Sending a Portal Reset Link

If a group member forgets their portal password:
1. Find them in the Members table
2. Click the **Send Reset Link** button (envelope icon)
3. The system sends a password reset email to their registered portal email

---

## 11. Member Shares

This module tracks member share subscriptions — how many shares each member owns and how much they have paid.

### 11.1 Viewing All Shares

Go to **Shares** in the sidebar to see a summary of all members' share positions.

### 11.2 Adding Shares to a Member

1. Open the **Client Profile**
2. Scroll to the **Member Shares** section
3. Click **Add Share**
4. Enter the **Share Value** and any notes
5. Click **Save**

The share is recorded with **Unpaid** status.

### 11.3 Recording a Share Payment

1. In the Member Shares section, click **Pay** next to the share
2. Choose the **Payment Source** (GL cash account or deduct from savings)
3. Enter the **Amount** (can be partial), **Date**, and **Receipt**
4. Click **Save**

### 11.4 Revaluing Shares

If the organisation changes the share value:
1. Click **Revalue** next to the share
2. Enter the **New Share Value** and **Effective Date**
3. Click **Save**

To revalue all shares at once, use **Shares → Revalue All** with the new value.

### 11.5 Liquidating Shares

When a member exits and their shares are bought back:
1. Click **Liquidate** next to the share
2. Choose payout method: **Cash** or **Credit to Savings**
3. Enter the **Liquidation Date** and notes
4. Click **Liquidate**

> This action cannot be undone. The share is marked as **Liquidated**.

---

## 12. Payroll

### 12.1 Creating a Payroll Run

1. Go to **Payroll** → **New Payroll Run**
2. Select **Month** and **Year**
3. Add a **Description** (optional, e.g. "June 2026 Salaries")
4. Add employees:
   - Click **Add All Active** to load all active employees automatically, or
   - Click **Add Employee Row** to add one at a time
5. For each employee row:
   - Select the **Employee** from the dropdown (the Basic Salary auto-fills)
   - Adjust **Allowances** (add-ons like housing, transport)
   - Adjust **Deductions** (e.g. loans, NSSF)
   - The **Net Salary** calculates automatically: Basic + Allowances − Deductions
6. Click **Save Payroll Run** (saves as **Draft**)

### 12.2 Processing a Payroll Run

1. Open a **Draft** payroll run
2. Review all employee figures
3. Click **Process Payroll**
4. The system posts the salary GL entries and credits each employee's savings account (if linked)

Once processed, the run is locked and cannot be edited. Status changes to **Processed**.

### 12.3 Reversing a Payroll Run

If a payroll was processed in error:
1. Open the processed payroll run
2. Click **Reverse** (available to authorised users)
3. The system reverses all GL entries and savings credits, and the run returns to **Draft**

---

## 13. Reports

Go to **Reports** in the sidebar. Click any report card to open it.

### Available Reports

| Report | What It Shows | Filters Available |
|--------|--------------|-------------------|
| **Trial Balance** | Debit and credit totals for every GL account | As-of date |
| **Income Statement** | Revenue vs expenses (profit/loss) | Date range |
| **Balance Sheet** | Assets, Liabilities, and Equity snapshot | As-of date |
| **General Ledger** | Every transaction for a selected account | Account, date range |
| **Loan Portfolio** | All loans with status, outstanding principal | Status, date range |
| **Loan Aging** | Overdue loans grouped by how long overdue | Date |
| **Repayment Schedule** | Full schedule for a selected loan | Loan selection |
| **Interest Income** | Interest earned on loans and savings | Date range |
| **Savings Balances** | All active savings accounts and their balances | As-of date |
| **FD Maturity** | Fixed deposits maturing within a period | Date range |
| **Member Summary** | Per-member summary of savings, loans, FD, shares | — |

Most reports can be printed or exported to PDF using the browser's print function or the **Download PDF** button where available.

---

## 14. User Management

Only users with the **Manage Users** permission can access this module.

### 14.1 Viewing Users

Go to **Users** in the sidebar. The table shows all system users with their name, email, role, branch, and status.

### 14.2 Creating a New User

1. Click **New User**
2. Enter Name, Email, Phone (optional)
3. Assign a **Role** (see Section 19 for role descriptions)
4. Assign a **Branch** (optional)
5. Set a temporary **Password**
6. Click **Save**

Share the email and temporary password with the user. They should change their password on first login.

### 14.3 Editing a User

Click **Edit** next to a user to change their name, phone, role, or branch.

### 14.4 Activating / Deactivating a User

Click the **toggle icon** (play/pause) next to a user to activate or deactivate their account. A deactivated user cannot log in.

---

## 15. System Settings

Only users with the **Manage Settings** permission can access this. Go to **Settings** in the sidebar.

### Key Settings

**Organisation**
- Organisation Name — displayed on all reports, statements, and the login page
- Organisation Logo — upload a PNG, JPG, or SVG (max 2 MB)
- Address, phone, email — appear on PDF documents

**Financial**
- Default currency symbol
- Financial year start month
- Default penalty rates

**Modules**
- Enable/disable Membership Fees, Shares, Fixed Deposits, Groups, Payroll

**Email/Mail**
- SMTP settings for sending portal invitations and password resets

### Saving Settings

After making changes, scroll to the top and click **Save Settings**.

### Data Reconciliation

If you suspect account balances are out of sync, use the **Run Reconciliation** button at the bottom of the Settings page. This recalculates all denormalised balances and fixes any discrepancies.

> Only run reconciliation when advised by your system administrator.

---

## 16. Audit Log

Go to **Audit Log** in the sidebar. This page records every significant action taken in the system: who did it, what they did, and when.

Use the date filter and search to look up specific events. This log cannot be edited or deleted.

---

## 17. Client Portal

The Client Portal is a separate login area where individual clients can view their own accounts — without accessing any administrative features.

### What Clients Can See

- **Dashboard** — summary of all their savings balances, active loans, and FDs
- **Accounts** — list of all linked savings accounts, loans, and fixed deposits
- **Activity** — chronological feed of all recent transactions
- **Savings Statements** — printable PDF per savings account
- **Loan Statements** — loan details, repayment history, and schedule PDF
- **FD Statements** — fixed deposit details and certificate PDF

### How a Client Accesses the Portal

1. The staff must send an invitation (see Section 3.6)
2. The client receives an email with a link
3. They click the link, set a password, and log in at the same system URL
4. After login, they are taken directly to the Client Portal dashboard

### Switching Between Multiple Accounts

If a client is linked to more than one client profile (e.g. a personal account and a business account), a **Switch Account** option appears on their portal allowing them to toggle between profiles.

---

## 18. Group Portal

The Group Portal has two access levels: **Group Leader** and **Group Member**.

### 18.1 Group Leader Portal

Group leaders see a management dashboard for their group.

**Dashboard shows:**
- Pool Balance or Total Balance
- Active member count
- Deposits this month
- Date of last deposit
- Monthly deposit trend chart (last 6 months)
- Top Savers (top 3 members by balance)
- Lowest Balances (bottom 3 members)
- Watch List (members with zero balance or 60+ days inactive)

**All Members page:**
- Full member list with balances and last deposit dates

**Member Statement:**
- Full transaction history for any member (click their name)

### 18.2 Group Member Portal

Group members see only their own position within the group.

**Dashboard shows:**
- Their individual savings balance
- Group name and monthly interest rate
- Full transaction history (deposits, withdrawals, interest credited)

### 18.3 How Group Members Access the Portal

1. When adding a member (Section 10.3), enter their **Portal Email**
2. Their default password is: **Welcome@123**
3. The member logs in at the system URL with their email and that password
4. They should change their password immediately

To reset a forgotten password, use the **Send Reset Link** button in the group management screen (Section 10.7).

---

## 19. Roles & Permissions Reference

The system uses role-based access control. Each user is assigned one role.

| Role | Access Level |
|------|-------------|
| **Super Admin** | Full access to everything including deleting transactions, managing roles, and system settings |
| **Admin** | Full operational access; cannot delete journal entries or manage system roles |
| **Cashier** | Can process deposits, withdrawals, repayments, and use Quick Teller; cannot access reports, settings, or user management |
| **Staff** | Read-only access to most modules; cannot post transactions |
| **Client** | Access to Client Portal only — can view their own accounts |
| **Group Leader** | Access to Group Leader Portal — can view their group's dashboard and all member statements |
| **Group Member** | Access to Group Member Portal — can view their own balance and statement only |

### Common Permission Descriptions

| Permission | What It Allows |
|------------|---------------|
| view clients | See the clients list and profiles |
| edit clients | Edit client details and send portal invitations |
| create loans | Submit loan applications and add guarantors |
| disburse loans | Disburse approved loan applications |
| repay loans | Record loan repayments |
| create savings | Open savings accounts |
| deposit savings | Post deposits and interest |
| withdraw savings | Post withdrawals |
| create fixed-deposits | Create new FDs |
| mature fixed-deposits | Process maturity payouts and early breaks |
| create transactions | Post manual journal entries |
| reverse transactions | Reverse posted journal entries |
| view reports | Access the reports module |
| manage users | Create and edit system users |
| manage settings | Access and change system settings |
| manage backup | Create and download database backups |

---

## Appendix A — Common Workflows

### Onboarding a New Member (Full Workflow)

1. Register client → **Clients → New Client** (Section 3.2)
2. Collect membership fee → **Client Profile → Record Payment** (Section 3.7)
3. Open savings account → **Savings → Open Account** (Section 8.2)
4. Make opening deposit → **Savings Account → Deposit** (Section 8.3)
5. If taking a loan: Create loan application → Disburse (Sections 7.2–7.3)
6. Invite client to portal → **Client Profile → envelope icon** (Section 3.6)

---

### Monthly End-of-Month Routine

1. **Post savings interest** — Savings → Post Interest (Bulk) (Section 8.6)
2. **Review overdue loans** — Reports → Loan Aging (Section 13)
3. **Run payroll** (if applicable) — Payroll → New Payroll Run → Process (Section 12)
4. **Generate financial reports** — Reports → Income Statement, Balance Sheet (Section 13)
5. **Close financial period** — Settings → Financial Periods → Close Month

---

### Correcting a Posted Transaction

- **Small error (wrong amount/account):** Post a reversing entry via **Journal Entries → Reverse** (Section 5.3), then post the correct entry
- **Savings/Loan transaction posted in error:** Use the specific module's reversal option (e.g. reverse the repayment from the loan detail page)
- **Complete deletion:** Only the Super Admin can delete; use only as a last resort

---

## Appendix B — Glossary

| Term | Meaning |
|------|---------|
| GL | General Ledger — the master record of all financial transactions |
| Double-entry | Every transaction has equal debits and credits |
| Principal | The original loan or deposit amount (excluding interest) |
| Outstanding Principal | Remaining loan balance that hasn't been repaid yet |
| Flat Interest | Interest calculated on the original principal for the full term |
| Reducing Balance | Interest calculated on the remaining outstanding principal each period |
| Amortisation | The process of gradually paying off a loan through scheduled payments |
| FD | Fixed Deposit — a savings product with a fixed term and rate |
| Accrued Interest | Interest that has been earned but not yet collected or paid |
| Sub-ledger | Individual member account records that mirror the GL balances |
| SACCO | Savings and Credit Cooperative Organisation |
| MFI | Micro Finance Institution |

---

*For technical support, contact your system administrator.*

*ElTech Finance — Powered by ElTech Systems*
