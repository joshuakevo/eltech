<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement — {{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary:#0f2444; --accent:#2563eb; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: #f3f4f6; min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 2rem 1rem; }
        .header { background: var(--primary); border-radius: 16px 16px 0 0; padding: 1.5rem 1.75rem; color: #fff; }
        .header .org { font-weight: 700; font-size: 1.05rem; }
        .header .sub { color: rgba(255,255,255,.6); font-size: .8rem; }
        .card-body-custom { background: #fff; border-radius: 0 0 16px 16px; padding: 1.5rem 1.75rem; box-shadow: 0 20px 60px rgba(0,0,0,.08); }
        .period-badge { display: inline-block; background: #eef2ff; color: var(--accent); font-size: .78rem; font-weight: 600; padding: .3rem .7rem; border-radius: 999px; margin-bottom: 1.25rem; }
        .account-row { display: flex; align-items: center; justify-content: space-between; padding: .9rem 0; border-bottom: 1px solid #f3f4f6; }
        .account-row:last-child { border-bottom: none; }
        .account-number { font-family: monospace; font-weight: 600; color: #111827; font-size: .9rem; }
        .account-product { color: #6b7280; font-size: .78rem; }
        .footer-note { color: #9ca3af; font-size: .72rem; text-align: center; margin-top: 1.25rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div class="org">{{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</div>
        <div class="sub">Savings Statement</div>
    </div>
    <div class="card-body-custom">
        <p class="mb-1 fw-semibold">{{ $client->name }}</p>
        <p class="text-muted small mb-2">{{ $client->client_number }}</p>
        <span class="period-badge"><i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</span>

        @forelse($accounts as $account)
        <div class="account-row">
            <div>
                <div class="account-number">{{ $account->account_number }}</div>
                <div class="account-product">{{ $account->product->name ?? 'Savings Account' }}</div>
            </div>
            <a href="{{ $account->pdf_link }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf me-1"></i> View / Download
            </a>
        </div>
        @empty
        <p class="text-muted small py-3 mb-0">No active savings accounts found.</p>
        @endforelse
    </div>
    <p class="footer-note">This link was sent to you by {{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }} and expires automatically. If you did not request this, you can ignore it.</p>
</div>
</body>
</html>
