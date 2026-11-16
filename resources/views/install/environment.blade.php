<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Application Configuration</title>
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
                    <div class="auth-title">Application Configuration</div>
                    <div class="auth-subtitle">Configure your application settings.</div>

                    @php
                        $errorMessages = [];
                        if (isset($errors)) {
                            if ($errors instanceof \Illuminate\Support\ViewErrorBag || $errors instanceof \Illuminate\Support\MessageBag) {
                                $errorMessages = $errors->all();
                            } elseif (is_array($errors)) {
                                $errorMessages = \Illuminate\Support\Arr::flatten($errors);
                            }
                        }
                    @endphp

                    @if (!empty($errorMessages))
                        <div class="error-box" style="margin-top: 1.25rem;">
                            <strong>Please fix the following errors:</strong>
                            <ul style="margin-top: 0.5rem; margin-left: 1.25rem; list-style: disc;">
                                @foreach ($errorMessages as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('install.save-environment') }}" style="margin-top: 1.5rem;">
                        @csrf

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="app_name" class="form-label form-label-required">Application Name</label>
                                <input type="text" id="app_name" name="app_name" value="{{ old('app_name', $env['APP_NAME'] ?? 'LeadX') }}" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="app_url" class="form-label form-label-required">Application URL</label>
                                <input type="url" id="app_url" name="app_url" value="{{ old('app_url', $env['APP_URL'] ?? url('/')) }}" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="timezone" class="form-label form-label-required">Timezone</label>
                                <select id="timezone" name="timezone" class="form-select" required>
                                    @foreach (timezone_identifiers_list() as $tz)
                                        <option value="{{ $tz }}" {{ old('timezone', $env['APP_TIMEZONE'] ?? 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="currency" class="form-label form-label-required">Default Currency Code</label>
                                <input type="text" id="currency" name="currency" value="{{ old('currency', $env['APP_CURRENCY'] ?? 'USD') }}" class="form-input" placeholder="USD, EUR, GBP" required>
                                <p class="form-hint">ISO 4217 currency code</p>
                            </div>
                        </div>

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                <input type="text" id="currency_symbol" name="currency_symbol" value="{{ old('currency_symbol', $env['APP_CURRENCY_SYMBOL'] ?? '$') }}" class="form-input" placeholder="$, €, £, ₹">
                                <p class="form-hint">Display symbol for prices</p>
                            </div>
                            <div class="form-group"></div>
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('install.requirements') }}" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">Continue</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
