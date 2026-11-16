<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Installation</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body>
    <main class="auth-page">
        <div style="width: 100%; max-width: 42rem;">
            <div class="auth-card auth-card-wide" style="max-width: 100%;">
                <div class="auth-brand">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX Installation</span>
                </div>
                <div class="auth-box" style="max-width: none;">
                    <div class="auth-title">Environment Requirements</div>
                    <div class="auth-subtitle">Checking your server meets minimum requirements for LeadX.</div>

                    <div style="margin-top: 1.5rem;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Requirement</th>
                                    <th style="width: 10rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($checks as $label => $check)
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;">{{ $label }}</div>
                                            <div style="font-size: 12px; color: #737373;">{{ $check['value'] }}</div>
                                        </td>
                                        <td>
                                            @if ($check['pass'])
                                                <span class="badge badge-success">Pass</span>
                                            @else
                                                <span class="badge badge-danger">Fail</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                        @if ($allPass)
                            <a href="{{ route('install.environment') }}" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">
                                Continue
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        @else
                            <span style="color: #dc2626; font-size: 14px; font-weight: 500;">Please fix the failing requirements before continuing.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
