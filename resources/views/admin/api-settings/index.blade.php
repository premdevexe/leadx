@extends('layouts.admin-dashboard')

@section('title', 'API Integrations & Credentials')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">API Integrations & Credentials</h1>
            <p class="mt-1 text-sm text-neutral-500">Dynamically configure external service keys, webhooks, and LeadX API access tokens.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 border border-emerald-200">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Database-Backed Dynamic Overlay
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 border border-emerald-200 flex items-center justify-between">
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 border border-red-200">
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
                       class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-mono font-medium text-neutral-900 selection:bg-amber-200 focus:outline-none">
                <button type="button" onclick="navigator.clipboard.writeText('{{ session('new_token') }}'); this.innerText = 'Copied!'; setTimeout(() => this.innerText = 'Copy', 2000)"
                        class="rounded-lg bg-amber-800 px-4 py-2 text-sm font-medium text-white hover:bg-amber-900 transition-colors">
                    Copy
                </button>
            </div>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <nav class="mt-5 flex flex-wrap gap-1 border-b border-neutral-200" role="tablist">
        <button type="button" onclick="switchTab('pusher')" id="tab-pusher-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg bg-neutral-900 text-white">Pusher Beams</button>
        <button type="button" onclick="switchTab('google')" id="tab-google-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Google OAuth</button>
        <button type="button" onclick="switchTab('whatsapp')" id="tab-whatsapp-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">WhatsApp API</button>
        <button type="button" onclick="switchTab('gateway')" id="tab-gateway-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Payment Gateway</button>
        <button type="button" onclick="switchTab('webhooks')" id="tab-webhooks-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Outbound Webhooks</button>
        <button type="button" onclick="switchTab('tokens')" id="tab-tokens-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">API Access Tokens</button>
    </nav>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 1 – PUSHER BEAMS (Web Push API)
         ════════════════════════════════════════════════════════════════ --}}
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
                    <label class="flex items-center gap-2 text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($pusher['is_enabled'] ?? true) class="rounded border-neutral-300">
                        Enable Pusher Push
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="pusher_instance_id" class="text-sm font-medium text-neutral-800">Instance ID *</label>
                        <input id="pusher_instance_id" name="instance_id" type="text" value="{{ old('instance_id', $pusher['instance_id'] ?? '') }}" required
                               placeholder="e.g. 709beee2-c2e7-48f1-a1e4-xxxxxxxxxxxx"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('instance_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="pusher_cluster" class="text-sm font-medium text-neutral-800">Cluster *</label>
                        <input id="pusher_cluster" name="cluster" type="text" value="{{ old('cluster', $pusher['cluster'] ?? 'ap2') }}" required
                               placeholder="e.g. ap2, us1, eu1"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('cluster') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="pusher_server_key" class="text-sm font-medium text-neutral-800">Server / Secret Key</label>
                        <input id="pusher_server_key" name="server_key" type="password"
                               placeholder="{{ !empty($pusher['server_key']) ? '•••••••••••• (leave blank to keep current secret)' : 'Enter Pusher Secret Key' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedPusherSecret))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current secret: {{ $maskedPusherSecret }}</p>
                        @endif
                        @error('server_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition-colors">
                    Save Pusher Settings
                </button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 2 – GOOGLE OAUTH & CALENDAR API
         ════════════════════════════════════════════════════════════════ --}}
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
                    <label class="flex items-center gap-2 text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($google['is_enabled'] ?? true) class="rounded border-neutral-300">
                        Enable Google Calendar Sync
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="google_client_id" class="text-sm font-medium text-neutral-800">Client ID *</label>
                        <input id="google_client_id" name="client_id" type="text" value="{{ old('client_id', $google['client_id'] ?? '') }}" required
                               placeholder="e.g. 123456789-xxxxxxxx.apps.googleusercontent.com"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('client_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="google_client_secret" class="text-sm font-medium text-neutral-800">Client Secret</label>
                        <input id="google_client_secret" name="client_secret" type="password"
                               placeholder="{{ !empty($google['client_secret']) ? '•••••••••••• (leave blank to keep current secret)' : 'Enter Client Secret' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedGoogleSecret))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current secret: {{ $maskedGoogleSecret }}</p>
                        @endif
                        @error('client_secret') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="google_redirect_uri" class="text-sm font-medium text-neutral-800">Authorized Redirect URI *</label>
                        <input id="google_redirect_uri" name="redirect_uri" type="url" value="{{ old('redirect_uri', $google['redirect_uri'] ?? url('/employee/calendar/connect')) }}" required
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        <p class="mt-1 text-xs text-neutral-400">Must exactly match the authorized redirect URI in your Google Cloud Console OAuth 2.0 Credentials.</p>
                        @error('redirect_uri') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="google_api_key" class="text-sm font-medium text-neutral-800">Google API Key (Optional)</label>
                        <input id="google_api_key" name="api_key" type="password"
                               placeholder="{{ !empty($google['api_key']) ? '•••••••••••• (leave blank to keep current key)' : 'Enter Google API Key (optional)' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedGoogleApiKey))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current key: {{ $maskedGoogleApiKey }}</p>
                        @endif
                        <p class="mt-1 text-xs text-neutral-400">Sent strictly via the <code class="bg-neutral-100 px-1 py-0.5 rounded font-mono text-neutral-700">X-Goog-Api-Key</code> HTTP header — never exposed as a URL query parameter.</p>
                        @error('api_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Security Best Practices Alert Box --}}
                <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50/70 p-4 text-xs text-blue-900 space-y-1.5">
                    <div class="flex items-center gap-1.5 font-semibold text-blue-950">
                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Google Cloud API Key & OAuth Security Best Practices Enforced
                    </div>
                    <ul class="list-disc pl-5 space-y-1 text-blue-800">
                        <li><strong>Header Transmission:</strong> API keys are transmitted exclusively via the <code class="font-mono bg-blue-100 px-1 rounded">X-Goog-Api-Key</code> HTTP header — never exposed in URL query parameters.</li>
                        <li><strong>Short-Lived Credentials:</strong> OAuth access tokens expire hourly and auto-refresh in the background using AES-256 encrypted refresh tokens.</li>
                        <li><strong>Per-Employee Isolation:</strong> Every team member connects their own Google account, providing an explicit audit trail in Google Cloud Monitoring.</li>
                        <li><strong>Instant Revocation:</strong> Disconnecting a Google account revokes the token directly on Google's authorization servers (<code class="font-mono bg-blue-100 px-1 rounded">oauth2.googleapis.com/revoke</code>).</li>
                        <li><strong>Least-Privilege Scopes:</strong> Access is restricted strictly to calendar event creation (<code class="font-mono bg-blue-100 px-1 rounded">calendar.events</code>) and user email identity.</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition-colors">
                    Save Google Credentials
                </button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 3 – WHATSAPP API & CLICK-TO-CHAT
         ════════════════════════════════════════════════════════════════ --}}
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
                    <label class="flex items-center gap-2 text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($whatsapp['is_enabled'] ?? true) class="rounded border-neutral-300">
                        Enable WhatsApp Actions
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="whatsapp_provider" class="text-sm font-medium text-neutral-800">Integration Mode *</label>
                        <select id="whatsapp_provider" name="provider"
                                class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                            <option value="click_to_chat" @selected(($whatsapp['provider'] ?? '') === 'click_to_chat')>One-Click Web Link (wa.me) — Recommended</option>
                            <option value="cloud_api" @selected(($whatsapp['provider'] ?? '') === 'cloud_api')>Meta WhatsApp Cloud API</option>
                        </select>
                        @error('provider') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="whatsapp_default_country_code" class="text-sm font-medium text-neutral-800">Default Country Code *</label>
                        <input id="whatsapp_default_country_code" name="default_country_code" type="text" value="{{ old('default_country_code', $whatsapp['default_country_code'] ?? '91') }}" required
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('default_country_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="whatsapp_base_url" class="text-sm font-medium text-neutral-800">Base URL *</label>
                        <input id="whatsapp_base_url" name="base_url" type="url" value="{{ old('base_url', $whatsapp['base_url'] ?? 'https://wa.me') }}" required
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('base_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="whatsapp_message_template" class="text-sm font-medium text-neutral-800">Message Template *</label>
                        <textarea id="whatsapp_message_template" name="message_template" rows="3" required
                                  class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">{{ old('message_template', $whatsapp['message_template'] ?? '') }}</textarea>
                        <p class="mt-1 text-xs text-neutral-400">Available placeholders: <code class="bg-neutral-100 px-1 py-0.5 rounded text-neutral-700">{order_number}</code>, <code class="bg-neutral-100 px-1 py-0.5 rounded text-neutral-700">{customer_name}</code>, <code class="bg-neutral-100 px-1 py-0.5 rounded text-neutral-700">{product_name}</code>, <code class="bg-neutral-100 px-1 py-0.5 rounded text-neutral-700">{closing_value}</code>, <code class="bg-neutral-100 px-1 py-0.5 rounded text-neutral-700">{api_key}</code></p>
                        @error('message_template') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2 pt-2 border-t border-neutral-100">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Optional: Meta WhatsApp Cloud API Credentials</h3>
                    </div>

                    <div>
                        <label for="whatsapp_phone_number_id" class="text-sm font-medium text-neutral-800">Phone Number ID</label>
                        <input id="whatsapp_phone_number_id" name="phone_number_id" type="text" value="{{ old('phone_number_id', $whatsapp['phone_number_id'] ?? '') }}"
                               placeholder="e.g. 104593829482910"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                    </div>

                    <div>
                        <label for="whatsapp_access_token" class="text-sm font-medium text-neutral-800">Permanent Access Token</label>
                        <input id="whatsapp_access_token" name="access_token" type="password"
                               placeholder="{{ !empty($whatsapp['access_token']) ? '•••••••••••• (leave blank to keep current token)' : 'Enter Meta System User Token' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedWhatsappToken))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current token: {{ $maskedWhatsappToken }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition-colors">
                    Save WhatsApp Settings
                </button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 4 – PAYMENT GATEWAY & WEBHOOK
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-gateway" class="tab-content mt-5 hidden space-y-5">
        <form method="POST" action="{{ route('admin.api-settings.gateway.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">Payment Gateway API & Webhook</h2>
                        <p class="mt-1 text-xs text-neutral-500">Configure online payment collection and automated signature verification.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($gateway['is_enabled'] ?? true) class="rounded border-neutral-300">
                        Enable Payment Gateway
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="gateway_driver" class="text-sm font-medium text-neutral-800">Gateway Driver *</label>
                        <select id="gateway_driver" name="driver"
                                class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                            <option value="mock" @selected(($gateway['driver'] ?? '') === 'mock')>Mock Gateway (Local Testing)</option>
                            <option value="razorpay" @selected(($gateway['driver'] ?? '') === 'razorpay')>Razorpay</option>
                            <option value="stripe" @selected(($gateway['driver'] ?? '') === 'stripe')>Stripe</option>
                            <option value="phonepe" @selected(($gateway['driver'] ?? '') === 'phonepe')>PhonePe PG</option>
                            <option value="cashfree" @selected(($gateway['driver'] ?? '') === 'cashfree')>Cashfree</option>
                            <option value="custom" @selected(($gateway['driver'] ?? '') === 'custom')>Custom Webhook Driver</option>
                        </select>
                        @error('driver') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="gateway_environment" class="text-sm font-medium text-neutral-800">Environment *</label>
                        <select id="gateway_environment" name="environment"
                                class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                            <option value="sandbox" @selected(($gateway['environment'] ?? '') === 'sandbox')>Sandbox / Test Mode</option>
                            <option value="live" @selected(($gateway['environment'] ?? '') === 'live')>Live / Production Mode</option>
                        </select>
                        @error('environment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="gateway_key_id" class="text-sm font-medium text-neutral-800">Public Key / Key ID</label>
                        <input id="gateway_key_id" name="key_id" type="text" value="{{ old('key_id', $gateway['key_id'] ?? '') }}"
                               placeholder="e.g. rzp_live_xxxxxxxx or pk_live_xxxxxxxx"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                    </div>

                    <div>
                        <label for="gateway_secret_key" class="text-sm font-medium text-neutral-800">Secret Key</label>
                        <input id="gateway_secret_key" name="secret_key" type="password"
                               placeholder="{{ !empty($gateway['secret_key']) ? '•••••••••••• (leave blank to keep current secret)' : 'Enter Gateway Secret Key' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedGatewaySecret))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current: {{ $maskedGatewaySecret }}</p>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label for="gateway_webhook_secret" class="text-sm font-medium text-neutral-800">Incoming Webhook Secret Key</label>
                        <input id="gateway_webhook_secret" name="webhook_secret" type="password"
                               placeholder="{{ !empty($gateway['webhook_secret']) ? '•••••••••••• (leave blank to keep current secret)' : 'Enter Webhook Signing Secret' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedWebhookSecret))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current: {{ $maskedWebhookSecret }}</p>
                        @endif
                    </div>

                    <div class="sm:col-span-2 rounded-lg bg-neutral-50 border border-neutral-200 p-3">
                        <p class="text-xs font-semibold text-neutral-700">Webhook Listener URL</p>
                        <p class="mt-1 text-xs font-mono text-neutral-600 select-all">{{ url('/api/v1/payments/webhook') }}</p>
                        <p class="mt-1 text-xs text-neutral-400">Configure this exact URL in your payment gateway dashboard to receive payment confirmation events.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition-colors">
                    Save Gateway Credentials
                </button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 5 – OUTBOUND WEBHOOKS
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-webhooks" class="tab-content mt-5 hidden space-y-5">
        <form method="POST" action="{{ route('admin.api-settings.webhook.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">Outbound Webhooks</h2>
                        <p class="mt-1 text-xs text-neutral-500">Automatically broadcast real-time event payloads to external servers, Zapier, or Make.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($webhook['is_enabled'] ?? false) class="rounded border-neutral-300">
                        Enable Outbound Dispatch
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="webhook_endpoint_url" class="text-sm font-medium text-neutral-800">Target Endpoint URL</label>
                        <input id="webhook_endpoint_url" name="endpoint_url" type="url" value="{{ old('endpoint_url', $webhook['endpoint_url'] ?? '') }}"
                               placeholder="https://your-crm.example.com/api/leadx-webhook"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('endpoint_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="webhook_secret_token" class="text-sm font-medium text-neutral-800">HMAC Secret Signing Token</label>
                        <input id="webhook_secret_token" name="secret_token" type="password"
                               placeholder="{{ !empty($webhook['secret_token']) ? '•••••••••••• (leave blank to keep current token)' : 'Enter Secret Token' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @if(!empty($maskedOutboundWebhookSecret))
                            <p class="mt-1 text-xs text-neutral-500 font-mono">Current secret: {{ $maskedOutboundWebhookSecret }}</p>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-neutral-800">Subscribed Events</label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @php
                                $allEvents = [
                                    'order.created' => 'Order Created',
                                    'order.cancelled' => 'Order Cancelled',
                                    'payment.verified' => 'Payment Verified',
                                    'subscription.activated' => 'Subscription Activated',
                                    'subscription.expired' => 'Subscription Expired',
                                    'refund.processed' => 'Refund Processed',
                                ];
                                $selectedEvents = $webhook['events'] ?? [];
                            @endphp
                            @foreach ($allEvents as $eventKey => $eventLabel)
                                <label class="flex items-center gap-2 rounded-lg border border-neutral-200 p-2.5 text-sm hover:bg-neutral-50 cursor-pointer">
                                    <input type="checkbox" name="events[]" value="{{ $eventKey }}" @checked(in_array($eventKey, $selectedEvents)) class="rounded border-neutral-300">
                                    <span class="text-neutral-700">{{ $eventLabel }}</span>
                                    <code class="ml-auto text-xs text-neutral-400 font-mono">{{ $eventKey }}</code>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition-colors">
                    Save Webhook Settings
                </button>
            </div>
        </form>

        {{-- Test Ping Form --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h3 class="text-sm font-semibold text-neutral-800">Test Webhook Ping</h3>
            <p class="mt-1 text-xs text-neutral-500">Sends a test JSON payload with HMAC signature header to your configured endpoint URL.</p>
            <form method="POST" action="{{ route('admin.api-settings.webhook.test') }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-lg border border-neutral-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                    Send Test Ping
                </button>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 6 – LEADX API ACCESS TOKENS (Sanctum)
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-tokens" class="tab-content mt-5 hidden space-y-5">
        {{-- Token Generation Box --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-800">Generate LeadX REST API Token</h2>
            <p class="mt-1 text-xs text-neutral-500">Create personal access Bearer tokens to authenticate third-party apps, custom scripts, or mobile portals.</p>

            <form method="POST" action="{{ route('admin.api-settings.tokens.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-[220px]">
                    <label for="token_name" class="text-xs font-medium text-neutral-700">Token Name / Client Label *</label>
                    <input id="token_name" name="token_name" type="text" required placeholder="e.g. Mobile App Backend, Zapier Integration"
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                </div>

                <div class="w-48">
                    <label for="abilities" class="text-xs font-medium text-neutral-700">Scopes / Abilities</label>
                    <input id="abilities" name="abilities" type="text" placeholder="* (All Scopes)"
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                </div>

                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition-colors">
                    Generate Token
                </button>
            </form>
        </div>

        {{-- Active Tokens Table --}}
        <div class="rounded-xl border border-neutral-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-3 border-b border-neutral-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-neutral-800">Active API Tokens</h3>
                <span class="text-xs text-neutral-400">{{ $tokens->count() }} active {{ Str::plural('token', $tokens->count()) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50 text-neutral-600 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Abilities</th>
                            <th class="px-4 py-3 font-semibold">Created</th>
                            <th class="px-4 py-3 font-semibold">Last Used</th>
                            <th class="px-4 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($tokens as $token)
                            <tr>
                                <td class="px-4 py-3 font-medium text-neutral-800">{{ $token->name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-neutral-600">
                                    {{ implode(', ', $token->abilities ?? ['*']) }}
                                </td>
                                <td class="px-4 py-3 text-neutral-500 text-xs whitespace-nowrap">
                                    {{ $token->created_at?->format('M d, Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-neutral-500 text-xs whitespace-nowrap">
                                    {{ $token->last_used_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.api-settings.tokens.revoke', $token) }}" class="inline"
                                          onsubmit="return confirm('Are you sure you want to revoke this API token? Any application using it will lose access immediately.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800">
                                            Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-neutral-400">
                                    No active API tokens found. Generate one above to access LeadX REST APIs.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- REST API Quick Reference Card --}}
        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-5 text-xs text-neutral-600 space-y-2">
            <h4 class="font-semibold text-neutral-800 text-sm">LeadX REST API Quick Reference</h4>
            <p>Pass your Bearer token in the <code class="bg-neutral-200 px-1 py-0.5 rounded font-mono">Authorization: Bearer &lt;token&gt;</code> header on all requests.</p>
            <div class="mt-2 rounded-lg bg-neutral-900 p-3 font-mono text-neutral-200 text-xs overflow-x-auto">
                curl -H "Authorization: Bearer &lt;YOUR_TOKEN&gt;" \<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-H "Accept: application/json" \<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ url('/api/v1/orders') }}
            </div>
        </div>
    </div>

    {{-- JavaScript for tab switching --}}
    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(function(el) {
            el.classList.add('hidden');
        });
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.classList.remove('bg-neutral-900', 'text-white');
            btn.classList.add('text-neutral-500', 'hover:text-neutral-700');
        });

        const panel = document.getElementById('tab-' + tab);
        const btn = document.getElementById('tab-' + tab + '-btn');
        if (panel) panel.classList.remove('hidden');
        if (btn) {
            btn.classList.remove('text-neutral-500', 'hover:text-neutral-700');
            btn.classList.add('bg-neutral-900', 'text-white');
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
