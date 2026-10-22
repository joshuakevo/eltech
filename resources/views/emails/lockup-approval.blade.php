<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:560px;">
    <tr>
        <td style="background:#0f2444;padding:20px 28px;">
            @php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp
            @if($_logo)
                <img src="{{ asset($_logo) }}" alt="{{ $orgName }}" style="height:32px;max-width:180px;object-fit:contain;">
            @else
                <span style="color:#fff;font-size:18px;font-weight:bold;">{{ $orgName }}</span>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:28px;">
            <p style="margin:0 0 14px;font-size:15px;">Dear {{ \Illuminate\Support\Str::before($approver->name, ' ') }},</p>
            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#374151;">
                {{ $isNew ? 'A loan lock-up has been requested and needs your approval' : 'This loan lock-up is still waiting for your approval' }} as <strong>{{ $roleLabel }}</strong>.
            </p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:13px;color:#374151;border:1px solid #e5e7eb;margin:0 0 16px;">
                <tr><td style="padding:8px 12px;color:#6b7280;width:38%;">Loan</td><td style="padding:8px 12px;font-weight:bold;">{{ $loan->loan_number }} — {{ $loan->product->name ?? '' }}</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;color:#6b7280;">Member</td><td style="padding:8px 12px;">{{ $loan->client->name ?? '' }} ({{ $loan->client->client_number ?? '' }})</td></tr>
                <tr><td style="padding:8px 12px;color:#6b7280;">Owing</td><td style="padding:8px 12px;">Principal {{ number_format($loan->outstanding_principal, 0) }} · Interest {{ number_format($loan->outstanding_interest, 0) }}</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;color:#6b7280;">Lock up from</td><td style="padding:8px 12px;">{{ $lockup->lockup_date->format('d M Y') }}</td></tr>
                <tr><td style="padding:8px 12px;color:#6b7280;">Requested by</td><td style="padding:8px 12px;">{{ $lockup->requester->name ?? '' }} on {{ $lockup->created_at->format('d M Y H:i') }}</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;color:#6b7280;">Reason</td><td style="padding:8px 12px;">{{ $lockup->reason }}</td></tr>
            </table>
            <p style="margin:0 0 20px;"><a href="{{ route('loans.lockups.index') }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;font-weight:bold;">Review &amp; approve</a></p>
            <p style="margin:0;font-size:12px;color:#6b7280;">The loan is locked up only after every approver role has approved. You can also reject it from the same page.</p>
        </td>
    </tr>
    <tr>
        <td style="background:#f9fafb;padding:16px 28px;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb;">
            This is an automated message from {{ $orgName }}. Please do not reply directly to this email.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
