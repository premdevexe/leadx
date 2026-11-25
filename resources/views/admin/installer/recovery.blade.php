@extends('layouts.admin-dashboard')

@section('title', 'Installer Recovery and Diagnostics')

@section('content')
<div class="page-container" style="max-width: 64rem; margin: 0 auto; padding: 1.5rem 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--c-neutral-900);">System Installation and Recovery Diagnostics</h1>
            <p style="font-size: 0.875rem; color: var(--c-neutral-500); margin-top: 0.25rem;">
                Non-destructive diagnostic and maintenance center for LeadX Super Administrators.
            </p>
        </div>
        <div>
            @if ($telemetry['is_installed'])
                <span class="badge badge-success" style="font-size: 0.875rem; padding: 0.35rem 0.75rem;">
                    &#10003; Installer Locked (Secure)
                </span>
            @else
                <span class="badge badge-warning" style="font-size: 0.875rem; padding: 0.35rem 0.75rem;">
                    &#9888; Installer Unlocked
                </span>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" style="margin-bottom: 1.25rem;">
            <div class="alert-title">Success</div>
            <div class="alert-message">{{ session('status') }}</div>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
            <div class="alert-title">Notice</div>
            <div class="alert-message">{{ session('error') }}</div>
        </div>
    @endif

    <!-- System Telemetry Overview -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card p-4" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md);">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--c-neutral-500); text-transform: uppercase;">PHP Runtime</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--c-neutral-900); margin-top: 0.5rem;">{{ $telemetry['php_version'] }}</div>
            <div style="font-size: 0.75rem; color: var(--c-green-700); margin-top: 0.25rem;">Laravel {{ $telemetry['laravel_version'] }}</div>
        </div>

        <div class="card p-4" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md);">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--c-neutral-500); text-transform: uppercase;">Database Connection</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: {{ $telemetry['database']['connected'] ? 'var(--c-green-700)' : 'var(--c-red-600)' }}; margin-top: 0.5rem;">
                {{ $telemetry['database']['connected'] ? 'Connected' : 'Offline' }}
            </div>
            <div style="font-size: 0.75rem; color: var(--c-neutral-500); margin-top: 0.25rem;">{{ $telemetry['database']['version'] }}</div>
        </div>

        <div class="card p-4" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md);">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--c-neutral-500); text-transform: uppercase;">Database Schema</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--c-neutral-900); margin-top: 0.5rem;">{{ $telemetry['database']['table_count'] }} Tables</div>
            <div style="font-size: 0.75rem; color: var(--c-neutral-500); margin-top: 0.25rem;">{{ $telemetry['database']['migration_count'] }} Migrations applied</div>
        </div>

        <div class="card p-4" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md);">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--c-neutral-500); text-transform: uppercase;">Admins Detected</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--c-neutral-900); margin-top: 0.5rem;">{{ $telemetry['database']['admin_count'] }} Accounts</div>
            <div style="font-size: 0.75rem; color: var(--c-neutral-500); margin-top: 0.25rem;">Super admin access active</div>
        </div>
    </div>

    <!-- Non-Destructive Actions Panel -->
    <div class="card p-5" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md); margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.125rem; font-weight: 600; color: var(--c-neutral-900); margin-bottom: 0.5rem;">Maintenance and Recovery Actions</h2>
        <p style="font-size: 0.8125rem; color: var(--c-neutral-500); margin-bottom: 1.25rem;">
            These actions are strictly non-destructive. They will apply pending forward migrations, clear or rebuild caches, and adjust security locks without altering existing orders, customers, or user accounts.
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
            <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="action" value="run_migrations">
                <button type="submit" class="btn btn-secondary" onclick="return confirm('Apply any pending forward migrations safely? Existing data will be preserved.');">
                    Apply Pending Migrations
                </button>
            </form>

            <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="action" value="test_db">
                <button type="submit" class="btn btn-secondary">
                    Test Live DB Connection
                </button>
            </form>

            <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="action" value="clear_cache">
                <button type="submit" class="btn btn-secondary">
                    Clear System Caches
                </button>
            </form>

            <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="action" value="rebuild_cache">
                <button type="submit" class="btn btn-secondary">
                    Rebuild Caches (Production)
                </button>
            </form>

            <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="action" value="storage_link">
                <button type="submit" class="btn btn-secondary">
                    Connect Storage Link
                </button>
            </form>

            <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                @csrf
                <input type="hidden" name="action" value="ensure_key">
                <button type="submit" class="btn btn-secondary">
                    Verify APP_KEY
                </button>
            </form>

            @if ($telemetry['is_installed'])
                <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="action" value="reset_install">
                    <button type="submit" class="btn btn-secondary" style="color: var(--c-amber-800);" onclick="return confirm('Reset installer lock and unlock setup wizard? Visitors will be redirected to /install.');">
                        Reset Install & Unlock
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.installer.recovery.action') }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="action" value="lock_installer">
                    <button type="submit" class="btn btn-primary">
                        Lock Installer (Protect System)
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Essential Application Settings Safe Update -->
    <div class="card p-5" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md); margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.125rem; font-weight: 600; color: var(--c-neutral-900); margin-bottom: 0.5rem;">Environment Adjustments</h2>
        <p style="font-size: 0.8125rem; color: var(--c-neutral-500); margin-bottom: 1.25rem;">
            Update core application environment values safely. Changes are committed to <code>.env</code> with automatic timestamped backup.
        </p>

        <form method="POST" action="{{ route('admin.installer.recovery.action') }}">
            @csrf
            <input type="hidden" name="action" value="update_env">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="app_name">Application Name</label>
                    <input type="text" id="app_name" name="app_name" class="form-input" value="{{ old('app_name', $env['APP_NAME'] ?? 'LeadX') }}">
                </div>

                <div class="form-group">
                    <label class="form-label" for="app_url">Application URL</label>
                    <input type="url" id="app_url" name="app_url" class="form-input" value="{{ old('app_url', $env['APP_URL'] ?? url('/')) }}">
                </div>

                <div class="form-group">
                    <label class="form-label" for="queue_driver">Queue Connection</label>
                    <select id="queue_driver" name="queue_driver" class="form-select">
                        <option value="sync" {{ ($env['QUEUE_CONNECTION'] ?? 'sync') === 'sync' ? 'selected' : '' }}>sync (Immediate)</option>
                        <option value="database" {{ ($env['QUEUE_CONNECTION'] ?? 'sync') === 'database' ? 'selected' : '' }}>database (Async Background Worker)</option>
                        <option value="redis" {{ ($env['QUEUE_CONNECTION'] ?? 'sync') === 'redis' ? 'selected' : '' }}>redis (High Performance)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Save Environment Settings
            </button>
        </form>
    </div>

    <!-- Server Runtime & Extension Audit -->
    <div class="card p-5" style="background: var(--c-white); border: 1px solid var(--c-neutral-200); border-radius: var(--radius-md);">
        <h2 style="font-size: 1.125rem; font-weight: 600; color: var(--c-neutral-900); margin-bottom: 0.5rem;">Server Runtime & Extension Audit</h2>
        <p style="font-size: 0.8125rem; color: var(--c-neutral-500); margin-bottom: 1rem;">
            Real-time status of required PHP extensions and directory permissions.
        </p>

        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0.5rem 0.75rem;">Component</th>
                        <th style="text-align: left; padding: 0.5rem 0.75rem;">Requirement / Path</th>
                        <th style="text-align: right; padding: 0.5rem 0.75rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 0.5rem 0.75rem; font-weight: 500;">PHP Engine</td>
                        <td style="padding: 0.5rem 0.75rem; color: var(--c-neutral-500);">Version {{ $requirements['php']['value'] }}</td>
                        <td style="padding: 0.5rem 0.75rem; text-align: right;">
                            <span class="badge {{ $requirements['php']['pass'] ? 'badge-success' : 'badge-danger' }}">
                                {{ $requirements['php']['pass'] ? 'Pass' : 'Fail' }}
                            </span>
                        </td>
                    </tr>
                    @foreach ($requirements['extensions'] as $ext => $item)
                        <tr>
                            <td style="padding: 0.5rem 0.75rem; font-weight: 500;">{{ $item['label'] }}</td>
                            <td style="padding: 0.5rem 0.75rem; color: var(--c-neutral-500); font-size: 0.75rem;">{{ $item['description'] }}</td>
                            <td style="padding: 0.5rem 0.75rem; text-align: right;">
                                <span class="badge {{ $item['pass'] ? 'badge-success' : 'badge-danger' }}">
                                    {{ $item['pass'] ? 'Installed' : 'Missing' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($requirements['directories'] as $dir => $item)
                        <tr>
                            <td style="padding: 0.5rem 0.75rem; font-weight: 500;">{{ $item['label'] }}</td>
                            <td style="padding: 0.5rem 0.75rem; color: var(--c-neutral-500); font-size: 0.75rem;">{{ $item['path'] }}</td>
                            <td style="padding: 0.5rem 0.75rem; text-align: right;">
                                <span class="badge {{ $item['pass'] ? 'badge-success' : 'badge-danger' }}">
                                    {{ $item['pass'] ? 'Writable' : 'Forbidden' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
