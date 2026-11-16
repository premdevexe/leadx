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
        <div style="width: 100%; max-width: 42rem;">
            <div class="auth-card auth-card-wide" style="max-width: 100%;">
                <div class="auth-brand">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX Installation</span>
                </div>
                <div class="auth-box" style="max-width: none;">
                    <div class="auth-title">System Configuration</div>
                    <div class="auth-subtitle">Configure optional integrations. You can skip these and configure them later from Settings.</div>

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

                        <div class="settings-section">
                            <div class="settings-section-header" style="padding: var(--sp-3) var(--sp-4);">
                                <div>
                                    <div class="settings-section-title" style="font-size: 14px;">SMTP / Email</div>
                                    <div class="settings-section-desc">Configure outgoing email (optional — can be set later)</div>
                                </div>
                            </div>
                            <div class="settings-section-body">
                                <div class="form-row form-row-cols-2">
                                    <div class="form-group">
                                        <label for="mail_mailer" class="form-label">Mailer</label>
                                        <select id="mail_mailer" name="mail_mailer" class="form-select">
                                            <option value="log">Log (development)</option>
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
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="mail_from_address" class="form-label">From Address</label>
                                        <input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address') }}" class="form-input">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section" style="margin-top: var(--sp-5);">
                            <div class="settings-section-header" style="padding: var(--sp-3) var(--sp-4);">
                                <div>
                                    <div class="settings-section-title" style="font-size: 14px;">Pusher (Real-time Notifications)</div>
                                    <div class="settings-section-desc">Enable real-time notifications (optional)</div>
                                </div>
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
                                        <input type="text" id="pusher_app_cluster" name="pusher_app_cluster" value="{{ old('pusher_app_cluster') }}" class="form-input" placeholder="mt1">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section" style="margin-top: var(--sp-5);">
                            <div class="settings-section-header" style="padding: var(--sp-3) var(--sp-4);">
                                <div>
                                    <div class="settings-section-title" style="font-size: 14px;">Google Calendar</div>
                                    <div class="settings-section-desc">Enable Google Calendar integration (optional)</div>
                                </div>
                            </div>
                            <div class="settings-section-body">
                                <div class="form-row form-row-cols-2">
                                    <div class="form-group">
                                        <label for="google_client_id" class="form-label">Client ID</label>
                                        <input type="text" id="google_client_id" name="google_client_id" value="{{ old('google_client_id') }}" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label for="google_client_secret" class="form-label">Client Secret</label>
                                        <input type="text" id="google_client_secret" name="google_client_secret" value="{{ old('google_client_secret') }}" class="form-input">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: var(--sp-6);">
                            <a href="{{ route('install.admin') }}" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">Continue</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
