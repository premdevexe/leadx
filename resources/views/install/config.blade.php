<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — System Configuration</title>
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

                @include('install.partials.stepper', ['currentStep' => 6])

                <div class="auth-box" style="max-width: none;">
                    <div class="auth-title">System Configuration</div>
                    <div class="auth-subtitle">Configure optional integrations. You can skip these and configure them later from Admin Settings.</div>

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
                            <ul style="margin-left: 1.25rem; list-style: disc;">
                                @foreach ($errorMessages as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('install.config') }}" style="margin-top: 1.5rem;">
                        @csrf

                        <!-- SMTP / Email Section -->
                        <div class="settings-section" style="border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem;">
                            <div class="settings-section-header" style="margin-bottom: 1rem;">
                                <div class="settings-section-title" style="font-size: 14px; font-weight: 600; color: var(--c-neutral-900);">SMTP / Email</div>
                                <div class="settings-section-desc" style="font-size: 12px; color: var(--c-neutral-500);">Configure outgoing transactional email (optional)</div>
                            </div>
                            <div class="settings-section-body">
                                <div class="form-row form-row-cols-2">
                                    <div class="form-group">
                                        <label for="mail_mailer" class="form-label">Mailer</label>
                                        <select id="mail_mailer" name="mail_mailer" class="form-select">
                                            <option value="log" selected>Log (development / testing)</option>
                                            <option value="smtp">SMTP</option>
                                            <option value="sendmail">Sendmail</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="mail_host" class="form-label">SMTP Host</label>
                                        <input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host') }}" class="form-input" placeholder="smtp.mailtrap.io">
                                    </div>
                                </div>
                                <div class="form-row form-row-cols-3" style="margin-top: var(--sp-4);">
                                    <div class="form-group">
                                        <label for="mail_port" class="form-label">Port</label>
                                        <input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', '587') }}" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label for="mail_username" class="form-label">Username</label>
                                        <input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username') }}" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label for="mail_password" class="form-label">Password</label>
                                        <input type="password" id="mail_password" name="mail_password" value="{{ old('mail_password') }}" class="form-input">
                                    </div>
                                </div>
                                <div class="form-row form-row-cols-2" style="margin-top: var(--sp-4);">
                                    <div class="form-group">
                                        <label for="mail_encryption" class="form-label">Encryption</label>
                                        <select id="mail_encryption" name="mail_encryption" class="form-select">
                                            <option value="">None</option>
                                            <option value="tls" selected>TLS</option>
                                            <option value="ssl">SSL</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="mail_from_address" class="form-label">From Address</label>
                                        <input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address') }}" class="form-input" placeholder="notifications@leadx.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pusher Real-time Notifications Section -->
                        <div class="settings-section" style="border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem;">
                            <div class="settings-section-header" style="margin-bottom: 1rem;">
                                <div class="settings-section-title" style="font-size: 14px; font-weight: 600; color: var(--c-neutral-900);">Pusher (Real-time Notifications)</div>
                                <div class="settings-section-desc" style="font-size: 12px; color: var(--c-neutral-500);">Enable real-time push notifications (optional)</div>
                            </div>
                            <div class="settings-section-body">
                                <div class="form-row form-row-cols-2">
                                    <div class="form-group">
                                        <label for="pusher_app_id" class="form-label">App ID</label>
                                        <input type="text" id="pusher_app_id" name="pusher_app_id" value="{{ old('pusher_app_id') }}" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label for="pusher_app_key" class="form-label">App Key</label>
                                        <input type="text" id="pusher_app_key" name="pusher_app_key" value="{{ old('pusher_app_key') }}" class="form-input">
                                    </div>
                                </div>
                                <div class="form-row form-row-cols-2" style="margin-top: var(--sp-4);">
                                    <div class="form-group">
                                        <label for="pusher_app_secret" class="form-label">App Secret</label>
                                        <input type="text" id="pusher_app_secret" name="pusher_app_secret" value="{{ old('pusher_app_secret') }}" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label for="pusher_app_cluster" class="form-label">Cluster</label>
                                        <input type="text" id="pusher_app_cluster" name="pusher_app_cluster" value="{{ old('pusher_app_cluster', 'mt1') }}" class="form-input">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Queue Driver -->
                        <div class="settings-section" style="border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem;">
                            <div class="settings-section-header" style="margin-bottom: 1rem;">
                                <div class="settings-section-title" style="font-size: 14px; font-weight: 600; color: var(--c-neutral-900);">Asynchronous Queue Driver</div>
                                <div class="settings-section-desc" style="font-size: 12px; color: var(--c-neutral-500);">Select background job execution driver</div>
                            </div>
                            <div class="settings-section-body">
                                <div class="form-group">
                                    <label for="queue_driver" class="form-label">Driver</label>
                                    <select id="queue_driver" name="queue_driver" class="form-select">
                                        <option value="sync" selected>sync (Immediate synchronous processing)</option>
                                        <option value="database">database (Background queue worker - recommended)</option>
                                        <option value="redis">redis (High-performance Redis queue)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                            <a href="{{ route('install.admin') }}" class="btn btn-secondary">
                                &larr; Back
                            </a>
                            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">
                                Continue &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
