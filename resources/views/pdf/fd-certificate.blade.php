<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }

    /* Outer border frame */
    .certificate-frame {
        border: 6px double #0f2444;
        margin: 10px;
        padding: 0;
        min-height: 730px;
        position: relative;
    }
    .inner-frame {
        border: 1.5px solid #c9a227;
        margin: 6px;
        padding: 28px 32px 24px;
        min-height: 706px;
    }

    /* Header */
    .cert-header { text-align: center; border-bottom: 2px solid #0f2444; padding-bottom: 16px; margin-bottom: 20px; }
    .logo-band { background: #0f2444; padding: 12px 20px; margin: -28px -32px 14px; text-align: center; }
    .logo-band img { height: 44px; max-width: 220px; }
    .logo-band .org-text { color: #fff; font-size: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.12em; }
    .org-name { font-size: 20px; font-weight: bold; color: #0f2444; text-transform: uppercase; letter-spacing: 0.12em; }
    .org-sub { font-size: 10px; color: #6b7280; margin-top: 2px; letter-spacing: 0.05em; }
    .cert-title { font-size: 15px; font-weight: bold; color: #c9a227; text-transform: uppercase; letter-spacing: 0.15em; margin-top: 14px; }
    .cert-subtitle { font-size: 10px; color: #6b7280; margin-top: 2px; }

    /* Certificate number badge */
    .cert-number-row { text-align: center; margin: 16px 0 20px; }
    .cert-number-badge {
        display: inline-block;
        background: #0f2444;
        color: #c9a227;
        font-size: 13px;
        font-weight: bold;
        letter-spacing: 0.1em;
        padding: 6px 28px;
        border-radius: 2px;
    }

    /* Body text */
    .cert-body { font-size: 11px; line-height: 1.85; color: #333; margin-bottom: 20px; text-align: justify; }
    .cert-body strong { color: #0f2444; }
    .underline-val {
        display: inline-block;
        border-bottom: 1px solid #0f2444;
        min-width: 160px;
        text-align: center;
        font-weight: bold;
        color: #0f2444;
        padding: 0 6px;
    }

    /* Details table */
    .details-table { width: 100%; border-collapse: collapse; margin: 18px 0; }
    .details-table td { padding: 7px 12px; font-size: 11px; border: 1px solid #e5e7eb; }
    .details-table td.lbl { color: #6b7280; width: 38%; background: #f9fafb; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
    .details-table td.val { font-weight: bold; color: #0f2444; }
    .highlight-row td { background: #fffbeb !important; }
    .highlight-row td.lbl { background: #fef3c7 !important; }

    /* Interest formula */
    .formula-box {
        background: #f0f4ff;
        border: 1px solid #c7d2fe;
        border-radius: 3px;
        padding: 10px 16px;
        margin: 14px 0;
        font-size: 10px;
        color: #374151;
    }
    .formula-box strong { color: #0f2444; }

    /* Stamp + issue details (shared by email: no pen signatures) */
    .seal-section { width: 100%; border-collapse: collapse; margin-top: 22px; }
    .seal-section td { vertical-align: middle; padding: 0 8px; }
    .seal-caption { font-size: 10px; color: #374151; line-height: 1.6; }
    .seal-caption strong { color: #0f2444; }

    /* Drawn stamp (when no stamp image is uploaded) */
    .stamp {
        width: 118px; height: 118px;
        border: 3px solid #1d4ed8;
        border-radius: 59px;
        padding: 4px;
        margin: 0 auto;
    }
    .stamp-inner {
        width: 104px; height: 104px;
        border: 1px solid #1d4ed8;
        border-radius: 52px;
        text-align: center;
        color: #1d4ed8;
        font-weight: bold;
    }
    .stamp-org { font-size: 8.5px; letter-spacing: 0.08em; text-transform: uppercase; padding: 16px 8px 0; line-height: 1.25; }
    .stamp-mid { font-size: 11px; letter-spacing: 0.15em; border-top: 1px solid #1d4ed8; border-bottom: 1px solid #1d4ed8; margin: 6px 8px; padding: 3px 0; }
    .stamp-date { font-size: 8.5px; letter-spacing: 0.05em; }

    /* Footer */
    .cert-footer {
        border-top: 1px solid #e5e7eb;
        margin-top: 24px;
        padding-top: 8px;
        font-size: 8.5px;
        color: #9ca3af;
        text-align: center;
    }

    /* Watermark-style status */
    .status-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 64px;
        font-weight: bold;
        color: rgba(220, 38, 38, 0.08);
        text-transform: uppercase;
        letter-spacing: 0.15em;
        white-space: nowrap;
        pointer-events: none;
        z-index: 0;
    }

    .content-wrap { position: relative; z-index: 1; }

    .two-col { width: 100%; border-collapse: collapse; }
    .two-col td { width: 50%; vertical-align: top; }
</style>
</head>
<body>
<div class="certificate-frame">
    @if($fixedDeposit->status === 'closed' || $fixedDeposit->status === 'broken')
    <div class="status-watermark">{{ strtoupper($fixedDeposit->status) }}</div>
    @endif

    <div class="inner-frame content-wrap">

        {{-- Header --}}
        <div class="cert-header">
            @php
                $_org   = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
                $_logo  = \App\Models\SystemSetting::get('org_logo');
                $_stamp = \App\Models\SystemSetting::get('org_stamp');
            @endphp
            <div class="logo-band">@if($_logo && file_exists(public_path($_logo)))<img src="{{ public_path($_logo) }}">@else<span class="org-text">{{ $_org }}</span>@endif</div>
            <div class="org-sub">Trusted Financial Services &mdash; Certificate of Fixed Deposit</div>
            <div class="cert-title">Fixed Deposit Certificate</div>
            <div class="cert-subtitle">This certifies that the following deposit has been received and accepted</div>
        </div>

        {{-- Certificate number --}}
        <div class="cert-number-row">
            <span class="cert-number-badge">{{ $fixedDeposit->deposit_number }}</span>
        </div>

        {{-- Narrative paragraph --}}
        <div class="cert-body">
            This is to certify that <strong>{{ $fixedDeposit->client->name }}</strong>
            has placed a Fixed Deposit with <strong>{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</strong>
            under the product <strong>{{ $fixedDeposit->product->name }}</strong>,
            with a principal amount of
            <span class="underline-val">UGX {{ number_format($fixedDeposit->principal, $dp) }}</span>
            at an annual interest rate of
            <span class="underline-val">{{ $fixedDeposit->interest_rate }}%</span>
            for a term of
            <span class="underline-val">{{ $fixedDeposit->term_months }} month(s)</span>,
            commencing on <strong>{{ $fixedDeposit->start_date->format('d F Y') }}</strong>
            and maturing on <strong>{{ $fixedDeposit->maturity_date->format('d F Y') }}</strong>.
        </div>

        {{-- Details table --}}
        <table class="details-table">
            <tr>
                <td class="lbl">Depositor Name</td>
                <td class="val">{{ $fixedDeposit->client->name }}</td>
                <td class="lbl">Client Number</td>
                <td class="val">{{ $fixedDeposit->client->client_number }}</td>
            </tr>
            <tr>
                <td class="lbl">Deposit Number</td>
                <td class="val">{{ $fixedDeposit->deposit_number }}</td>
                <td class="lbl">Product</td>
                <td class="val">{{ $fixedDeposit->product->name }}</td>
            </tr>
            <tr>
                <td class="lbl">Principal Amount</td>
                <td class="val">UGX {{ number_format($fixedDeposit->principal, $dp) }}</td>
                <td class="lbl">Interest Rate</td>
                <td class="val">{{ $fixedDeposit->interest_rate }}% per annum</td>
            </tr>
            <tr>
                <td class="lbl">Start Date</td>
                <td class="val">{{ $fixedDeposit->start_date->format('d M Y') }}</td>
                <td class="lbl">Maturity Date</td>
                <td class="val">{{ $fixedDeposit->maturity_date->format('d M Y') }}</td>
            </tr>
            <tr>
                <td class="lbl">Term</td>
                <td class="val">{{ $fixedDeposit->term_months }} Month(s)</td>
                <td class="lbl">Status</td>
                <td class="val">{{ ucfirst($fixedDeposit->status) }}</td>
            </tr>
            <tr class="highlight-row">
                <td class="lbl">Interest Amount</td>
                <td class="val">UGX {{ number_format($fixedDeposit->interest_amount, $dp) }}</td>
                <td class="lbl">Maturity Amount</td>
                <td class="val" style="font-size:13px; color:#15803d;">UGX {{ number_format($fixedDeposit->maturity_amount, $dp) }}</td>
            </tr>
        </table>

        {{-- Interest formula note --}}
        <div class="formula-box">
            <strong>Interest Calculation (Simple Interest):</strong>
            &nbsp; I = P &times; R &times; T &nbsp;=&nbsp;
            UGX {{ number_format($fixedDeposit->principal, $dp) }}
            &times; {{ $fixedDeposit->interest_rate }}%
            &times; ({{ $fixedDeposit->term_months }}/12)
            &nbsp;=&nbsp; <strong>UGX {{ number_format($fixedDeposit->interest_amount, $dp) }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Maturity Amount:</strong> UGX {{ number_format($fixedDeposit->principal, $dp) }} + UGX {{ number_format($fixedDeposit->interest_amount, $dp) }}
            = <strong>UGX {{ number_format($fixedDeposit->maturity_amount, $dp) }}</strong>
        </div>

        {{-- Official stamp (certificate is shared by email — no signature lines) --}}
        <table class="seal-section">
            <tr>
                <td style="width:150px; text-align:center;">
                    @if($_stamp && file_exists(public_path($_stamp)))
                        <img src="{{ public_path($_stamp) }}" style="width:130px; max-height:130px;">
                    @else
                        <div class="stamp"><div class="stamp-inner">
                            <div class="stamp-org">{{ $_org }}</div>
                            <div class="stamp-mid">OFFICIAL</div>
                            <div class="stamp-date">{{ now()->format('d M Y') }}</div>
                        </div></div>
                    @endif
                </td>
                <td class="seal-caption">
                    Issued for and on behalf of <strong>{{ $_org }}</strong><br>
                    Date of issue: <strong>{{ now()->format('d F Y') }}</strong><br>
                    Certificate No.: <strong>{{ $fixedDeposit->deposit_number }}</strong>
                </td>
            </tr>
        </table>

        {{-- Footer --}}
        <div class="cert-footer">
            This certificate is computer-generated and valid without a signature. &bull;
            Deposit Number: {{ $fixedDeposit->deposit_number }} &bull;
            Printed: {{ now()->format('d M Y H:i') }}
        </div>

    </div>
</div>
</body>
</html>
