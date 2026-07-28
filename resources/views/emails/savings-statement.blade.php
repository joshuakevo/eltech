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
            <p style="margin:0 0 16px;font-size:15px;">Dear {{ $client->name }},</p>
            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#374151;">
                Please find attached your savings statement for the period
                <strong>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</strong> to
                <strong>{{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</strong>.
            </p>
            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#374151;">
                If you have any questions about your statement, please get in touch with us.
            </p>
            <p style="margin:24px 0 0;font-size:14px;color:#374151;">Thank you for banking with us.</p>
            <p style="margin:4px 0 0;font-size:14px;font-weight:bold;color:#0f2444;">{{ $orgName }}</p>
        </td>
    </tr>
    <tr>
        <td style="background:#f9fafb;padding:16px 28px;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb;">
            This is an automated message. Please do not reply directly to this email.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
