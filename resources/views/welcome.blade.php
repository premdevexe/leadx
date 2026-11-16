<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Models\Setting::siteName() }} — Sales & Order Lifecycle System</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body style="min-height: 100vh; display: flex; flex-direction: column; background: var(--bg-surface); color: var(--text-color);">
    <header style="border-bottom: var(--border-width) solid var(--border-color); background: var(--c-white);">
        <div style="max-width: 64rem; margin: 0 auto; padding: var(--sp-3) var(--sp-4); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: var(--sp-2);">
                <span class="auth-brand-icon">L</span>
                <span style="font-size: var(--text-base); font-weight: 600; letter-spacing: -0.01em;">{{ \App\Models\Setting::siteName() }}</span>
            </div>
            <div style="display: flex; align-items: center; gap: var(--sp-2);">
                <span class="status-pip" style="background: var(--c-green-500);" title="System online"></span>
                <span style="font-size: var(--text-xs); color: var(--c-neutral-500);">System operational</span>
            </div>
        </div>
    </header>

    <main style="flex: 1; max-width: 64rem; width: 100%; margin: 0 auto; padding: var(--sp-8) var(--sp-4);">
        <div style="margin-bottom: var(--sp-8);">
            <h1 style="font-size: var(--text-2xl); font-weight: 600; letter-spacing: -0.02em; color: var(--c-neutral-900);">
                Operational sales CRM & order lifecycle
            </h1>
            <p style="margin-top: var(--sp-1); font-size: var(--text-sm); color: var(--c-neutral-500); max-width: 40rem;">
                Disciplined order verification, live revenue collections, and subscription continuity for sales teams and management.
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--sp-4); margin-bottom: var(--sp-8);">
            <div class="card" style="padding: var(--sp-5); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--sp-2);">
                        <span style="font-size: var(--text-xs); font-weight: 600; color: var(--c-neutral-900);">Admin Portal</span>
                        <span class="badge badge-neutral">Management</span>
                    </div>
                    <p style="font-size: var(--text-xs); color: var(--c-neutral-500); margin-bottom: var(--sp-4);">
                        Financial governance, verified receipts audit, catalog configuration, and team permissions.
                    </p>
                </div>
                <a href="{{ route('admin.login') }}" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                    Admin Login &rarr;
                </a>
            </div>

            <div class="card" style="padding: var(--sp-5); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--sp-2);">
                        <span style="font-size: var(--text-xs); font-weight: 600; color: var(--c-neutral-900);">Employee Portal</span>
                        <span class="badge badge-neutral">Sales</span>
                    </div>
                    <p style="font-size: var(--text-xs); color: var(--c-neutral-500); margin-bottom: var(--sp-4);">
                        Order intake, customer client management, scheduled follow-ups, and live collections tracking.
                    </p>
                </div>
                <a href="{{ route('login') }}" class="btn btn-secondary" style="width: 100%; text-align: center; justify-content: center;">
                    Employee Login &rarr;
                </a>
            </div>

            <div class="card" style="padding: var(--sp-5); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--sp-2);">
                        <span style="font-size: var(--text-xs); font-weight: 600; color: var(--c-neutral-900);">Team Portal</span>
                        <span class="badge badge-neutral">Operations</span>
                    </div>
                    <p style="font-size: var(--text-xs); color: var(--c-neutral-500); margin-bottom: var(--sp-4);">
                        Team-scoped order submission, collective revenue tracking, and member performance.
                    </p>
                </div>
                <a href="{{ route('team.login') }}" class="btn btn-secondary" style="width: 100%; text-align: center; justify-content: center;">
                    Team Login &rarr;
                </a>
            </div>
        </div>

        <div class="card" style="padding: var(--sp-5); background: var(--c-white);">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: var(--border-width) solid var(--border-color); padding-bottom: var(--sp-3); margin-bottom: var(--sp-3);">
                <div style="display: flex; align-items: center; gap: var(--sp-2);">
                    <span class="status-pip" style="background: var(--c-green-500);"></span>
                    <span style="font-size: var(--text-xs); font-weight: 600; color: var(--c-neutral-800);">System Ledger Snapshot</span>
                </div>
                <span style="font-size: var(--text-xs); color: var(--c-neutral-400); font-family: var(--font-mono);">LEADX-CORE</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--sp-4); font-size: var(--text-xs);">
                <div>
                    <div style="color: var(--c-neutral-400);">Accounting standard</div>
                    <div style="font-weight: 500; color: var(--c-neutral-800); margin-top: var(--sp-0-5);">Verified Receipts & Clearances</div>
                </div>
                <div>
                    <div style="color: var(--c-neutral-400);">Currency precision</div>
                    <div style="font-weight: 500; color: var(--c-neutral-800); margin-top: var(--sp-0-5); font-family: var(--font-mono);">{{ \App\Models\Setting::currency() }} ({{ \App\Models\Setting::currencySymbol() }})</div>
                </div>
                <div>
                    <div style="color: var(--c-neutral-400);">Interface engine</div>
                    <div style="font-weight: 500; color: var(--c-neutral-800); margin-top: var(--sp-0-5);">Vanilla CSS 3.0 Tokens</div>
                </div>
            </div>
        </div>
    </main>

    <footer style="border-top: var(--border-width) solid var(--border-color); padding: var(--sp-4); text-align: center; font-size: var(--text-xs); color: var(--c-neutral-400);">
        &copy; {{ date('Y') }} {{ \App\Models\Setting::siteName() }}. Disciplined sales & revenue operations.
    </footer>
</body>
</html>