<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Database Configuration</title>
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
                    <div class="auth-title">Database Configuration</div>
                    <div class="auth-subtitle">Configure your MySQL/MariaDB database connection.</div>

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

                    @if (session('db_test_result'))
                        <div class="alert {{ session('db_test_result.success') ? 'alert-success' : 'alert-danger' }}" style="margin-top: 1.25rem;">
                            <span class="alert-icon">{{ session('db_test_result.success') ? '&#10003;' : '&#10007;' }}</span>
                            <div class="alert-content">
                                <div class="alert-title">{{ session('db_test_result.success') ? 'Connection Successful' : 'Connection Failed' }}</div>
                                <div class="alert-message">{{ session('db_test_result.message') }}</div>
                                @if (session('db_test_result.table_count') > 0)
                                    <div class="alert-message" style="margin-top: 0.5rem;">
                                        <strong>Existing tables ({{ session('db_test_result.table_count') }}):</strong>
                                        {{ implode(', ', array_slice(session('db_test_result.tables'), 0, 10)) }}
                                        @if (session('db_test_result.table_count') > 10)
                                            ... and {{ session('db_test_result.table_count') - 10 }} more
                                        @endif
                                    </div>
                                    <div class="info-box" style="margin-top: 0.5rem;">
                                        These tables will not be affected. LeadX migrations will only create new tables.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('install.database') }}" id="db-form" style="margin-top: 1.25rem;">
                        @csrf

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="db_host" class="form-label form-label-required">Database Host</label>
                                <input type="text" id="db_host" name="db_host" value="{{ old('db_host', session('db_form_values.db_host', $env['DB_HOST'] ?? '127.0.0.1')) }}" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="db_port" class="form-label form-label-required">Port</label>
                                <input type="number" id="db_port" name="db_port" value="{{ old('db_port', session('db_form_values.db_port', $env['DB_PORT'] ?? '3306')) }}" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="db_database" class="form-label form-label-required">Database Name</label>
                            <input type="text" id="db_database" name="db_database" value="{{ old('db_database', session('db_form_values.db_database', $env['DB_DATABASE'] ?? 'leadx')) }}" class="form-input" required>
                        </div>

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="db_username" class="form-label form-label-required">Username</label>
                                <input type="text" id="db_username" name="db_username" value="{{ old('db_username', session('db_form_values.db_username', $env['DB_USERNAME'] ?? 'root')) }}" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="db_password" class="form-label">Password</label>
                                <input type="password" id="db_password" name="db_password" value="{{ old('db_password', $env['DB_PASSWORD'] ?? '') }}" class="form-input" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('install.environment') }}" class="btn btn-secondary">Back</a>
                            <div style="display: flex; gap: var(--sp-2);">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('db-form').action='{{ route('install.test-database') }}'; document.getElementById('db-form').submit();">
                                    Test Connection
                                </button>
                                <button type="submit" class="btn btn-primary">Continue</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
