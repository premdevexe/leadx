<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Installation Complete</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body>
    <main class="auth-page">
        <div style="width: 100%; max-width: 32rem; padding: 1.5rem 1rem;">
            <div class="auth-card" style="max-width: 100%; text-align: center;">
                <div style="display: flex; justify-content: center; margin-bottom: 1.25rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: var(--radius-full); border: 2px solid var(--c-green-700); background: var(--c-green-50, #f0fdf4); color: var(--c-green-700);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 1.75rem; height: 1.75rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                </div>

                <div class="auth-brand" style="justify-content: center; margin-bottom: 0.5rem;">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX</span>
                </div>

                <div class="auth-title">Installation complete</div>
                <div class="auth-subtitle">Your LeadX system is configured, verified, and secured for operations.</div>

                <div style="margin: 1.25rem 0;">
                    <span class="badge badge-success" style="padding: 0.35rem 0.75rem; font-size: 0.8125rem;">
                        &#10003; Installer Locked & Protected
                    </span>
                </div>

                @if (session('queue_warning'))
                    @php $qw = session('queue_warning'); @endphp
                    <div class="alert" style="margin-top: 1.25rem; text-align: left; background: var(--c-amber-50, #fffbeb); border-color: var(--c-amber-500, #f59e0b);">
                        <div class="alert-title" style="color: var(--c-amber-700, #b45309);">Queue worker required</div>
                        <div class="alert-message" style="margin-top: 0.5rem; color: var(--c-amber-800, #92400e);">
                            You selected the <strong>{{ $qw['driver'] }}</strong> queue driver, so web‑push and email jobs are queued
                            on the <code>notifications</code> queue. Until a worker processes it, those notifications will
                            not be delivered. Start one with:
                            <div style="margin-top: 0.5rem; background: var(--c-neutral-900, #171717); color: #22c55e; font-family: monospace; font-size: 0.75rem; padding: 0.5rem 0.75rem; border-radius: 4px; white-space: nowrap; overflow-x: auto;">{{ $qw['worker_command'] }}</div>
                        </div>
                    </div>
                @endif

                <div class="alert alert-success" style="margin-top: 1.25rem; text-align: left;">
                    <div class="alert-title">What was completed:</div>
                    <div class="alert-message" style="margin-top: 0.5rem;">
                        <ul style="margin-left: 1.25rem; list-style: disc;">
                            <li>Database migrations applied and seed data initialized</li>
                            <li>Super administrator credentials provisioned</li>
                            <li>Application encryption key secured</li>
                            <li>Storage, cache, and logging systems ready</li>
                            <li>Multi-location installer lock engaged</li>
                        </ul>
                    </div>
                </div>

                @php
                    $displayEmail = $adminEmail ?? $admin_email ?? null;
                @endphp

                @if ($displayEmail)
                    <div class="info-box" style="margin-top: 1rem; text-align: left;">
                        <strong>Admin account:</strong> {{ $displayEmail }}
                    </div>
                @endif

                <div class="info-box" style="margin-top: 1rem; text-align: left;">
                    <strong>Next steps:</strong>
                    <ul style="margin-top: 0.5rem; margin-left: 1.25rem; list-style: disc;">
                        <li>Sign in to the Admin Dashboard at <code>/admin/login</code></li>
                        <li>Configure additional team members or API credentials</li>
                        <li>Review diagnostics at <code>/admin/installer/recovery</code> if needed</li>
                    </ul>
                </div>

                <a href="{{ route('admin.login') }}" class="btn btn-primary" style="margin-top: 1.5rem; width: 100%; justify-content: center;">
                    Go to Admin Login
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px; margin-left: 0.5rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
