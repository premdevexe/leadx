<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Database Installation</title>
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
                    <div class="auth-title">Database Installation</div>
                    <div class="auth-subtitle">Run database migrations and seed initial data.</div>

                    <div id="migrate-status" style="margin-top: 1.5rem;">
                        <div class="info-box">
                            <strong>What happens here:</strong>
                            <ul style="margin-top: 0.5rem; margin-left: 1.25rem; list-style: disc;">
                                <li>Creates all required database tables via migrations</li>
                                <li>Inserts initial seed data (permissions, settings, etc.)</li>
                                <li>Does NOT drop or modify existing tables</li>
                                <li>Existing data will not be affected</li>
                            </ul>
                        </div>

                        <form id="migrate-form" method="POST" action="{{ route('install.run-migrate') }}">
                            @csrf
                            <input type="hidden" name="force" value="1">

                            <div class="form-check" style="margin: 1.25rem 0;">
                                <input type="checkbox" id="confirm" name="confirm" required>
                                <label for="confirm" style="font-size: 14px;">I understand this will create database tables and confirm the database is correct.</label>
                            </div>

                            <div class="form-actions">
                                <a href="{{ route('install.database') }}" class="btn btn-secondary">Back</a>
                                <button type="submit" class="btn btn-primary" id="migrate-btn">Run Migrations</button>
                            </div>
                        </form>
                    </div>

                    <div id="migrate-result" style="margin-top: 1.5rem; display: none;">
                        <div id="migrate-loading" style="text-align: center; padding: 2rem;">
                            <div class="spinner spinner-lg" style="margin: 0 auto 1rem;"></div>
                            <p style="color: #737373;">Running migrations, please wait...</p>
                        </div>
                        <div id="migrate-output" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('migrate-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('migrate-btn');
            const status = document.getElementById('migrate-status');
            const result = document.getElementById('migrate-result');
            const loading = document.getElementById('migrate-loading');
            const output = document.getElementById('migrate-output');

            btn.disabled = true;
            btn.textContent = 'Running...';
            status.style.display = 'none';
            result.style.display = 'block';

            try {
                const res = await fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ force: 1 }),
                });

                const data = await res.json();

                loading.style.display = 'none';
                output.style.display = 'block';

                if (data.success) {
                    output.innerHTML = `
                        <div class="alert alert-success">
                            <span class="alert-icon">&#10003;</span>
                            <div class="alert-content">
                                <div class="alert-title">${data.message}</div>
                                ${data.seeded ? '<div class="alert-message">Initial data seeded successfully.</div>' : ''}
                            </div>
                        </div>
                        <div class="form-actions" style="margin-top: 1rem;">
                            <a href="{{ route('install.admin') }}" class="btn btn-primary">Continue</a>
                        </div>
                    `;
                } else {
                    output.innerHTML = `
                        <div class="alert alert-danger">
                            <span class="alert-icon">&#10007;</span>
                            <div class="alert-content">
                                <div class="alert-title">Migration Failed</div>
                                <div class="alert-message">${data.message}</div>
                            </div>
                        </div>
                        <div class="form-actions" style="margin-top: 1rem;">
                            <button type="button" class="btn btn-secondary" onclick="location.reload()">Try Again</button>
                        </div>
                    `;
                }
            } catch (err) {
                loading.style.display = 'none';
                output.style.display = 'block';
                output.innerHTML = `
                    <div class="alert alert-danger">
                        <div class="alert-title">Error</div>
                        <div class="alert-message">${err.message}</div>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
