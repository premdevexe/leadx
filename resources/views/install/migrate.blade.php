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
        <div style="width: 100%; max-width: 46rem; padding: 1.5rem 1rem;">
            <div class="auth-card auth-card-wide" style="max-width: 100%;">
                <div class="auth-brand">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX Installation</span>
                </div>

                @include('install.partials.stepper', ['currentStep' => 4])

                <div class="auth-box" style="max-width: none;">
                    <div class="auth-title">Database Installation</div>
                    <div class="auth-subtitle">Run database migrations and seed initial data safely.</div>

                    <div id="migrate-status" style="margin-top: 1.5rem;">
                        <div class="info-box">
                            <strong>Safety & Idempotency Guarantee:</strong>
                            <ul style="margin-top: 0.5rem; margin-left: 1.25rem; list-style: disc;">
                                <li>Creates required database tables via approved Laravel migrations</li>
                                <li>Inserts initial seed data (settings, lookup records)</li>
                                <li><strong>Never drops or wipes tables</strong> (no <code>migrate:fresh</code> or <code>db:wipe</code>)</li>
                                <li>Existing database data will be preserved completely</li>
                            </ul>
                        </div>

                        <form id="migrate-form" method="POST" action="{{ route('install.run-migrate') }}" style="margin-top: 1.5rem;">
                            @csrf
                            <input type="hidden" name="force" value="1">

                            <div class="form-check" style="margin: 1.25rem 0;">
                                <input type="checkbox" id="confirm" name="confirm" required>
                                <label for="confirm" style="font-size: 14px; margin-left: 0.5rem; color: var(--c-neutral-800);">I confirm the database configuration is correct and authorize running forward migrations.</label>
                            </div>

                            <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                                <a href="{{ route('install.database') }}" class="btn btn-secondary">
                                    &larr; Back
                                </a>
                                <button type="submit" class="btn btn-primary" id="migrate-btn" style="padding: 0.5rem 1.5rem;">
                                    Run Migrations &rarr;
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="migrate-result" style="margin-top: 1.5rem; display: none;">
                        <div id="migrate-loading" style="text-align: center; padding: 2rem;">
                            <div style="display: inline-block; width: 2.5rem; height: 2.5rem; border: 3px solid var(--c-neutral-200); border-top-color: var(--c-neutral-900); border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                            <p style="color: var(--c-neutral-600); font-weight: 500; margin-top: 1rem;">Executing database migrations, please wait...</p>
                        </div>
                        <div id="migrate-output" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

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
                        ${data.migration_output ? `
                            <div style="margin-top: 1rem; background: var(--c-neutral-900, #171717); color: #22c55e; padding: 0.75rem 1rem; border-radius: var(--radius-md, 6px); font-family: monospace; font-size: 0.75rem; max-height: 180px; overflow-y: auto; white-space: pre-wrap;">
                                ${data.migration_output}
                            </div>
                        ` : ''}
                        <div style="margin-top: 1.5rem; text-align: right;">
                            <a href="{{ route('install.admin') }}" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">Continue &rarr;</a>
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
                        <div style="margin-top: 1.5rem; display: flex; justify-content: space-between;">
                            <button type="button" class="btn btn-secondary" onclick="location.reload()">&larr; Back to Setup</button>
                            <button type="button" class="btn btn-primary" onclick="location.reload()">Retry Migrations</button>
                        </div>
                    `;
                }
            } catch (err) {
                loading.style.display = 'none';
                output.style.display = 'block';
                output.innerHTML = `
                    <div class="alert alert-danger">
                        <div class="alert-title">Network Exception</div>
                        <div class="alert-message">${err.message}</div>
                    </div>
                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-secondary" onclick="location.reload()">Retry</button>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
