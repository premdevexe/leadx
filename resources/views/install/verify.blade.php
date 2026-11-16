<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Installation Verification</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body>
    <main class="auth-page">
        <div style="width: 100%; max-width: 46rem; padding: 1.5rem 1rem;">
            <div class="auth-card auth-card-wide" style="max-width: 100%;">
                <div class="auth-brand">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX Installation</span>
                </div>

                @include('install.partials.stepper', ['currentStep' => 7])

                <div class="auth-box" style="max-width: none;">
                    <div class="auth-title">Final Verification</div>
                    <div class="auth-subtitle">Verifying installation health and security before completing setup.</div>

                    <div style="margin-top: 1.5rem;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th style="width: 8rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($checks as $label => $check)
                                    @php
                                        $pass = is_array($check) ? ($check['pass'] ?? false) : (bool) $check;
                                        $val = is_array($check) ? ($check['value'] ?? ($pass ? 'Passed' : 'Failed')) : ($check ? 'Passed' : 'Failed');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;">{{ $label }}</div>
                                            <div style="font-size: 12px; color: #737373;">{{ $val }}</div>
                                        </td>
                                        <td>
                                            @if ($pass)
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

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                        <a href="{{ route('install.config') }}" class="btn btn-secondary">
                            &larr; Back
                        </a>
                        @if ($allPass)
                            <form method="POST" action="{{ route('install.finish') }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">
                                    Complete Installation &rarr;
                                </button>
                            </form>
                        @else
                            <span style="color: #dc2626; font-size: 14px; font-weight: 500;">Please resolve the failing checks before finishing.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
