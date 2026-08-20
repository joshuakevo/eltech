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
            <p style="margin:0 0 16px;font-size:15px;">Hi {{ $user->name }},</p>
            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#374151;">
                You've been added as a <strong>{{ ucfirst(str_replace('_',' ',$user->role_name)) }}</strong> on {{ $orgName }}'s
                system. Click the button below to set your own password and log in.
            </p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                <tr>
                    <td style="border-radius:6px;background:#2563eb;">
                        <a href="{{ $setupUrl }}" target="_blank"
                           style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">
                            Set Your Password
                        </a>
                    </td>
                </tr>
            </table>
            <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#6b7280;">
                If the button doesn't work, copy and paste this link into your browser:<br>
                <a href="{{ $setupUrl }}" style="color:#2563eb;word-break:break-all;">{{ $setupUrl }}</a>
            </p>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">This link expires in 60 minutes for security.</p>
        </td>
    </tr>
    <tr>
        <td style="background:#f9fafb;padding:16px 28px;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb;">
            If you weren't expecting this invitation, you can safely ignore this email.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
