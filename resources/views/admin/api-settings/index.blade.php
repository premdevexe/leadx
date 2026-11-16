@extends('layouts.admin-dashboard')

@section('title', 'API Integrations & Credentials')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">API Integrations & Credentials</h1>
            <p class="mt-1 text-sm text-neutral-500">Dynamically configure external service keys, webhooks, and LeadX API access tokens.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-success badge-dot">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Database-Backed Dynamic Overlay
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-sm border border-emerald-200 flex items-center justify-between">
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="mt-4 alert alert-danger text-sm border border-red-200">
            {{ session('error') }}
        </div>
    @endif

    @if (session('new_token'))
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-xs">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-amber-800">Your New API Token: {{ session('new_token_name') }}</h3>
                    <p class="mt-1 text-xs text-amber-700">Make sure to copy your personal access token now. You will not be able to see it again!</p>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <input id="new-token-input" type="text" readonly value="{{ session('new_token') }}"
                       class="form-input w-full font-mono font-medium">
                <button type="button" onclick="navigator.clipboard.writeText('{{ session('new_token') }}'); this.innerText = 'Copied!'; setTimeout(() => this.innerText = 'Copy', 2000)"
                        class="btn px-4 py-2 text-sm font-medium text-white" style="background-color:#92400e;border-color:#92400e">
                    Copy
                </button>
            </div>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <nav class="mt-5 flex flex-wrap gap-1 border-b border-neutral-200" role="tablist">
        <button type="button" onclick="switchTab('pusher')" id="tab-pusher-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-primary">Pusher Beams</button>
        <button type="button" onclick="switchTab('google')" id="tab-google-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-secondary text-neutral-500">Google OAuth</button>
        <button type="button" onclick="switchTab('whatsapp')" id="tab-whatsapp-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-secondary text-neutral-500">WhatsApp API</button>
    </nav>

    {{-- TAB 1 – PUSHER BEAMS --}}
    <div id="tab-pusher" class="tab-content mt-5 space-y-5">
        <form method="POST" action="{{ route('admin.api-settings.pusher.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">Pusher Beams Web Push Credentials</h2>
                        <p class="mt-1 text-xs text-neutral-500">Delivers instantaneous in-browser notifications to active sales reps and managers.</p>
                    </div>
                    <label class="form-check text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($pusher['is_enabled'] ?? true) class="rounded border-neutral-300">
                        <span>Enable Pusher Push</span>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="pusher_instance_id" class="form-label">Instance ID *</label>
                        <input id="pusher_instance_id" name="instance_id" type="text" value="{{ old('instance_id', $pusher['instance_id'] ?? '') }}" required
                               placeholder="e.g. 709beee2-c2e7-48f1-a1e4-xxxxxxxxxxxx"
                               class="form-input font-mono">
                        @error('instance_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="pusher_cluster" class="form-label">Cluster *</label>
                        <input id="pusher_cluster" name="cluster" type="text" value="{{ old('cluster', $pusher['cluster'] ?? 'ap2') }}" required
                               placeholder="e.g. ap2, us1, eu1"
                               class="form-input font-mono">
                        @error('cluster') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="pusher_server_key" class="form-label">Server / Secret Key</label>
                        <input id="pusher_server_key" name="server_key" type="password"
                               placeholder="{{ !empty($pusher['server_key']) ? '•••••••••••• (leave blank to keep current secret)' : 'Enter Pusher Secret Key' }}"
                               class="form-input font-mono">
                        @if(!empty($maskedPusherSecret))
                            <p class="form-hint font-mono">Current secret: {{ $maskedPusherSecret }}</p>
                        @endif
                        @error('server_key') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Save Pusher Settings
                </button>
            </div>
        </form>
    </div>

    {{-- TAB 2 – GOOGLE OAUTH --}}
    <div id="tab-google" class="tab-content mt-5 hidden space-y-5">
        <form method="POST" action="{{ route('admin.api-settings.google.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">Google OAuth 2.0 & Calendar API</h2>
                        <p class="mt-1 text-xs text-neutral-500">Allows sales reps to connect personal Google Calendars for automatic subscription expiry synchronization.</p>
                    </div>
                    <label class="form-check text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($google['is_enabled'] ?? true) class="rounded border-neutral-300">
                        <span>Enable Google Calendar Sync</span>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="google_client_id" class="form-label">Client ID *</label>
                        <input id="google_client_id" name="client_id" type="text" value="{{ old('client_id', $google['client_id'] ?? '') }}" required
                               placeholder="e.g. 123456789-xxxxxxxx.apps.googleusercontent.com"
                               class="form-input font-mono">
                        @error('client_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="google_client_secret" class="form-label">Client Secret</label>
                        <input id="google_client_secret" name="client_secret" type="password"
                               placeholder="{{ !empty($google['client_secret']) ? '•••••••••••• (leave blank to keep current secret)' : 'Enter Client Secret' }}"
                               class="form-input font-mono">
                        @if(!empty($maskedGoogleSecret))
                            <p class="form-hint font-mono">Current secret: {{ $maskedGoogleSecret }}</p>
                        @endif
                        @error('client_secret') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="google_redirect_uri" class="form-label">Authorized Redirect URI *</label>
                        <input id="google_redirect_uri" name="redirect_uri" type="url" value="{{ old('redirect_uri', $google['redirect_uri'] ?? url('/api/v1/google/calendar/callback')) }}" required
                               class="form-input font-mono">
                        <p class="form-hint">Must exactly match the authorized redirect URI in your Google Cloud Console OAuth 2.0 Credentials. Must be <code class="font-mono bg-neutral-100 px-1 rounded">{{ url('/api/v1/google/calendar/callback') }}</code> (the OAuth callback endpoint).</p>
                        @error('redirect_uri') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="google_api_key" class="form-label">Google API Key (Optional)</label>
                        <input id="google_api_key" name="api_key" type="password"
                               placeholder="{{ !empty($google['api_key']) ? '•••••••••••• (leave blank to keep current key)' : 'Enter Google API Key (optional)' }}"
                               class="form-input font-mono">
                        @if(!empty($maskedGoogleApiKey))
                            <p class="form-hint font-mono">Current key: {{ $maskedGoogleApiKey }}</p>
                        @endif
                        <p class="form-hint">Sent strictly via the <code class="font-mono bg-neutral-100 px-1 py-0.5 rounded">X-Goog-Api-Key</code> HTTP header — never exposed as a URL query parameter.</p>
                        @error('api_key') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-xs text-blue-900 space-y-1.5">
                    <div class="flex items-center gap-1.5 font-semibold" style="color:var(--c-blue-950)">
                        <svg class="h-4 w-4" style="color:var(--c-blue-600)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Google Cloud API Key & OAuth Security Best Practices Enforced
                    </div>
                    <ul class="list-disc pl-5 space-y-1 text-blue-800">
                        <li><strong>Header Transmission:</strong> API keys are transmitted exclusively via the <code class="font-mono bg-blue-100 px-1 rounded">X-Goog-Api-Key</code> HTTP header — never exposed in URL query parameters.</li>
                        <li><strong>Short-Lived Credentials:</strong> OAuth access tokens expire hourly and auto-refresh in the background using AES-256 encrypted refresh tokens.</li>
                        <li><strong>Per-Employee Isolation:</strong> Every team member connects their own Google account, providing an explicit audit trail in Google Cloud Monitoring.</li>
                        <li><strong>Instant Revocation:</strong> Disconnecting a Google account revokes the token directly on Google's authorization servers.</li>
                        <li><strong>Least-Privilege Scopes:</strong> Access is restricted strictly to calendar event creation and user email identity.</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Save Google Credentials
                </button>
            </div>
        </form>
    </div>

    {{-- TAB 3 – WHATSAPP API --}}
    <div id="tab-whatsapp" class="tab-content mt-5 hidden space-y-5">
        <form method="POST" action="{{ route('admin.api-settings.whatsapp.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">WhatsApp Integration</h2>
                        <p class="mt-1 text-xs text-neutral-500">Configure customer order confirmation and API credential delivery links.</p>
                    </div>
                    <label class="form-check text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($whatsapp['is_enabled'] ?? true) class="rounded border-neutral-300">
                        <span>Enable WhatsApp Actions</span>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="whatsapp_provider" class="form-label">Integration Mode *</label>
                        <select id="whatsapp_provider" name="provider"
                                class="form-select mt-1 w-full">
                            <option value="click_to_chat" @selected(($whatsapp['provider'] ?? '') === 'click_to_chat')>One-Click Web Link (wa.me) — Recommended</option>
                            <option value="cloud_api" @selected(($whatsapp['provider'] ?? '') === 'cloud_api')>Meta WhatsApp Cloud API</option>
                        </select>
                        @error('provider') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="whatsapp_base_url" class="form-label">Base URL *</label>
                        <input id="whatsapp_base_url" name="base_url" type="url" value="{{ old('base_url', $whatsapp['base_url'] ?? 'https://wa.me') }}" required
                               class="form-input font-mono">
                        @error('base_url') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="whatsapp_message_template" class="form-label">Message Template *</label>
                        <textarea id="whatsapp_message_template" name="message_template" rows="3" required
                                  class="form-input">{{ old('message_template', $whatsapp['message_template'] ?? '') }}</textarea>
                        <p class="form-hint">Available placeholders: <code class="bg-neutral-100 px-1 rounded text-xs">{order_number}</code>, <code class="bg-neutral-100 px-1 rounded text-xs">{customer_name}</code>, <code class="bg-neutral-100 px-1 rounded text-xs">{product_name}</code>, <code class="bg-neutral-100 px-1 rounded text-xs">{closing_value}</code>, <code class="bg-neutral-100 px-1 rounded text-xs">{api_key}</code></p>
                        @error('message_template') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2 pt-2 border-t border-neutral-100">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Optional: Meta WhatsApp Cloud API Credentials</h3>
                    </div>

                    <div>
                        <label for="whatsapp_phone_number_id" class="form-label">Phone Number ID</label>
                        <input id="whatsapp_phone_number_id" name="phone_number_id" type="text" value="{{ old('phone_number_id', $whatsapp['phone_number_id'] ?? '') }}"
                               placeholder="e.g. 104593829482910"
                               class="form-input font-mono">
                    </div>

                    <div>
                        <label for="whatsapp_access_token" class="form-label">Permanent Access Token</label>
                        <input id="whatsapp_access_token" name="access_token" type="password"
                               placeholder="{{ !empty($whatsapp['access_token']) ? '•••••••••••• (leave blank to keep current token)' : 'Enter Meta System User Token' }}"
                               class="form-input font-mono">
                        @if(!empty($maskedWhatsappToken))
                            <p class="form-hint font-mono">Current token: {{ $maskedWhatsappToken }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Save WhatsApp Settings
                </button>
            </div>
        </form>
    </div>

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(function(el) {
            el.classList.add('hidden');
        });
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary', 'text-neutral-500');
        });

        const panel = document.getElementById('tab-' + tab);
        const btn = document.getElementById('tab-' + tab + '-btn');
        if (panel) panel.classList.remove('hidden');
        if (btn) {
            btn.classList.remove('btn-secondary', 'text-neutral-500');
            btn.classList.add('btn-primary');
        }
        if (window.history && window.history.replaceState) {
            try {
                window.history.replaceState(null, '', '#' + tab);
            } catch (e) {}
        }
    }
    window.switchTab = switchTab;

    document.addEventListener('DOMContentLoaded', function () {
        @if(session('active_tab'))
            switchTab('{{ session('active_tab') }}');
        @else
            const hash = (window.location.hash || '').replace('#', '');
            if (hash && document.getElementById('tab-' + hash)) {
                switchTab(hash);
            }
        @endif

        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const tabId = this.id.replace('tab-', '').replace('-btn', '');
                if (tabId) {
                    switchTab(tabId);
                }
            });
        });
    });
    </script>
@endsection
