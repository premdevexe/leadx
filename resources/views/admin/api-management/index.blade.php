@extends('layouts.admin-dashboard')

@section('title', 'API Management')

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-semibold tracking-tight text-neutral-900">API Management</h1>
                <span class="badge badge-primary font-mono text-2xs">{{ $metrics['version'] }}</span>
            </div>
            <p class="mt-1 text-xs text-neutral-500">
                Manage Android App API keys, real-time rate limits, live request logs, endpoint monitors, and integration health.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($health['status'] === 'healthy')
                <span class="badge badge-success badge-dot">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    API Operational
                </span>
            @elseif ($health['status'] === 'maintenance')
                <span class="badge badge-warning badge-dot">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    Maintenance Mode Active
                </span>
            @else
                <span class="badge badge-danger badge-dot">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                    API Degraded
                </span>
            @endif

            <a href="{{ route('admin.api-management.docs.swagger') }}" class="btn btn-secondary btn-sm flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                </svg>
                <span>Interactive Swagger UI</span>
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if (session('status'))
        <div class="alert alert-success text-sm flex items-center justify-between p-3.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">&times;</button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger text-sm flex items-center justify-between p-3.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-800">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900">&times;</button>
        </div>
    @endif

    {{-- ONE-TIME GENERATED API KEY DISPLAY (CRITICAL SECURITY) --}}
    @if (session('new_api_key'))
        <div id="new-key-banner" class="rounded-xl border-2 border-emerald-400 bg-emerald-50 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs">!</span>
                        <h2 class="text-sm font-bold text-emerald-900">New API Key Generated — One-Time Secret Display</h2>
                    </div>
                    <p class="text-xs text-emerald-800">
                        Copy this API key now. For your security, this raw key is <strong>never stored in plaintext</strong> and will <strong>never be shown again</strong>.
                    </p>
                </div>
                <button type="button" onclick="document.getElementById('new-key-banner').remove()" class="text-emerald-700 hover:text-emerald-950 font-bold text-sm">
                    Dismiss
                </button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <div class="flex-1 min-w-[280px]">
                    <input type="text" id="plain-api-key-input" readonly value="{{ session('new_api_key') }}"
                           class="w-full px-3 py-2 text-xs font-mono font-semibold bg-white border border-emerald-300 rounded-lg text-emerald-950 select-all focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="button" id="copy-key-btn" onclick="copyPlainApiKey()"
                        class="btn btn-primary btn-sm flex items-center gap-1.5 px-4 bg-emerald-700 hover:bg-emerald-800 border-emerald-700 text-white">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/>
                    </svg>
                    <span id="copy-btn-text">Copy Key</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <nav class="flex flex-wrap gap-1.5 border-b border-neutral-200" role="tablist">
        <button type="button" onclick="switchApiTab('overview')" id="tab-btn-overview"
                class="tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors {{ $activeTab === 'overview' ? 'btn btn-primary text-white bg-neutral-900' : 'btn btn-secondary text-neutral-600 hover:text-neutral-900' }}">
            Overview & Metrics
        </button>
        <button type="button" onclick="switchApiTab('keys')" id="tab-btn-keys"
                class="tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors {{ $activeTab === 'keys' ? 'btn btn-primary text-white bg-neutral-900' : 'btn btn-secondary text-neutral-600 hover:text-neutral-900' }}">
            API Key Management
        </button>
        <button type="button" onclick="switchApiTab('config')" id="tab-btn-config"
                class="tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors {{ $activeTab === 'config' ? 'btn btn-primary text-white bg-neutral-900' : 'btn btn-secondary text-neutral-600 hover:text-neutral-900' }}">
            API Configuration
        </button>
        <button type="button" onclick="switchApiTab('endpoints')" id="tab-btn-endpoints"
                class="tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors {{ $activeTab === 'endpoints' ? 'btn btn-primary text-white bg-neutral-900' : 'btn btn-secondary text-neutral-600 hover:text-neutral-900' }}">
            Endpoint Monitoring
        </button>
        <button type="button" onclick="switchApiTab('logs')" id="tab-btn-logs"
                class="tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors {{ $activeTab === 'logs' ? 'btn btn-primary text-white bg-neutral-900' : 'btn btn-secondary text-neutral-600 hover:text-neutral-900' }}">
            Request Logs & Forensics
        </button>
        <button type="button" onclick="switchApiTab('docs')" id="tab-btn-docs"
                class="tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors {{ $activeTab === 'docs' ? 'btn btn-primary text-white bg-neutral-900' : 'btn btn-secondary text-neutral-600 hover:text-neutral-900' }}">
            API Documentation
        </button>
    </nav>

    {{-- TAB 1: OVERVIEW & REAL DATABASE-BACKED METRICS --}}
    <div id="tab-pane-overview" class="tab-pane space-y-6 {{ $activeTab === 'overview' ? '' : 'hidden' }}">
        {{-- Application Status Summary Banner --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-neutral-900 text-white flex items-center justify-center font-bold text-sm">
                    LX
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900">{{ $metrics['app_name'] }}</h2>
                    <p class="text-xs text-neutral-500 font-mono">{{ $metrics['base_url'] }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="badge {{ $metrics['is_enabled'] ? 'badge-success' : 'badge-danger' }}">
                    {{ $metrics['is_enabled'] ? 'API Enabled' : 'API Disabled' }}
                </span>
                <span class="badge {{ $metrics['maintenance_mode'] ? 'badge-warning' : 'badge-neutral' }}">
                    {{ $metrics['maintenance_mode'] ? 'Maintenance Active' : 'Normal Traffic' }}
                </span>
                <span class="badge {{ $metrics['active_key_status'] === 'Active' ? 'badge-success' : ($metrics['active_key_status'] === 'Expired' ? 'badge-danger' : 'badge-warning') }}">
                    Key: {{ $metrics['active_key_status'] }}
                </span>
            </div>
        </div>

        {{-- 5 Database-backed Metric Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-xs">
                <p class="text-2xs font-semibold uppercase tracking-wider text-neutral-400">Total API Requests</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-neutral-900 font-mono">{{ number_format($metrics['total_requests']) }}</p>
                <p class="mt-1 text-2xs text-neutral-500">Recorded across all v1 routes</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-xs">
                <p class="text-2xs font-semibold uppercase tracking-wider text-emerald-600">Successful Requests</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-700 font-mono">{{ number_format($metrics['successful_requests']) }}</p>
                <p class="mt-1 text-2xs text-neutral-500">{{ $metrics['success_rate'] }}% 2xx/3xx response rate</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-xs">
                <p class="text-2xs font-semibold uppercase tracking-wider text-rose-600">Failed Requests</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-rose-700 font-mono">{{ number_format($metrics['failed_requests']) }}</p>
                <p class="mt-1 text-2xs text-neutral-500">4xx / 5xx error responses</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-xs">
                <p class="text-2xs font-semibold uppercase tracking-wider text-neutral-400">Average Response Time</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-neutral-900 font-mono">{{ $metrics['avg_response_time'] }} <span class="text-xs font-normal text-neutral-500">ms</span></p>
                <p class="mt-1 text-2xs text-neutral-500">Real database request average</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-xs">
                <p class="text-2xs font-semibold uppercase tracking-wider text-neutral-400">Last Request Date</p>
                <p class="mt-2 text-sm font-bold tracking-tight text-neutral-900 truncate">
                    {{ $metrics['last_request_at'] ? $metrics['last_request_at']->format('M d, H:i:s') : 'No requests yet' }}
                </p>
                <p class="mt-1 text-2xs text-neutral-500">
                    {{ $metrics['last_request_at'] ? $metrics['last_request_at']->diffForHumans() : 'Awaiting traffic' }}
                </p>
            </div>
        </div>

        {{-- API Health Monitor Component --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">API Health Monitor</h3>
                    <p class="text-xs text-neutral-500">Automated diagnostic probe of API stack, database engine, token middleware, and credentials.</p>
                </div>
                <button type="button" onclick="refreshHealthCheck()" id="refresh-health-btn" class="btn btn-secondary btn-sm flex items-center gap-1.5 text-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Refresh Diagnostics</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="health-grid">
                @foreach ($health['checks'] as $key => $check)
                    <div class="p-3.5 rounded-lg border border-neutral-100 bg-neutral-50/70 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-neutral-700">{{ $check['name'] }}</span>
                            @if ($check['status'] === 'operational')
                                <span class="badge badge-success text-2xs font-semibold">OK</span>
                            @elseif ($check['status'] === 'warning')
                                <span class="badge badge-warning text-2xs font-semibold">Warning</span>
                            @else
                                <span class="badge badge-danger text-2xs font-semibold">Down</span>
                            @endif
                        </div>
                        <p class="text-xs font-semibold text-neutral-900">{{ $check['label'] }}</p>
                        <p class="text-2xs text-neutral-500 leading-normal">{{ $check['details'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TAB 2: API KEY MANAGEMENT --}}
    <div id="tab-pane-keys" class="tab-pane space-y-6 {{ $activeTab === 'keys' ? '' : 'hidden' }}">
        {{-- Active Key Section --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Current LeadX Android App API Key</h3>
                    <p class="text-xs text-neutral-500">Only one active key is permitted at a time. Plaintext keys are hashed with SHA-256 upon generation.</p>
                </div>

                @if (! $metrics['active_key'])
                    <form method="POST" action="{{ route('admin.api-management.keys.generate') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Generate Initial API Key</span>
                        </button>
                    </form>
                @endif
            </div>

            @if ($metrics['active_key'])
                @php $k = $metrics['active_key']; @endphp
                <div class="p-4 rounded-xl border border-neutral-200 bg-neutral-50/50 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-neutral-900 font-mono">{{ $k->name }}</span>
                                <span class="badge {{ $k->status === 'active' ? 'badge-success' : 'badge-warning' }} text-2xs uppercase tracking-wider font-bold">
                                    {{ $k->status }}
                                </span>
                            </div>
                            <p class="text-xs font-mono text-neutral-600 bg-white px-2.5 py-1 rounded border border-neutral-200 inline-block">
                                Key Prefix: <strong>{{ $k->key_prefix }}</strong>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Toggle Button --}}
                            <form method="POST" action="{{ route('admin.api-management.keys.toggle', $k->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm text-xs">
                                    {{ $k->status === 'active' ? 'Disable Key' : 'Enable Key' }}
                                </button>
                            </form>

                            {{-- Regenerate Button with Confirmation --}}
                            <button type="button" onclick="confirmRegenerate('{{ route('admin.api-management.keys.regenerate', $k->id) }}')"
                                    class="btn btn-warning btn-sm text-xs flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Regenerate Key</span>
                            </button>

                            {{-- Revoke Button with Confirmation --}}
                            <button type="button" onclick="confirmRevoke('{{ route('admin.api-management.keys.revoke', $k->id) }}')"
                                    class="btn btn-danger btn-sm text-xs flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                <span>Revoke Key</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-neutral-200/80 text-xs text-neutral-600">
                        <div>
                            <span class="text-neutral-400 block text-2xs uppercase">Creation Date</span>
                            <span class="font-medium text-neutral-800">{{ $k->created_at->format('M d, Y H:i:s') }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block text-2xs uppercase">Expiration Date</span>
                            <span class="font-medium text-neutral-800">
                                {{ $k->expires_at ? $k->expires_at->format('M d, Y H:i:s') . ' (' . $k->expires_at->diffForHumans() . ')' : 'Never Expires' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block text-2xs uppercase">Last Used Date</span>
                            <span class="font-medium text-neutral-800">
                                {{ $k->last_used_at ? $k->last_used_at->format('M d, Y H:i:s') . ' (' . $k->last_used_at->diffForHumans() . ')' : 'Never Used' }}
                            </span>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-8 text-center rounded-xl border border-dashed border-neutral-300 bg-neutral-50/50">
                    <p class="text-xs font-semibold text-neutral-700">No active API key currently exists for this application.</p>
                    <p class="text-2xs text-neutral-500 mt-1">Generate a production key to permit the Android & Flutter clients to authenticate against LeadX.</p>
                </div>
            @endif
        </div>

        {{-- API Key History Table --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-4">
            <h3 class="text-sm font-semibold text-neutral-900">API Key Lifecycle History</h3>
            <div class="table-wrapper overflow-x-auto">
                <table class="w-full text-left text-xs text-neutral-600">
                    <thead class="bg-neutral-50 text-2xs uppercase text-neutral-400 border-b border-neutral-200">
                        <tr>
                            <th class="px-4 py-2.5">Key Preview</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Created</th>
                            <th class="px-4 py-2.5">Expires</th>
                            <th class="px-4 py-2.5">Last Used</th>
                            <th class="px-4 py-2.5">Revoked At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($keys as $histKey)
                            <tr>
                                <td class="px-4 py-3 font-mono font-medium text-neutral-900">{{ $histKey->key_prefix }}</td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $histKey->status === 'active' ? 'badge-success' : ($histKey->status === 'revoked' ? 'badge-danger' : 'badge-warning') }} text-2xs font-semibold">
                                        {{ $histKey->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $histKey->created_at->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $histKey->expires_at ? $histKey->expires_at->format('M d, Y') : 'Never' }}</td>
                                <td class="px-4 py-3">{{ $histKey->last_used_at ? $histKey->last_used_at->diffForHumans() : '—' }}</td>
                                <td class="px-4 py-3 text-neutral-400">{{ $histKey->revoked_at ? $histKey->revoked_at->format('M d, Y H:i') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-xs text-neutral-400">No previous API keys recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($keys->hasPages())
                <div class="mt-4">
                    {{ $keys->appends(['tab' => 'keys'])->links() }}
                </div>
            @endif
        </div>

        {{-- Audit Trail of Key & Config Operations --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-3">
            <h3 class="text-sm font-semibold text-neutral-900">Admin Audit Trail (API Events)</h3>
            <div class="divide-y divide-neutral-100 text-xs">
                @forelse ($auditLogs as $audit)
                    <div class="py-2.5 flex flex-wrap items-center justify-between gap-2">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-neutral text-2xs font-mono font-bold">{{ $audit->event }}</span>
                                <span class="text-neutral-500">by {{ $audit->new_values['generated_by'] ?? ($audit->new_values['regenerated_by'] ?? ($audit->new_values['revoked_by'] ?? 'Super Admin')) }}</span>
                            </div>
                            <p class="text-2xs text-neutral-400">IP: {{ $audit->ip ?: 'local' }} &bull; Record ID: #{{ $audit->auditable_id }}</p>
                        </div>
                        <span class="text-2xs text-neutral-400 font-mono">{{ $audit->created_at->format('M d, Y H:i:s') }}</span>
                    </div>
                @empty
                    <p class="text-xs text-neutral-400 py-3 text-center">No API management audit records found.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- TAB 3: API CONFIGURATION --}}
    <div id="tab-pane-config" class="tab-pane space-y-6 {{ $activeTab === 'config' ? '' : 'hidden' }}">
        <form method="POST" action="{{ route('admin.api-management.config.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Core API Configuration</h3>
                    <p class="text-xs text-neutral-500">Configure global throttling, base routing paths, IP restrictions, and maintenance windows.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="cfg_app_name" class="form-label text-xs font-semibold text-neutral-700">API Application Name *</label>
                        <input type="text" id="cfg_app_name" name="name" value="{{ old('name', $app->name) }}" required
                               class="form-input text-xs w-full mt-1">
                        @error('name') <p class="text-2xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cfg_base_url" class="form-label text-xs font-semibold text-neutral-700">API Base URL *</label>
                        <input type="url" id="cfg_base_url" name="base_url" value="{{ old('base_url', $app->base_url) }}" required
                               class="form-input text-xs w-full mt-1 font-mono">
                        @error('base_url') <p class="text-2xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cfg_version" class="form-label text-xs font-semibold text-neutral-700">API Version Tag *</label>
                        <input type="text" id="cfg_version" name="version" value="{{ old('version', $app->version) }}" required
                               class="form-input text-xs w-full mt-1 font-mono">
                        @error('version') <p class="text-2xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cfg_rate_limit" class="form-label text-xs font-semibold text-neutral-700">Rate Limit (Requests / minute) *</label>
                        <input type="number" id="cfg_rate_limit" name="rate_limit_per_minute" min="1" max="10000"
                               value="{{ old('rate_limit_per_minute', $app->rate_limit_per_minute) }}" required
                               class="form-input text-xs w-full mt-1 font-mono">
                        <span class="text-2xs text-neutral-400">Protects API from DDoS and abusive scraping.</span>
                        @error('rate_limit_per_minute') <p class="text-2xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cfg_key_expiry" class="form-label text-xs font-semibold text-neutral-700">Default Key Expiry (Days) *</label>
                        <input type="number" id="cfg_key_expiry" name="key_expiry_days" min="0" max="3650"
                               value="{{ old('key_expiry_days', $app->key_expiry_days) }}" required
                               class="form-input text-xs w-full mt-1 font-mono">
                        <span class="text-2xs text-neutral-400">Set to 0 for keys that never expire automatically.</span>
                        @error('key_expiry_days') <p class="text-2xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cfg_ip_allowlist" class="form-label text-xs font-semibold text-neutral-700">Optional IP Allowlist</label>
                        <textarea id="cfg_ip_allowlist" name="ip_allowlist" rows="2"
                                  placeholder="e.g. 203.0.113.1, 198.51.100.0/24 (leave blank to allow all IPs)"
                                  class="form-textarea text-xs w-full mt-1 font-mono">{{ old('ip_allowlist', $app->ip_allowlist) }}</textarea>
                        <span class="text-2xs text-neutral-400">Comma-separated or newline-separated IPs/CIDRs.</span>
                    </div>
                </div>

                {{-- Status Switches --}}
                <div class="pt-4 border-t border-neutral-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="flex items-start gap-3 p-3.5 rounded-lg border border-neutral-200 bg-neutral-50/50 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $app->is_enabled))
                               class="rounded border-neutral-300 mt-0.5">
                        <div class="space-y-0.5">
                            <span class="text-xs font-semibold text-neutral-800">Enable API Access</span>
                            <p class="text-2xs text-neutral-500">When unchecked, incoming API requests immediately return HTTP 503.</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-lg border border-amber-200 bg-amber-50/50 cursor-pointer">
                        <input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $app->maintenance_mode))
                               class="rounded border-amber-300 mt-0.5">
                        <div class="space-y-0.5">
                            <span class="text-xs font-semibold text-amber-900">Maintenance Mode</span>
                            <p class="text-2xs text-amber-700">When active, clients receive a graceful maintenance JSON notice (503).</p>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end pt-3">
                    <button type="submit" class="btn btn-primary btn-sm px-6 text-xs">
                        Save API Configuration
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- TAB 4: ENDPOINT MONITORING (11 MODULES DYNAMICALLY RESOLVED) --}}
    <div id="tab-pane-endpoints" class="tab-pane space-y-4 {{ $activeTab === 'endpoints' ? '' : 'hidden' }}">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Implemented API Endpoint Monitor</h3>
                    <p class="text-xs text-neutral-500">Live reflection of registered Laravel routes grouped by system module. Unimplemented routes are excluded.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-filter-input" placeholder="Filter routes..." onkeyup="filterEndpoints()"
                           class="form-input text-xs px-3 py-1.5 rounded-lg border-neutral-200">
                </div>
            </div>

            <div class="mt-5 space-y-3" id="endpoint-accordion">
                @foreach ($groupedEndpoints as $moduleName => $routes)
                    <div class="border border-neutral-200 rounded-lg overflow-hidden endpoint-module-card">
                        <button type="button" onclick="toggleEndpointModule('{{ Str::slug($moduleName) }}')"
                                class="w-full flex items-center justify-between px-4 py-3 bg-neutral-50 text-left hover:bg-neutral-100 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <span class="text-xs font-bold text-neutral-800">{{ $moduleName }}</span>
                                <span class="badge badge-neutral text-2xs">{{ count($routes) }} routes</span>
                            </div>
                            <svg class="w-4 h-4 text-neutral-400 transform transition-transform" id="icon-{{ Str::slug($moduleName) }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div id="body-{{ Str::slug($moduleName) }}" class="p-3 bg-white space-y-2">
                            @forelse ($routes as $route)
                                <div class="endpoint-item flex flex-wrap items-center justify-between gap-2 p-2 rounded hover:bg-neutral-50 border border-transparent hover:border-neutral-100 text-xs">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @foreach ($route['methods'] as $m)
                                            <span class="badge font-mono font-bold text-2xs
                                                {{ $m === 'GET' ? 'badge-primary' : ($m === 'POST' ? 'badge-success' : ($m === 'PUT' ? 'badge-warning' : 'badge-danger')) }}">
                                                {{ $m }}
                                            </span>
                                        @endforeach
                                        <span class="font-mono font-semibold text-neutral-900 endpoint-uri">{{ $route['uri'] }}</span>
                                    </div>

                                    <div class="flex items-center gap-2 text-2xs text-neutral-500">
                                        <span class="font-mono text-neutral-600 truncate max-w-xs">{{ $route['action'] }}</span>
                                        <span class="badge badge-success text-2xs font-semibold">Active</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-neutral-400 py-2 text-center">No active routes registered under this module.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TAB 5: REQUEST LOGS & FORENSICS --}}
    <div id="tab-pane-logs" class="tab-pane space-y-4 {{ $activeTab === 'logs' ? '' : 'hidden' }}">
        {{-- Filter Bar --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-4">
            <h3 class="text-sm font-semibold text-neutral-900">API Usage & Request Logs Forensics</h3>
            <form method="GET" action="{{ route('admin.api-management.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input type="hidden" name="tab" value="logs">

                <div>
                    <label class="text-2xs font-semibold text-neutral-500 block mb-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}"
                           class="form-input text-xs w-full py-1.5">
                </div>

                <div>
                    <label class="text-2xs font-semibold text-neutral-500 block mb-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}"
                           class="form-input text-xs w-full py-1.5">
                </div>

                <div>
                    <label class="text-2xs font-semibold text-neutral-500 block mb-1">Endpoint Filter</label>
                    <input type="text" name="endpoint" value="{{ request('endpoint') }}" placeholder="e.g. orders"
                           class="form-input text-xs w-full py-1.5">
                </div>

                <div>
                    <label class="text-2xs font-semibold text-neutral-500 block mb-1">HTTP Method</label>
                    <select name="method" class="form-select text-xs w-full py-1.5">
                        <option value="ALL">ALL Methods</option>
                        @foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $met)
                            <option value="{{ $met }}" @selected(request('method') === $met)>{{ $met }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-2xs font-semibold text-neutral-500 block mb-1">Response Status</label>
                    <div class="flex items-center gap-1.5">
                        <select name="status" class="form-select text-xs w-full py-1.5">
                            <option value="ALL">ALL Statuses</option>
                            <option value="2xx" @selected(request('status') === '2xx')>2xx Success</option>
                            <option value="4xx" @selected(request('status') === '4xx')>4xx Client Error</option>
                            <option value="5xx" @selected(request('status') === '5xx')>5xx Server Error</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm py-1.5 text-xs">Filter</button>
                    </div>
                </div>
            </form>

            @if (request()->hasAny(['from_date', 'to_date', 'endpoint', 'method', 'status']))
                <div class="flex items-center justify-between text-2xs text-neutral-500 pt-2 border-t border-neutral-100">
                    <span>Filtering active request records</span>
                    <a href="{{ route('admin.api-management.index', ['tab' => 'logs']) }}" class="text-neutral-900 font-semibold hover:underline">
                        Clear Filters
                    </a>
                </div>
            @endif
        </div>

        {{-- Log Records Table --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-4">
            <div class="table-wrapper overflow-x-auto">
                <table class="w-full text-left text-xs text-neutral-600">
                    <thead class="bg-neutral-50 text-2xs uppercase text-neutral-400 border-b border-neutral-200">
                        <tr>
                            <th class="px-3.5 py-2.5">Request ID</th>
                            <th class="px-3.5 py-2.5">Method</th>
                            <th class="px-3.5 py-2.5">Endpoint</th>
                            <th class="px-3.5 py-2.5">Status</th>
                            <th class="px-3.5 py-2.5">Duration</th>
                            <th class="px-3.5 py-2.5">Masked Client IP</th>
                            <th class="px-3.5 py-2.5">Request Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-3.5 py-2.5 font-mono text-2xs text-neutral-900">{{ $log->request_id }}</td>
                                <td class="px-3.5 py-2.5 font-mono">
                                    <span class="badge font-bold text-2xs
                                        {{ $log->method === 'GET' ? 'badge-primary' : ($log->method === 'POST' ? 'badge-success' : ($log->method === 'PUT' ? 'badge-warning' : 'badge-danger')) }}">
                                        {{ $log->method }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 font-mono text-xs text-neutral-800">{{ $log->endpoint }}</td>
                                <td class="px-3.5 py-2.5">
                                    <span class="badge {{ $log->status_code < 400 ? 'badge-success' : ($log->status_code < 500 ? 'badge-warning' : 'badge-danger') }} font-mono font-bold text-2xs">
                                        {{ $log->status_code }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 font-mono text-2xs">{{ $log->response_time_ms }} ms</td>
                                <td class="px-3.5 py-2.5 font-mono text-2xs text-neutral-500">{{ $log->ip_address ?: '—' }}</td>
                                <td class="px-3.5 py-2.5 text-2xs text-neutral-500">{{ $log->created_at->format('M d, H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-neutral-400">
                                    No API request logs recorded matching your filter parameters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="mt-4">
                    {{ $logs->appends(request()->except('page'))->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- TAB 6: API DOCUMENTATION --}}
    <div id="tab-pane-docs" class="tab-pane space-y-6 {{ $activeTab === 'docs' ? '' : 'hidden' }}">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- OpenAPI Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm">
                    YAML
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">OpenAPI Specification (v3.0.3)</h3>
                    <p class="text-xs text-neutral-500 mt-1">Official comprehensive REST schema specifying every endpoint, request schema, and response model.</p>
                </div>
                <div class="pt-2 flex items-center gap-2">
                    <a href="{{ route('admin.api-management.docs.openapi') }}" target="_blank" class="btn btn-secondary btn-sm text-xs">
                        View Specification
                    </a>
                    <a href="{{ route('admin.api-management.docs.openapi', ['download' => 1]) }}" class="btn btn-primary btn-sm text-xs">
                        Download YAML
                    </a>
                </div>
            </div>

            {{-- Swagger UI Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center font-bold text-sm">
                    UI
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Interactive Swagger Explorer</h3>
                    <p class="text-xs text-neutral-500 mt-1">Full interactive browser console to test queries, inspect models, and execute live API calls.</p>
                </div>
                <div class="pt-2">
                    <a href="{{ route('admin.api-management.docs.swagger') }}" class="btn btn-primary btn-sm text-xs flex items-center gap-1.5 w-fit">
                        <span>Launch Swagger Console</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Postman Collection Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-sm">
                    JSON
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Postman Collection (v2.1.0)</h3>
                    <p class="text-xs text-neutral-500 mt-1">Pre-configured collection with all 11 modules, bearer token variables, and environment setup.</p>
                </div>
                <div class="pt-2">
                    <a href="{{ route('admin.api-management.docs.postman') }}" class="btn btn-secondary btn-sm text-xs">
                        Download Postman Collection
                    </a>
                </div>
            </div>

            {{-- Flutter Guide Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-3">
                <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-800 flex items-center justify-center font-bold text-sm">
                    DART
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Flutter Mobile Integration Guide</h3>
                    <p class="text-xs text-neutral-500 mt-1">Architectural patterns, Dio HTTP client interceptors, state management, and offline cache guidelines.</p>
                </div>
                <div class="pt-2 flex items-center gap-2">
                    <a href="{{ route('admin.api-management.docs.flutter') }}" class="btn btn-secondary btn-sm text-xs">
                        View Integration Guide
                    </a>
                    <a href="{{ route('admin.api-management.docs.flutter', ['download' => 1]) }}" class="btn btn-outline btn-sm text-xs">
                        Download Markdown
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: CONFIRM ACTION DIALOG --}}
<div id="confirm-modal" class="fixed inset-0 z-50 bg-neutral-900/50 backdrop-blur-xs flex items-center justify-center hidden p-4" aria-modal="true" role="dialog">
    <div class="bg-white rounded-xl max-w-md w-full p-5 shadow-xl border border-neutral-200 space-y-4">
        <div class="flex items-start gap-3">
            <div id="confirm-modal-icon" class="w-10 h-10 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h3 id="confirm-modal-title" class="text-sm font-bold text-neutral-900">Confirm Action</h3>
                <p id="confirm-modal-desc" class="text-xs text-neutral-500 mt-1">Are you sure you want to proceed with this operation?</p>
            </div>
        </div>

        <form id="confirm-modal-form" method="POST" action="">
            @csrf
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeConfirmModal()" class="btn btn-secondary btn-sm text-xs">
                    Cancel
                </button>
                <button type="submit" id="confirm-modal-btn" class="btn btn-danger btn-sm text-xs">
                    Confirm Action
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab switching with URL hash persistence
    function switchApiTab(tabId) {
        document.querySelectorAll('.tab-pane').forEach(function(el) {
            el.classList.add('hidden');
        });
        document.querySelectorAll('.tab-nav-btn').forEach(function(btn) {
            btn.className = 'tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors btn btn-secondary text-neutral-600 hover:text-neutral-900';
        });

        var activePane = document.getElementById('tab-pane-' + tabId);
        var activeBtn = document.getElementById('tab-btn-' + tabId);
        if (activePane) activePane.classList.remove('hidden');
        if (activeBtn) activeBtn.className = 'tab-nav-btn px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors btn btn-primary text-white bg-neutral-900';

        if (history.replaceState) {
            var url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            history.replaceState(null, '', url);
        }
    }

    // Copy plaintext API key
    function copyPlainApiKey() {
        var input = document.getElementById('plain-api-key-input');
        if (!input) return;
        input.select();
        navigator.clipboard.writeText(input.value).then(function() {
            var btnText = document.getElementById('copy-btn-text');
            if (btnText) btnText.textContent = 'Copied!';
            setTimeout(function() {
                if (btnText) btnText.textContent = 'Copy Key';
            }, 2500);
        });
    }

    // Modal Confirmation Dialogs
    function confirmRegenerate(actionUrl) {
        document.getElementById('confirm-modal-title').textContent = 'Regenerate API Key?';
        document.getElementById('confirm-modal-desc').textContent = 'Regenerating will revoke the currently active key immediately. Any active mobile clients using the old key will lose access until updated.';
        document.getElementById('confirm-modal-form').action = actionUrl;
        document.getElementById('confirm-modal-btn').className = 'btn btn-warning btn-sm text-xs';
        document.getElementById('confirm-modal-btn').textContent = 'Yes, Regenerate Key';
        document.getElementById('confirm-modal').classList.remove('hidden');
    }

    function confirmRevoke(actionUrl) {
        document.getElementById('confirm-modal-title').textContent = 'Revoke API Key Permanently?';
        document.getElementById('confirm-modal-desc').textContent = 'Revoking is permanent and cannot be undone. All future requests presenting this key will be rejected immediately.';
        document.getElementById('confirm-modal-form').action = actionUrl;
        document.getElementById('confirm-modal-btn').className = 'btn btn-danger btn-sm text-xs';
        document.getElementById('confirm-modal-btn').textContent = 'Yes, Revoke Key';
        document.getElementById('confirm-modal').classList.remove('hidden');
    }

    function closeConfirmModal() {
        document.getElementById('confirm-modal').classList.add('hidden');
    }

    // Endpoint Accordion Toggle
    function toggleEndpointModule(slug) {
        var body = document.getElementById('body-' + slug);
        var icon = document.getElementById('icon-' + slug);
        if (body.classList.contains('hidden')) {
            body.classList.remove('hidden');
            if (icon) icon.classList.remove('-rotate-90');
        } else {
            body.classList.add('hidden');
            if (icon) icon.classList.add('-rotate-90');
        }
    }

    // Endpoint Quick Search
    function filterEndpoints() {
        var input = document.getElementById('endpoint-filter-input');
        var filter = input.value.toLowerCase();
        var items = document.querySelectorAll('.endpoint-item');

        items.forEach(function(item) {
            var uri = item.querySelector('.endpoint-uri').textContent.toLowerCase();
            if (uri.indexOf(filter) > -1) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Health Check Live Fetch
    function refreshHealthCheck() {
        var btn = document.getElementById('refresh-health-btn');
        btn.disabled = true;
        btn.classList.add('opacity-50');

        fetch('{{ route('admin.api-management.health') }}')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                window.location.reload();
            })
            .catch(function() {
                alert('Health check diagnostic probe failed to respond.');
            })
            .finally(function() {
                btn.disabled = false;
                btn.classList.remove('opacity-50');
            });
    }
</script>
@endsection
