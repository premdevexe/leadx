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
        <div style="width: 100%; max-width: 28rem;">
            <div class="auth-card" style="max-width: 100%; text-align: center;">
                <div style="display: flex; justify-content: center; margin-bottom: 1.25rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; border-radius: var(--radius-full); border: 1px solid var(--c-neutral-200); background: var(--c-neutral-50); color: var(--c-neutral-900);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                </div>
                <div class="auth-brand" style="justify-content: center; margin-bottom: 1rem;">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX</span>
                </div>
                <div class="auth-title">Installation complete</div>
                <div class="auth-subtitle">Your LeadX system is configured and ready for operations.</div>

                <div class="alert alert-success" style="margin-top: 1.5rem; text-align: left;">
                    <div class="alert-title">What was installed:</div>
                    <div class="alert-message" style="margin-top: 0.5rem;">
                        <ul style="margin-left: 1.25rem; list-style: disc;">
                            <li>Database migrations and seed data</li>
                            <li>Super administrator account</li>
                            <li>Application configuration</li>
                            <li>Storage and cache directories</li>
                        </ul>
                    </div>
                </div>

                @if ($admin_email)
                    <div class="info-box" style="margin-top: 1rem; text-align: left;">
                        <strong>Admin account:</strong> {{ $admin_email }}
                    </div>
                @endif

                <div class="info-box" style="margin-top: 1rem; text-align: left;">
                    <strong>Next steps:</strong>
                    <ul style="margin-top: 0.5rem; margin-left: 1.25rem; list-style: disc;">
                        <li>Visit <code>/admin/login</code> to access the admin panel</li>
                        <li>Configure integrations from Admin → Settings</li>
                        <li>Set up scheduled tasks if needed</li>
                    </ul>
                </div>

                <a href="{{ route('admin.login') }}" class="btn btn-primary" style="margin-top: 1.5rem; width: 100%;">
                    Go to Admin Login
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
