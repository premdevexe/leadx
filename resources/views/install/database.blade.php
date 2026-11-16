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
        <div style="width: 100%; max-width: 46rem; padding: 1.5rem 1rem;">
            <div class="auth-card auth-card-wide" style="max-width: 100%;">
                <div class="auth-brand">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX Installation</span>
                </div>

                @include('install.partials.stepper', ['currentStep' => 3])

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

                    <div id="ajax-result-container">
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
                    </div>

                    @php
                        $formValues = session('db_form_values', []);
                    @endphp

                    <form id="db-form" method="POST" action="{{ route('install.save-database') }}" style="margin-top: 1.5rem;">
                        @csrf

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="db_host" class="form-label form-label-required">Database Host</label>
                                <input type="text" id="db_host" name="db_host" value="{{ old('db_host', $formValues['db_host'] ?? ($env['DB_HOST'] ?? '127.0.0.1')) }}" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="db_port" class="form-label form-label-required">Database Port</label>
                                <input type="number" id="db_port" name="db_port" value="{{ old('db_port', $formValues['db_port'] ?? ($env['DB_PORT'] ?? 3306)) }}" class="form-input" min="1" max="65535" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="db_database" class="form-label form-label-required">Database Name</label>
                            <input type="text" id="db_database" name="db_database" value="{{ old('db_database', $formValues['db_database'] ?? ($env['DB_DATABASE'] ?? 'leadx')) }}" class="form-input" required>
                        </div>

                        <div class="form-row form-row-cols-2">
                            <div class="form-group">
                                <label for="db_username" class="form-label form-label-required">Database Username</label>
                                <input type="text" id="db_username" name="db_username" value="{{ old('db_username', $formValues['db_username'] ?? ($env['DB_USERNAME'] ?? 'root')) }}" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="db_password" class="form-label">Database Password</label>
                                <!-- Password is intentionally never pre-filled from .env: the wizard is
     unauthenticated when the installer is unlocked, and echoing live DB
     credentials into the HTML would leak them to anyone who can browse it. -->
<input type="password" id="db_password" name="db_password" value="{{ old('db_password') }}" class="form-input" placeholder="Leave blank if the username above has no password">
                            </div>
                        </div>

                        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                            <a href="{{ route('install.environment') }}" class="btn btn-secondary">
                                &larr; Back
                            </a>
                            <div style="display: flex; gap: 0.75rem;">
                                <button type="button" id="btn-test-ajax" class="btn btn-secondary">
                                    <span id="test-spinner" style="display: none; margin-right: 0.35rem;">&#9696;</span>
                                    Test Connection
                                </button>
                                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">
                                    Save & Continue &rarr;
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btnTest = document.getElementById('btn-test-ajax');
            var spinner = document.getElementById('test-spinner');
            var resultContainer = document.getElementById('ajax-result-container');
            var form = document.getElementById('db-form');

            btnTest.addEventListener('click', function () {
                var host = document.getElementById('db_host').value;
                var port = document.getElementById('db_port').value;
                var database = document.getElementById('db_database').value;
                var username = document.getElementById('db_username').value;
                var password = document.getElementById('db_password').value;
                var token = document.querySelector('input[name="_token"]').value;

                btnTest.disabled = true;
                spinner.style.display = 'inline-block';
                resultContainer.innerHTML = '';

                fetch('{{ route("install.test-database") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        db_host: host,
                        db_port: port,
                        db_database: database,
                        db_username: username,
                        db_password: password
                    })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    btnTest.disabled = false;
                    spinner.style.display = 'none';

                    var alertClass = data.success ? 'alert-success' : 'alert-danger';
                    var icon = data.success ? '&#10003;' : '&#10007;';
                    var title = data.success ? 'Connection Successful' : 'Connection Failed';

                    var html = '<div class="alert ' + alertClass + '" style="margin-top: 1.25rem;">'
                        + '<span class="alert-icon">' + icon + '</span>'
                        + '<div class="alert-content">'
                        + '<div class="alert-title">' + title + '</div>'
                        + '<div class="alert-message">' + data.message + '</div>';

                    if (data.success && data.version) {
                        html += '<div style="font-size: 0.75rem; margin-top: 0.25rem; color: var(--c-neutral-500);">Server Engine: ' + data.version + '</div>';
                    }

                    if (data.table_count && data.table_count > 0) {
                        html += '<div class="alert-message" style="margin-top: 0.5rem;">'
                            + '<strong>Existing tables (' + data.table_count + '):</strong> '
                            + data.tables.slice(0, 8).join(', ')
                            + (data.table_count > 8 ? ' ... and more' : '')
                            + '</div>'
                            + '<div class="info-box" style="margin-top: 0.5rem;">Existing tables will not be affected.</div>';
                    }

                    html += '</div></div>';
                    resultContainer.innerHTML = html;
                })
                .catch(function (err) {
                    btnTest.disabled = false;
                    spinner.style.display = 'none';
                    resultContainer.innerHTML = '<div class="alert alert-danger" style="margin-top: 1.25rem;">'
                        + '<span class="alert-icon">&#10007;</span>'
                        + '<div class="alert-content"><div class="alert-title">Request Failed</div><div class="alert-message">Could not communicate with installer test endpoint.</div></div></div>';
                });
            });
        });
    </script>
</body>
</html>
