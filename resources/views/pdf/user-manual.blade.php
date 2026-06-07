<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1a1a1a; background: #fff; line-height: 1.55; }

    /* ── Cover ── */
    .cover { text-align: center; padding: 80px 40px 60px; border-bottom: 4px solid #0f2444; page-break-after: always; }
    .cover-logo { font-size: 38px; font-weight: bold; color: #0f2444; letter-spacing: -1px; }
    .cover-logo span { color: #2563eb; }
    .cover-subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }
    .cover-title { font-size: 26px; font-weight: bold; color: #0f2444; margin: 48px 0 12px; }
    .cover-version { font-size: 11px; color: #9ca3af; margin-top: 8px; }
    .cover-band { background: #0f2444; color: #fff; padding: 14px 0; margin-top: 60px; font-size: 11px; }

    /* ── TOC ── */
    .toc-page { page-break-after: always; padding: 32px 40px; }
    .toc-title { font-size: 18px; font-weight: bold; color: #0f2444; border-bottom: 2px solid #0f2444; padding-bottom: 8px; margin-bottom: 20px; }
    .toc-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #d1d5db; font-size: 10.5px; }
    .toc-item.toc-sub { padding-left: 18px; color: #374151; font-size: 10px; }
    .toc-num { color: #9ca3af; min-width: 24px; }
    .toc-pg { color: #6b7280; }

    /* ── Page structure ── */
    .page { padding: 28px 40px; }
    .section-break { page-break-before: always; }

    /* ── Headers ── */
    .section-header { background: #0f2444; color: #fff; padding: 10px 14px; margin-bottom: 16px; border-radius: 4px; }
    .section-header h1 { font-size: 15px; font-weight: bold; }
    .section-header .sec-num { font-size: 10px; opacity: 0.65; margin-bottom: 1px; }
    h2 { font-size: 12.5px; font-weight: bold; color: #0f2444; margin: 20px 0 8px; border-left: 3px solid #2563eb; padding-left: 8px; }
    h3 { font-size: 11px; font-weight: bold; color: #374151; margin: 14px 0 5px; }

    /* ── Body text ── */
    p { margin-bottom: 8px; }
    ul, ol { margin: 6px 0 10px 20px; }
    li { margin-bottom: 3px; }
    strong { font-weight: bold; }

    /* ── Tables ── */
    table { width: 100%; border-collapse: collapse; margin: 10px 0 14px; font-size: 10px; }
    th { background: #f3f4f6; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; padding: 6px 8px; text-align: left; border: 1px solid #e5e7eb; }
    td { padding: 6px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
    tr:nth-child(even) td { background: #f9fafb; }

    /* ── Callouts ── */
    .note { background: #eff6ff; border-left: 3px solid #2563eb; padding: 8px 12px; margin: 10px 0; font-size: 10px; border-radius: 0 4px 4px 0; }
    .warning { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 8px 12px; margin: 10px 0; font-size: 10px; border-radius: 0 4px 4px 0; }
    .danger { background: #fef2f2; border-left: 3px solid #ef4444; padding: 8px 12px; margin: 10px 0; font-size: 10px; border-radius: 0 4px 4px 0; }

    /* ── Steps ── */
    .steps { margin: 8px 0 12px; }
    .step { display: flex; gap: 10px; margin-bottom: 6px; align-items: flex-start; }
    .step-num { background: #0f2444; color: #fff; font-size: 9px; font-weight: bold; min-width: 18px; height: 18px; border-radius: 50%; text-align: center; line-height: 18px; flex-shrink: 0; margin-top: 1px; }
    .step-text { flex: 1; }

    /* ── Footer ── */
    .page-footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e5e7eb; padding: 5px 40px; font-size: 9px; color: #9ca3af; display: flex; justify-content: space-between; background: #fff; }
    .page-footer .footer-brand { color: #0f2444; font-weight: bold; }

    /* ── Divider ── */
    hr { border: none; border-top: 1px solid #e5e7eb; margin: 16px 0; }
    .spacer { height: 10px; }
</style>
</head>
<body>

{{-- ═══════════════════════════════ COVER ═══════════════════════════════ --}}
<div class="cover">
    <div class="cover-logo">ElTech <span>System</span></div>
    <div class="cover-subtitle">Financial Management System</div>
    <div style="margin-top:48px">
        <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em">Complete Guide</div>
        <div class="cover-title">User Manual</div>
        <div class="cover-version">Version 1.0 &nbsp;|&nbsp; June 2026</div>
    </div>
    <div style="margin-top:48px;font-size:10.5px;color:#6b7280">
        Prepared for: <strong style="color:#0f2444">{{ $orgName }}</strong><br>
        <span style="font-size:9.5px">This document is confidential and intended for authorised staff only.</span>
    </div>
    <div class="cover-band">ElTech System &nbsp;—&nbsp; Powered by ElTech System</div>
</div>

{{-- ════════════════════════════════ TOC ════════════════════════════════ --}}
<div class="toc-page">
    <div class="toc-title">Table of Contents</div>
    <div class="toc-item"><span><span class="toc-num">1.</span> Getting Started</span><span class="toc-pg">3</span></div>
    <div class="toc-item toc-sub"><span>1.1 Accessing the System &nbsp;·&nbsp; 1.2 Logging In &nbsp;·&nbsp; 1.3 Logging Out &nbsp;·&nbsp; 1.4 Navigation</span></div>
    <div class="toc-item"><span><span class="toc-num">2.</span> Dashboard</span><span class="toc-pg">3</span></div>
    <div class="toc-item"><span><span class="toc-num">3.</span> Clients</span><span class="toc-pg">4</span></div>
    <div class="toc-item toc-sub"><span>3.1 Viewing &nbsp;·&nbsp; 3.2 Registering Individual &nbsp;·&nbsp; 3.3 Registering Group &nbsp;·&nbsp; 3.4–3.7 Profile, Edit, Portal, Membership Fees</span></div>
    <div class="toc-item"><span><span class="toc-num">4.</span> Chart of Accounts</span><span class="toc-pg">6</span></div>
    <div class="toc-item"><span><span class="toc-num">5.</span> Journal Entries (Manual Transactions)</span><span class="toc-pg">6</span></div>
    <div class="toc-item"><span><span class="toc-num">6.</span> Loans</span><span class="toc-pg">7</span></div>
    <div class="toc-item toc-sub"><span>6.1 Loan Products &nbsp;·&nbsp; 6.2 Application &nbsp;·&nbsp; 6.3 Disbursement &nbsp;·&nbsp; 6.4 Repayments &nbsp;·&nbsp; 6.5 Statuses &nbsp;·&nbsp; 6.6 Statements</span></div>
    <div class="toc-item"><span><span class="toc-num">7.</span> Savings Accounts</span><span class="toc-pg">9</span></div>
    <div class="toc-item toc-sub"><span>7.1 Products &nbsp;·&nbsp; 7.2 Opening &nbsp;·&nbsp; 7.3 Deposits &nbsp;·&nbsp; 7.4 Withdrawals &nbsp;·&nbsp; 7.5 Transfers &nbsp;·&nbsp; 7.6 Interest &nbsp;·&nbsp; 7.7 Statements</span></div>
    <div class="toc-item"><span><span class="toc-num">8.</span> Fixed Deposits</span><span class="toc-pg">10</span></div>
    <div class="toc-item toc-sub"><span>8.1 Products &nbsp;·&nbsp; 8.2 Creating &nbsp;·&nbsp; 8.3 Maturity Payout &nbsp;·&nbsp; 8.4 Early Break &nbsp;·&nbsp; 8.5 Certificate</span></div>
    <div class="toc-item"><span><span class="toc-num">9.</span> Groups</span><span class="toc-pg">11</span></div>
    <div class="toc-item"><span><span class="toc-num">10.</span> Member Shares</span><span class="toc-pg">12</span></div>
    <div class="toc-item"><span><span class="toc-num">11.</span> Payroll</span><span class="toc-pg">13</span></div>
    <div class="toc-item"><span><span class="toc-num">12.</span> Reports</span><span class="toc-pg">14</span></div>
    <div class="toc-item"><span><span class="toc-num">13.</span> User Management</span><span class="toc-pg">15</span></div>
    <div class="toc-item"><span><span class="toc-num">14.</span> System Settings</span><span class="toc-pg">15</span></div>
    <div class="toc-item"><span><span class="toc-num">15.</span> Audit Log</span><span class="toc-pg">16</span></div>
    <div class="toc-item"><span><span class="toc-num">16.</span> Client Portal</span><span class="toc-pg">16</span></div>
    <div class="toc-item"><span><span class="toc-num">17.</span> Group Portal</span><span class="toc-pg">17</span></div>
    <div class="toc-item"><span><span class="toc-num">18.</span> Roles &amp; Permissions Reference</span><span class="toc-pg">18</span></div>
    <div class="toc-item"><span><span class="toc-num"></span> Appendix A — Common Workflows</span><span class="toc-pg">20</span></div>
    <div class="toc-item"><span><span class="toc-num"></span> Appendix B — Glossary</span><span class="toc-pg">21</span></div>
</div>

{{-- ═══════════════════ SECTION 1 — GETTING STARTED ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 1</div><h1>Getting Started</h1></div>

    <h2>1.1 Accessing the System</h2>
    <p>Open your web browser and navigate to the system URL provided by your administrator. The ElTech System login page will appear with the organisation's name at the top.</p>

    <h2>1.2 Logging In</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Enter your <strong>Email Address</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Enter your <strong>Password</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Tick <strong>Remember Me</strong> only if you are on a private computer</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">Click <strong>Sign In</strong></div></div>
    </div>
    <p>If correct, the system redirects you to your assigned portal based on your role.</p>
    <div class="note"><strong>Note:</strong> If you see "Your account has been deactivated", contact your system administrator.</div>

    <h2>1.3 Logging Out</h2>
    <p>Click <strong>Sign Out</strong> in the top-right corner. Always log out when leaving your workstation, especially on shared computers.</p>

    <h2>1.4 Navigation</h2>
    <p>The <strong>sidebar</strong> on the left organises all modules. Click any item to open it. On mobile devices, tap the menu icon (≡) in the top bar to reveal the sidebar.</p>

    <hr>

    <div class="section-header" style="margin-top:24px"><div class="sec-num">Section 2</div><h1>Dashboard</h1></div>
    <p>The Dashboard is the first screen after login. It shows a live financial summary of the organisation.</p>

    <table>
        <thead><tr><th>Card</th><th>What It Shows</th></tr></thead>
        <tbody>
            <tr><td>Total Loans Disbursed</td><td>Cumulative principal disbursed to all clients</td></tr>
            <tr><td>Total Savings</td><td>Sum of all savings account balances</td></tr>
            <tr><td>Active Members</td><td>Number of clients with active accounts</td></tr>
            <tr><td>Fixed Deposits</td><td>Total principal held in active fixed deposits</td></tr>
        </tbody>
    </table>
    <p>Below the cards you will find <strong>quick-access links</strong> to common actions and a <strong>recent activity feed</strong> showing the latest transactions.</p>
</div>

{{-- ═══════════════════ SECTION 3 — CLIENTS ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 3</div><h1>Clients</h1></div>
    <p>Clients are the people and groups your organisation serves. Every loan, savings account, or fixed deposit must belong to a client.</p>

    <h2>3.1 Viewing All Clients</h2>
    <p>Go to <strong>Clients</strong> in the sidebar. The table shows Client Number, Name, Type (Individual / Group), Phone, Email, and Status. Use the <strong>search bar</strong> to find a client by name, phone, or number. Use the <strong>Status filter</strong> to show Active, Inactive, or Blacklisted clients.</p>

    <h2>3.2 Registering an Individual Client</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Click <strong>New Client</strong> → select <strong>Individual Member</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Complete the 5-step wizard (use Next / Previous to move between steps)</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Click <strong>Submit</strong> on Step 5 to save</div></div>
    </div>

    <table>
        <thead><tr><th>Step</th><th>Fields</th></tr></thead>
        <tbody>
            <tr><td><strong>1 — Personal Info</strong></td><td>First Name, Middle Name, Last Name, Gender, Date of Birth, Marital Status, Nationality, National ID / Passport No., Profile Photo</td></tr>
            <tr><td><strong>2 — Contact</strong></td><td>Primary Phone, Alternative Phone, Email, District, Village/Parish, Physical Address</td></tr>
            <tr><td><strong>3 — Employment</strong></td><td>Employment Status, Purpose of Joining, Expected Monthly Savings, Interested in Loans?</td></tr>
            <tr><td><strong>4 — Next of Kin</strong></td><td>Full Name, Relationship, Phone, Address</td></tr>
            <tr><td><strong>5 — Preferences</strong></td><td>Branch, Account Status (default: Active), Date Joined, Preferred Communication</td></tr>
        </tbody>
    </table>

    <h2>3.3 Registering a Group Client</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Click <strong>New Client</strong> → select <strong>Group</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Choose the <strong>Group Type</strong>: Savings Group or Pooled Group</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Fill in Group Name, Phone, Email, Branch, Status, Joining Date, Membership Fee</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">For <strong>Savings Groups</strong>: enter the Monthly Interest Rate (%)</div></div>
        <div class="step"><div class="step-num">5</div><div class="step-text">For <strong>Pooled Groups</strong>: enter Expected Contribution and Contribution Cycle (Weekly / Monthly / Quarterly)</div></div>
        <div class="step"><div class="step-num">6</div><div class="step-text">Click <strong>Save Group</strong></div></div>
    </div>

    <h2>3.4 Viewing a Client Profile</h2>
    <p>Click the <strong>eye icon</strong> next to any client. The profile shows personal details, linked loans, savings accounts, fixed deposits, membership fee status, and member shares.</p>

    <h2>3.5 Editing a Client</h2>
    <p>Click the <strong>pencil icon</strong> on the list or the <strong>Edit</strong> button on the profile. Update the required fields and save.</p>

    <h2>3.6 Sending a Portal Invitation</h2>
    <p>Clients can log into their own online portal to view accounts. To invite a client:</p>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the client profile</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Click the <strong>envelope icon</strong> (or Invite to Portal)</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Confirm the prompt — the system emails a login link to the client</div></div>
    </div>
    <div class="note"><strong>Note:</strong> The client receives an email to set their password and access the Client Portal.</div>

    <h2>3.7 Membership Fees</h2>
    <p>If your organisation charges a membership fee, open the client profile, scroll to <strong>Membership Fee</strong>, and click <strong>Record Payment</strong>. Choose the payment source (cash GL account or savings account), enter the amount, date, and receipt number, then save. The system tracks required, paid, and outstanding amounts.</p>
</div>

{{-- ═══════════════════ SECTIONS 4 & 5 ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 4</div><h1>Chart of Accounts</h1></div>
    <p>The Chart of Accounts lists all General Ledger (GL) accounts used in double-entry bookkeeping. It is pre-seeded with 45+ accounts covering Assets, Liabilities, Equity, Income, and Expenses.</p>

    <h2>4.1 Viewing Accounts</h2>
    <p>Go to <strong>Chart of Accounts</strong>. Accounts are listed by code and type with their current balance.</p>

    <h2>4.2 Adding a New Account</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Click <strong>New Account</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Enter the Account Code (unique), Account Name, Type, and Description</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Click <strong>Save</strong></div></div>
    </div>
    <div class="warning"><strong>Important:</strong> Only add accounts you genuinely need. Do not duplicate existing accounts. Consult your accountant if unsure.</div>

    <h2>4.3 Editing an Account</h2>
    <p>Click <strong>Edit</strong> next to any account. You can update the name and description, but not the account code once it has been used in transactions.</p>

    <hr>

    <div class="section-header" style="margin-top:24px"><div class="sec-num">Section 5</div><h1>Journal Entries (Manual Transactions)</h1></div>
    <p>Manual journal entries let you post any financial transaction directly into the General Ledger using double-entry principles.</p>
    <div class="note"><strong>When to use this:</strong> Corrections, adjustments, membership fee income, inter-account transfers, and any transaction not covered by a dedicated module.</div>

    <h2>5.1 Posting a Journal Entry</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Go to <strong>Journal Entries</strong> → <strong>New Entry</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Fill in: <strong>Date</strong> (cannot be future), <strong>Description</strong>, and <strong>Receipt / Reference</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Add journal lines (minimum 2): for each line, select an Account, optionally tag a Client, add a description, and enter a Debit or Credit amount</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">Watch the <strong>Totals bar</strong> — a green "Balanced" indicator must appear before you can submit</div></div>
        <div class="step"><div class="step-num">5</div><div class="step-text">Click <strong>Post Transaction</strong></div></div>
    </div>
    <div class="note"><strong>Client tagging:</strong> If you tag a client on a savings or loan account line, the system automatically updates that client's individual account balance. Always tag the client when the line affects an individual member.</div>

    <h2>5.2 Reversing a Journal Entry</h2>
    <p>Open the journal entry, click <strong>Reverse</strong>. The system posts an equal and opposite entry and marks the original as reversed. This is the correct way to fix a posted entry.</p>

    <h2>5.3 Deleting a Journal Entry</h2>
    <p>Only the <strong>Super Admin</strong> can permanently delete a journal entry. Open the entry and click <strong>Delete</strong>. This is irreversible — use reversal instead wherever possible.</p>
</div>

{{-- ═══════════════════ SECTION 6 — LOANS ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 6</div><h1>Loans</h1></div>

    <h2>6.1 Loan Products</h2>
    <p>Before creating loans, the organisation must have loan products configured. Go to <strong>Loan Products</strong> to see available products. Each product defines the default interest rate, interest method, and term.</p>

    <h2>6.2 Creating a Loan Application</h2>
    <p>Go to <strong>Loans</strong> → <strong>New Loan</strong> and complete the 3-step wizard:</p>

    <table>
        <thead><tr><th>Step</th><th>Fields</th></tr></thead>
        <tbody>
            <tr>
                <td><strong>1 — Client &amp; Product</strong></td>
                <td>Client (searchable), Loan Product, Principal Amount, Application Notes (optional)</td>
            </tr>
            <tr>
                <td><strong>2 — Loan Terms</strong></td>
                <td>Interest Rate (%), Interest Method (Flat / Reducing Balance), Term in Months — leave blank to use product defaults</td>
            </tr>
            <tr>
                <td><strong>3 — Guarantors</strong></td>
                <td>Optional. Click <strong>Add Guarantor</strong> for each: Full Name, Phone, ID, Relationship, Employer, Monthly Income, Address</td>
            </tr>
        </tbody>
    </table>
    <p>Click <strong>Submit Application</strong> on Step 3. The loan is created with <strong>Pending</strong> status.</p>

    <h2>6.3 Disbursing a Loan</h2>
    <p>A loan must be disbursed before repayments can be recorded.</p>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the loan detail page → click <strong>Disburse Loan</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Review the <strong>Fees &amp; Deductions</strong> table (Application Fee, Management Fee, Insurance Fee)</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">For each fee, choose whether to deduct from the <strong>loan amount</strong> or from the client's <strong>savings account</strong></div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">Review the <strong>Fee Summary</strong> showing the net cash the client will receive</div></div>
        <div class="step"><div class="step-num">5</div><div class="step-text">Set the <strong>Disbursement Date</strong></div></div>
        <div class="step"><div class="step-num">6</div><div class="step-text">If any fees are deducted from savings, select the <strong>Savings Account</strong></div></div>
        <div class="step"><div class="step-num">7</div><div class="step-text">Click <strong>Disburse</strong></div></div>
    </div>
    <p>The loan becomes <strong>Active</strong> and the repayment schedule is generated automatically.</p>

    <h2>6.4 Recording a Loan Repayment</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the loan → click <strong>Record Repayment</strong> (or use the Repay action from the loans list)</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Enter Amount, Payment Date, Payment Method, and Receipt / Reference</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Click <strong>Save</strong></div></div>
    </div>
    <div class="note"><strong>Allocation order:</strong> Repayments are applied in this order — Penalty first, then Interest, then Principal.</div>

    <h2>6.5 Loan Statuses</h2>
    <table>
        <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
        <tbody>
            <tr><td><strong>Pending</strong></td><td>Application submitted, not yet disbursed</td></tr>
            <tr><td><strong>Active</strong></td><td>Disbursed and repayment is ongoing</td></tr>
            <tr><td><strong>Closed</strong></td><td>Fully repaid</td></tr>
            <tr><td><strong>Defaulted</strong></td><td>Overdue beyond the grace period</td></tr>
        </tbody>
    </table>

    <h2>6.6 Statements &amp; Exports</h2>
    <p>From the loan detail page, use the <strong>Export</strong> dropdown to: view the Loan Statement on screen, download the Loan Statement as PDF, or download the Repayment Schedule as PDF.</p>
</div>

{{-- ═══════════════════ SECTION 8 — SAVINGS ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 7</div><h1>Savings Accounts</h1></div>

    <h2>7.1 Savings Products</h2>
    <p>Products define the interest rate, minimum balance, and GL accounts. Go to <strong>Savings Products</strong> to see what is configured.</p>

    <h2>7.2 Opening a Savings Account</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Go to <strong>Savings</strong> → <strong>Open Account</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Select the <strong>Client</strong> and <strong>Savings Product</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Set the <strong>Opening Date</strong> → click <strong>Open Account</strong></div></div>
    </div>

    <h2>7.3 Depositing into a Savings Account</h2>
    <p>Open the savings account → click <strong>Deposit</strong>. Enter Amount, Date, Payment Source (cash GL account), Receipt / Reference, and Narration. Click <strong>Post Deposit</strong>. Alternatively, use <strong>Quick Teller</strong> for faster processing.</p>

    <h2>7.4 Withdrawing from a Savings Account</h2>
    <p>Open the account → click <strong>Withdraw</strong>. Enter Amount, Date, Fee (if any), Payment Source, Reference, and Narration. Click <strong>Post Withdrawal</strong>.</p>
    <div class="warning"><strong>Note:</strong> Withdrawals that would take the balance below the product's minimum balance will be blocked.</div>

    <h2>7.5 Transferring Between Accounts</h2>
    <p>Open the source account → click <strong>Transfer</strong>. Select the destination account, enter the Amount, Date, and Reference. Click <strong>Post Transfer</strong>.</p>

    <h2>7.6 Posting Interest</h2>
    <p><strong>Single account:</strong> Open the account → click <strong>Post Interest</strong> → confirm the date → click Post.</p>
    <p><strong>All accounts at once (bulk):</strong> On the Savings list page, click <strong>Post Interest</strong> (top right) → select the Posting Date → click <strong>Post Interest to All Eligible Accounts</strong>.</p>
    <p>Interest is calculated based on daily balance and the product's annual rate.</p>

    <h2>7.7 Viewing a Statement</h2>
    <p>Open any savings account and scroll down for the full transaction history. Click <strong>Export Statement (PDF)</strong> to download.</p>
</div>

{{-- ═══════════════════ SECTION 9 — FIXED DEPOSITS ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 8</div><h1>Fixed Deposits</h1></div>

    <h2>8.1 Fixed Deposit Products</h2>
    <p>Go to <strong>FD Products</strong> to see available products. Each product defines the default interest rate, term, and GL accounts.</p>

    <h2>8.2 Creating a Fixed Deposit</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Go to <strong>Fixed Deposits</strong> → <strong>New Fixed Deposit</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Select the <strong>Client</strong> and <strong>FD Product</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Enter <strong>Principal Amount</strong>. Override interest rate and term if different from product defaults</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">Set the <strong>Start Date</strong> — a live maturity calculation shows projected interest and maturity amount</div></div>
        <div class="step"><div class="step-num">5</div><div class="step-text">Select <strong>Funding Source</strong>: Cash (select cash GL account) or Deduct from Savings (select client's savings account)</div></div>
        <div class="step"><div class="step-num">6</div><div class="step-text">Enter the <strong>Receipt / Reference</strong> → click <strong>Create Fixed Deposit</strong></div></div>
    </div>
    <div class="note"><strong>Interest formula:</strong> Simple Interest = Principal × Rate × (Term ÷ 12)</div>

    <h2>8.3 Processing Maturity Payout</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the fixed deposit — a maturity banner appears when it has matured</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Click <strong>Process Maturity Payout</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Choose the payout destination (savings account or cash), confirm date and reference</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">Click <strong>Process</strong> — the FD closes and the maturity amount is credited</div></div>
    </div>

    <h2>8.4 Breaking a Fixed Deposit Early</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the fixed deposit → click <strong>Break FD</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Read the warning: only the <strong>principal</strong> is returned; all accrued interest is <strong>forfeited</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Choose the payout destination, confirm date and reference → click <strong>Break Deposit</strong></div></div>
    </div>
    <div class="danger"><strong>This action cannot be undone.</strong> Confirm with the client before proceeding.</div>

    <h2>8.5 FD Certificate</h2>
    <p>Open any fixed deposit → click <strong>FD Certificate</strong> to download a PDF certificate to give to the client.</p>
</div>

{{-- ═══════════════════ SECTION 10 — GROUPS ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 9</div><h1>Groups</h1></div>
    <p>Groups are clients that operate collectively. Two types are supported:</p>
    <ul>
        <li><strong>Savings Group</strong> — each member has an individual balance and earns interest</li>
        <li><strong>Pooled Group</strong> — all members contribute to a shared fund</li>
    </ul>

    <h2>9.1 Viewing Groups</h2>
    <p>Go to <strong>Groups</strong> in the sidebar. The list shows group name, number, member count, total balance, interest rate, and status.</p>

    <h2>9.2 Creating a Group</h2>
    <p>Groups are created through the <strong>Clients</strong> module (Section 3.3). Once created, the group appears in both the Clients list and the Groups list.</p>

    <h2>9.3 Managing Group Members</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the group → scroll to <strong>Members</strong> → click <strong>Add Member</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Enter Full Name, Phone, National ID. Tick <strong>Is Leader</strong> if applicable</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Enter a <strong>Portal Email</strong> if the member will use the Group Portal (default password: <strong>Welcome@123</strong>)</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">Click <strong>Save</strong></div></div>
    </div>
    <p>To edit a member, click the <strong>edit icon</strong> on their row. You can update details, change their password, or promote/demote them as leader.</p>

    <h2>9.4 Recording Transactions — Savings Group</h2>
    <p>From the group detail page use: <strong>Deposit</strong> (record a deposit for a member), <strong>Withdraw</strong> (record a withdrawal), or <strong>Post Interest</strong> (calculate and post monthly interest to all members).</p>

    <h2>9.5 Recording Transactions — Pooled Group</h2>
    <p>Use: <strong>Record Contribution</strong> (mark a member's contribution for the cycle) or <strong>Pool Withdrawal</strong> (record a withdrawal from the shared pool).</p>

    <h2>9.6 Transferring a Member Balance to Savings</h2>
    <p>Click the <strong>Transfer to Savings</strong> icon next to the member. Select the target savings account, enter the amount and date, then click <strong>Transfer</strong>.</p>

    <h2>9.7 Sending a Portal Reset Link</h2>
    <p>If a group member forgets their password, click the <strong>Send Reset Link</strong> button (envelope icon) next to their name. The system emails a reset link to their portal email address.</p>
</div>

{{-- ═══════════════════ SECTION 11 — SHARES ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 10</div><h1>Member Shares</h1></div>
    <p>This module tracks member share subscriptions — how many shares each member holds and how much has been paid.</p>

    <h2>10.1 Viewing All Shares</h2>
    <p>Go to <strong>Shares</strong> in the sidebar for a summary of all members' share positions.</p>

    <h2>10.2 Adding Shares to a Member</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open the <strong>Client Profile</strong> → scroll to <strong>Member Shares</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Click <strong>Add Share</strong> → enter Share Value and Notes → click <strong>Save</strong></div></div>
    </div>
    <p>The share is created with <strong>Unpaid</strong> status.</p>

    <h2>10.3 Recording a Share Payment</h2>
    <p>Click <strong>Pay</strong> next to the share. Choose the payment source (cash GL account or deduct from savings), enter the Amount (partial payments allowed), Date, and Receipt. Click <strong>Save</strong>.</p>

    <h2>10.4 Revaluing Shares</h2>
    <p>Click <strong>Revalue</strong> next to the share. Enter the New Share Value and Effective Date. Click <strong>Save</strong>. To revalue all shares at once, use <strong>Shares → Revalue All</strong>.</p>

    <h2>10.5 Liquidating Shares</h2>
    <p>When a member exits and their shares are bought back, click <strong>Liquidate</strong>, choose the payout method (Cash or Credit to Savings), enter the date and notes, then click <strong>Liquidate</strong>.</p>
    <div class="danger"><strong>This action cannot be undone.</strong> The share is permanently marked as Liquidated.</div>
</div>

{{-- ═══════════════════ SECTION 12 — PAYROLL ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 11</div><h1>Payroll</h1></div>

    <h2>11.1 Creating a Payroll Run</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Go to <strong>Payroll</strong> → <strong>New Payroll Run</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Select <strong>Month</strong> and <strong>Year</strong>, add a Description (optional)</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Click <strong>Add All Active</strong> to load all active employees, or add rows one at a time</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">For each employee: confirm Basic Salary (auto-fills), adjust Allowances and Deductions — Net Salary calculates automatically</div></div>
        <div class="step"><div class="step-num">5</div><div class="step-text">Click <strong>Save Payroll Run</strong> (saves as Draft)</div></div>
    </div>
    <div class="note"><strong>Net Salary = Basic Salary + Allowances − Deductions</strong></div>

    <h2>11.2 Processing a Payroll Run</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Open a Draft payroll run</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Review all employee figures carefully</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Click <strong>Process Payroll</strong></div></div>
    </div>
    <p>The system posts all salary GL entries and credits each employee's linked savings account. The run is locked after processing — status changes to <strong>Processed</strong>.</p>

    <h2>11.3 Reversing a Payroll Run</h2>
    <p>If a payroll was processed in error, open the run and click <strong>Reverse</strong>. All GL entries and savings credits are reversed and the run returns to <strong>Draft</strong> for correction.</p>
</div>

{{-- ═══════════════════ SECTION 13 — REPORTS ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 12</div><h1>Reports</h1></div>
    <p>Go to <strong>Reports</strong> in the sidebar and click any report card to open it.</p>

    <table>
        <thead><tr><th>Report</th><th>What It Shows</th><th>Key Filters</th></tr></thead>
        <tbody>
            <tr><td><strong>Trial Balance</strong></td><td>Debit and credit totals for every GL account</td><td>As-of date</td></tr>
            <tr><td><strong>Income Statement</strong></td><td>Revenue vs expenses — profit or loss for a period</td><td>Date range</td></tr>
            <tr><td><strong>Balance Sheet</strong></td><td>Assets, Liabilities, and Equity snapshot</td><td>As-of date</td></tr>
            <tr><td><strong>General Ledger</strong></td><td>Every transaction posted to a specific account</td><td>Account, date range</td></tr>
            <tr><td><strong>Loan Portfolio</strong></td><td>All loans with status and outstanding principal</td><td>Status, date range</td></tr>
            <tr><td><strong>Loan Aging</strong></td><td>Overdue loans grouped by how long they are overdue</td><td>Date</td></tr>
            <tr><td><strong>Repayment Schedule</strong></td><td>Full repayment schedule for a selected loan</td><td>Loan selection</td></tr>
            <tr><td><strong>Interest Income</strong></td><td>Interest earned on loans and savings in a period</td><td>Date range</td></tr>
            <tr><td><strong>Savings Balances</strong></td><td>All active savings accounts and their balances</td><td>As-of date</td></tr>
            <tr><td><strong>FD Maturity</strong></td><td>Fixed deposits maturing within a given period</td><td>Date range</td></tr>
            <tr><td><strong>Member Summary</strong></td><td>Per-member summary of savings, loans, FDs, and shares</td><td>—</td></tr>
        </tbody>
    </table>

    <p>Most reports can be printed or exported to PDF using the <strong>Download PDF</strong> button or your browser's print function (Ctrl+P).</p>
</div>

{{-- ═══════════════════ SECTIONS 14–16 ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 13</div><h1>User Management</h1></div>
    <p>Only users with the <strong>Manage Users</strong> permission can access this module.</p>

    <h2>13.1 Creating a New User</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Go to <strong>Users</strong> → click <strong>New User</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">Enter Name, Email, Phone (optional), Role, Branch, and a temporary Password</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">Click <strong>Save</strong>, then share the email and temporary password with the user</div></div>
    </div>

    <h2>13.2 Editing a User</h2>
    <p>Click <strong>Edit</strong> next to any user to change their name, phone, role, or branch.</p>

    <h2>13.3 Activating / Deactivating a User</h2>
    <p>Click the <strong>toggle icon</strong> (play/pause) next to a user. A deactivated user cannot log in. Use this instead of deleting accounts when staff leave.</p>

    <hr>

    <div class="section-header" style="margin-top:24px"><div class="sec-num">Section 14</div><h1>System Settings</h1></div>
    <p>Only users with the <strong>Manage Settings</strong> permission can access this. Go to <strong>Settings</strong> in the sidebar.</p>

    <h2>Key Settings</h2>
    <table>
        <thead><tr><th>Group</th><th>Key Settings</th></tr></thead>
        <tbody>
            <tr><td><strong>Organisation</strong></td><td>Organisation Name (shown on reports and login page), Logo (PNG/JPG/SVG, max 2 MB), Address, Phone, Email</td></tr>
            <tr><td><strong>Financial</strong></td><td>Default currency symbol, financial year start month, default penalty rates</td></tr>
            <tr><td><strong>Modules</strong></td><td>Enable/disable: Membership Fees, Shares, Fixed Deposits, Groups, Payroll</td></tr>
            <tr><td><strong>Email/Mail</strong></td><td>SMTP settings for sending portal invitations and password resets</td></tr>
        </tbody>
    </table>
    <p>After making changes, click <strong>Save Settings</strong> at the top of the page.</p>

    <h2>Data Reconciliation</h2>
    <p>If account balances appear out of sync, use <strong>Run Reconciliation</strong> at the bottom of Settings. This recalculates all balances and fixes any discrepancies.</p>
    <div class="warning"><strong>Only run reconciliation when advised by your system administrator.</strong></div>

    <hr>

    <div class="section-header" style="margin-top:24px"><div class="sec-num">Section 15</div><h1>Audit Log</h1></div>
    <p>Go to <strong>Audit Log</strong> in the sidebar. Every significant action in the system is recorded here: who did it, what they did, and when. Use the date filter and search to find specific events. This log cannot be edited or deleted.</p>
</div>

{{-- ═══════════════════ SECTION 17 — CLIENT PORTAL ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 16</div><h1>Client Portal</h1></div>
    <p>The Client Portal is a separate login area where individual clients can view their own accounts without accessing any administrative features.</p>

    <h2>16.1 What Clients Can See</h2>
    <table>
        <thead><tr><th>Page</th><th>Content</th></tr></thead>
        <tbody>
            <tr><td><strong>Dashboard</strong></td><td>Summary of all savings balances, active loans, and fixed deposits</td></tr>
            <tr><td><strong>Accounts</strong></td><td>List of all linked savings accounts, loans, and FDs</td></tr>
            <tr><td><strong>Activity</strong></td><td>Chronological feed of all recent transactions</td></tr>
            <tr><td><strong>Savings Statements</strong></td><td>Full statement per savings account, downloadable as PDF</td></tr>
            <tr><td><strong>Loan Statements</strong></td><td>Loan details, repayment history, and schedule PDF</td></tr>
            <tr><td><strong>FD Statements</strong></td><td>Fixed deposit details and certificate PDF</td></tr>
        </tbody>
    </table>

    <h2>16.2 How a Client Accesses the Portal</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">Staff sends an invitation from the client profile (Section 3.6)</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">The client receives an email — they click the link and set a password</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">They log in at the same system URL using their email and new password</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-text">After login they land directly on the Client Portal dashboard</td></div>
    </div>

    <h2>16.3 Multiple Accounts</h2>
    <p>If a client is linked to more than one profile, a <strong>Switch Account</strong> option appears allowing them to toggle between profiles.</p>
</div>

{{-- ═══════════════════ SECTION 18 — GROUP PORTAL ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 17</div><h1>Group Portal</h1></div>
    <p>The Group Portal has two access levels: <strong>Group Leader</strong> and <strong>Group Member</strong>.</p>

    <h2>17.1 Group Leader Portal</h2>
    <p>Group leaders have a management dashboard showing the health of their group.</p>
    <table>
        <thead><tr><th>Section</th><th>What It Shows</th></tr></thead>
        <tbody>
            <tr><td>Summary Cards</td><td>Pool/Total Balance, Active Members, Deposits This Month, Last Deposit Date</td></tr>
            <tr><td>Trend Chart</td><td>Bar chart of monthly deposits for the last 6 months</td></tr>
            <tr><td>Top Savers</td><td>Top 3 members ranked by savings balance</td></tr>
            <tr><td>Lowest Balances</td><td>Bottom 3 members (flagged in red if zero balance)</td></tr>
            <tr><td>Watch List</td><td>Members with zero balance or no deposit in 60+ days</td></tr>
            <tr><td>All Members</td><td>Full member list with balances and last deposit dates</td></tr>
            <tr><td>Member Statements</td><td>Full transaction history for any member in the group</td></tr>
        </tbody>
    </table>

    <h2>17.2 Group Member Portal</h2>
    <p>Members see only their own position within the group:</p>
    <ul>
        <li>Their individual savings balance</li>
        <li>Group name and monthly interest rate</li>
        <li>Full transaction history (deposits, withdrawals, interest)</li>
    </ul>

    <h2>17.3 How Group Members Access the Portal</h2>
    <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text">When adding a member (Section 10.3), staff enters their <strong>Portal Email</strong></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text">The member's default password is: <strong>Welcome@123</strong></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text">The member logs in at the system URL and should change their password immediately</div></div>
    </div>
    <div class="note">To reset a forgotten password, staff clicks <strong>Send Reset Link</strong> on the group members table (Section 10.7).</div>
</div>

{{-- ═══════════════════ SECTION 19 — ROLES ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Section 18</div><h1>Roles &amp; Permissions Reference</h1></div>

    <h2>System Roles</h2>
    <table>
        <thead><tr><th>Role</th><th>Access Level</th></tr></thead>
        <tbody>
            <tr><td><strong>Super Admin</strong></td><td>Full access to everything — delete transactions, manage roles, change system settings</td></tr>
            <tr><td><strong>Admin</strong></td><td>Full operational access; cannot delete journal entries or manage system roles</td></tr>
            <tr><td><strong>Cashier</strong></td><td>Process deposits, withdrawals, repayments, and Quick Teller; cannot access reports, settings, or user management</td></tr>
            <tr><td><strong>Staff</strong></td><td>Read-only access to most modules; cannot post transactions</td></tr>
            <tr><td><strong>Client</strong></td><td>Client Portal only — can view their own accounts</td></tr>
            <tr><td><strong>Group Leader</strong></td><td>Group Leader Portal — group dashboard and all member statements</td></tr>
            <tr><td><strong>Group Member</strong></td><td>Group Member Portal — own balance and statement only</td></tr>
        </tbody>
    </table>

    <h2>Key Permissions</h2>
    <table>
        <thead><tr><th>Permission</th><th>What It Allows</th></tr></thead>
        <tbody>
            <tr><td>view clients</td><td>See the clients list and profiles</td></tr>
            <tr><td>edit clients</td><td>Edit client details and send portal invitations</td></tr>
            <tr><td>create loans</td><td>Submit loan applications and add guarantors</td></tr>
            <tr><td>disburse loans</td><td>Disburse approved loan applications</td></tr>
            <tr><td>repay loans</td><td>Record loan repayments</td></tr>
            <tr><td>create savings</td><td>Open new savings accounts</td></tr>
            <tr><td>deposit savings</td><td>Post deposits and bulk interest</td></tr>
            <tr><td>withdraw savings</td><td>Post withdrawals</td></tr>
            <tr><td>create fixed-deposits</td><td>Create new fixed deposits</td></tr>
            <tr><td>mature fixed-deposits</td><td>Process maturity payouts and early breaks</td></tr>
            <tr><td>create transactions</td><td>Post manual journal entries</td></tr>
            <tr><td>reverse transactions</td><td>Reverse posted journal entries</td></tr>
            <tr><td>view reports</td><td>Access the reports module</td></tr>
            <tr><td>manage users</td><td>Create and edit system users</td></tr>
            <tr><td>manage settings</td><td>Access and change system settings</td></tr>
            <tr><td>manage backup</td><td>Create and download database backups</td></tr>
        </tbody>
    </table>
</div>

{{-- ═══════════════════ APPENDIX A ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Appendix A</div><h1>Common Workflows</h1></div>

    <h2>Onboarding a New Member (Full Workflow)</h2>
    <table>
        <thead><tr><th>#</th><th>Step</th><th>Where</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Register the client</td><td>Clients → New Client</td></tr>
            <tr><td>2</td><td>Collect membership fee</td><td>Client Profile → Membership Fee → Record Payment</td></tr>
            <tr><td>3</td><td>Open a savings account</td><td>Savings → Open Account</td></tr>
            <tr><td>4</td><td>Make the opening deposit</td><td>Savings Account → Deposit (or Quick Teller)</td></tr>
            <tr><td>5</td><td>Create loan application (if applicable)</td><td>Loans → New Loan</td></tr>
            <tr><td>6</td><td>Disburse the loan</td><td>Loan Detail → Disburse Loan</td></tr>
            <tr><td>7</td><td>Invite client to portal</td><td>Client Profile → envelope icon</td></tr>
        </tbody>
    </table>

    <h2>Monthly End-of-Month Routine</h2>
    <table>
        <thead><tr><th>#</th><th>Task</th><th>Where</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Post savings interest (bulk)</td><td>Savings → Post Interest</td></tr>
            <tr><td>2</td><td>Review overdue loans</td><td>Reports → Loan Aging</td></tr>
            <tr><td>3</td><td>Process group interest (if applicable)</td><td>Groups → group detail → Post Interest</td></tr>
            <tr><td>4</td><td>Run payroll (if applicable)</td><td>Payroll → New Payroll Run → Process</td></tr>
            <tr><td>5</td><td>Generate financial reports</td><td>Reports → Income Statement &amp; Balance Sheet</td></tr>
            <tr><td>6</td><td>Close the financial period</td><td>Settings → Financial Periods → Close Month</td></tr>
        </tbody>
    </table>

    <h2>Correcting a Posted Transaction</h2>
    <table>
        <thead><tr><th>Scenario</th><th>Correct Action</th></tr></thead>
        <tbody>
            <tr><td>Wrong amount or account in a manual journal</td><td>Journal Entries → open entry → Reverse, then post a new correct entry</td></tr>
            <tr><td>Savings deposit or withdrawal posted in error</td><td>Open the savings account → find the transaction → Reverse</td></tr>
            <tr><td>Loan repayment posted in error</td><td>Open the loan → find the repayment → Reverse</td></tr>
            <tr><td>Complete removal of an entry (last resort)</td><td>Super Admin only — open the entry → Delete</td></tr>
        </tbody>
    </table>
</div>

{{-- ═══════════════════ APPENDIX B — GLOSSARY ═══════════════════ --}}
<div class="page section-break">
    <div class="section-header"><div class="sec-num">Appendix B</div><h1>Glossary</h1></div>

    <table>
        <thead><tr><th>Term</th><th>Meaning</th></tr></thead>
        <tbody>
            <tr><td><strong>GL</strong></td><td>General Ledger — the master record of all financial transactions in the system</td></tr>
            <tr><td><strong>Double-entry</strong></td><td>Every financial transaction has equal debits and credits; the books must always balance</td></tr>
            <tr><td><strong>Principal</strong></td><td>The original loan or deposit amount, excluding interest</td></tr>
            <tr><td><strong>Outstanding Principal</strong></td><td>The remaining loan balance that has not yet been repaid</td></tr>
            <tr><td><strong>Flat Interest</strong></td><td>Interest calculated on the full original principal for the entire loan term</td></tr>
            <tr><td><strong>Reducing Balance</strong></td><td>Interest calculated on the remaining outstanding principal each period — decreases as the loan is repaid</td></tr>
            <tr><td><strong>Amortisation</strong></td><td>The process of gradually paying off a loan through scheduled equal payments</td></tr>
            <tr><td><strong>FD</strong></td><td>Fixed Deposit — a savings product where money is locked for a fixed term at a fixed interest rate</td></tr>
            <tr><td><strong>Accrued Interest</strong></td><td>Interest that has been earned or incurred but not yet collected or paid</td></tr>
            <tr><td><strong>Sub-ledger</strong></td><td>Individual member account records that detail the transactions behind the GL balance</td></tr>
            <tr><td><strong>SACCO</strong></td><td>Savings and Credit Cooperative Organisation</td></tr>
            <tr><td><strong>MFI</strong></td><td>Micro Finance Institution</td></tr>
            <tr><td><strong>Disbursement</strong></td><td>The act of paying out an approved loan to the borrower</td></tr>
            <tr><td><strong>Collateral</strong></td><td>An asset pledged as security against a loan</td></tr>
            <tr><td><strong>Guarantor</strong></td><td>A person who agrees to repay a loan if the borrower defaults</td></tr>
            <tr><td><strong>Maturity Date</strong></td><td>The date when a fixed deposit or loan term comes to an end</td></tr>
            <tr><td><strong>Reconciliation</strong></td><td>The process of verifying that account balances in the GL match the individual sub-ledger records</td></tr>
        </tbody>
    </table>

    <div style="margin-top:40px;text-align:center;color:#9ca3af;font-size:9.5px;border-top:1px solid #e5e7eb;padding-top:16px">
        <strong style="color:#0f2444">ElTech System</strong> — User Manual v1.0 — June 2026<br>
        This document is confidential and intended for authorised staff of {{ $orgName }} only.<br>
        For technical support, contact your system administrator.
    </div>
</div>

<div class="page-footer">
    <span class="footer-brand">ElTech System</span>
    <span>User Manual — Confidential</span>
    <span>© {{ date('Y') }} ElTech System</span>
</div>

</body>
</html>
