@extends('layouts.admin-dashboard')

@section('title', 'Email Management')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Email Management</h1>
    <p class="mt-1 text-sm text-neutral-500">Configure SMTP settings, manage templates, notification events, and view delivery logs.</p>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 alert alert-danger text-sm">{{ session('error') }}</div>
    @endif

    @php
        $smtpHost     = old('smtp_host',     $smtpPayload['smtp_host'] ?? '');
        $smtpPort     = old('smtp_port',     $smtpPayload['smtp_port'] ?? 587);
        $smtpUser     = old('smtp_username', $smtpPayload['smtp_username'] ?? '');
        $smtpEnc      = old('smtp_encryption', $smtpPayload['smtp_encryption'] ?? 'tls');
        $fromEmail    = old('from_email',    $smtpPayload['from_email'] ?? config('mail.from.address'));
        $fromName     = old('from_name',     $smtpPayload['from_name'] ?? config('mail.from.name', config('app.name')));
        $timeout      = old('connection_timeout', $smtpPayload['connection_timeout'] ?? 10);
        $hasSmtp      = !empty($smtpHost);
    @endphp

    {{-- Tab Navigation --}}
    <nav class="mt-5 flex flex-wrap gap-1 border-b border-neutral-200" role="tablist">
        <button type="button" onclick="switchTab('smtp')" id="tab-smtp-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-primary">SMTP Configuration</button>
        <button type="button" onclick="switchTab('events')" id="tab-events-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-secondary text-neutral-500">Notification Events</button>
        <button type="button" onclick="switchTab('templates')" id="tab-templates-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-secondary text-neutral-500">Templates</button>
        <button type="button" onclick="switchTab('reminders')" id="tab-reminders-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-secondary text-neutral-500">Expiry Reminders</button>
        <button type="button" onclick="switchTab('logs')" id="tab-logs-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg btn btn-secondary text-neutral-500">Email Logs</button>
    </nav>

    {{-- TAB 1 – SMTP CONFIGURATION --}}
    <div id="tab-smtp" class="tab-content mt-5 space-y-5">
        <form method="POST" action="{{ route('admin.email.smtp.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-800">SMTP Connection Settings</h2>
                        <p class="mt-1 text-xs text-neutral-500">Credentials are encrypted at rest and never exposed in API responses.</p>
                    </div>
                    <label class="form-check text-xs font-medium text-neutral-700 cursor-pointer">
                        <input type="checkbox" name="is_enabled" value="1" @checked($smtp['is_enabled'] ?? true) class="rounded border-neutral-300">
                        <span>Enable SMTP</span>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="smtp_host" class="form-label">SMTP Host *</label>
                        <input id="smtp_host" name="smtp_host" type="text" value="{{ $smtpHost }}" required maxlength="255"
                               class="form-input">
                        @error('smtp_host') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_port" class="form-label">SMTP Port *</label>
                        <input id="smtp_port" name="smtp_port" type="number" value="{{ $smtpPort }}" required min="1" max="65535"
                               class="form-input">
                        @error('smtp_port') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_username" class="form-label">SMTP Username</label>
                        <input id="smtp_username" name="smtp_username" type="text" value="{{ $smtpUser }}" maxlength="255"
                               class="form-input">
                        @error('smtp_username') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_password" class="form-label">SMTP Password / App Password</label>
                        <input id="smtp_password" name="smtp_password" type="password"
                               placeholder="{{ $hasSmtp ? '•••••••••••• (leave blank to keep)' : 'Enter password' }}"
                               class="form-input">
                        <p class="form-hint">Leave blank to keep the existing password.</p>
                        @error('smtp_password') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_encryption" class="form-label">Encryption *</label>
                        <select id="smtp_encryption" name="smtp_encryption"
                                class="form-select mt-1 w-full">
                            <option value="tls" @selected($smtpEnc === 'tls')>TLS</option>
                            <option value="ssl" @selected($smtpEnc === 'ssl')>SSL</option>
                            <option value="none" @selected($smtpEnc === 'none')>None</option>
                        </select>
                        @error('smtp_encryption') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="connection_timeout" class="form-label">Connection Timeout (seconds) *</label>
                        <input id="connection_timeout" name="connection_timeout" type="number" value="{{ $timeout }}" min="1" max="120"
                               class="form-input">
                        @error('connection_timeout') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="from_email" class="form-label">From Email Address *</label>
                        <input id="from_email" name="from_email" type="email" value="{{ $fromEmail }}" required maxlength="255"
                               class="form-input">
                        @error('from_email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="from_name" class="form-label">From Name *</label>
                        <input id="from_name" name="from_name" type="text" value="{{ $fromName }}" required maxlength="255"
                               class="form-input">
                        @error('from_name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Save SMTP Settings
                </button>
                <span id="smtp-status-indicator" class="text-xs {{ $hasSmtp ? 'text-emerald-600' : 'text-neutral-400' }}">
                    {{ $hasSmtp ? '&#x2713; Custom SMTP configured' : 'Using application default (.env)' }}
                </span>
            </div>
        </form>
    </div>

    {{-- TAB 2 – NOTIFICATION EVENTS --}}
    <div id="tab-events" class="tab-content mt-5 hidden">
        <form method="POST" action="{{ route('admin.email.events.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            @php
                $groups = [
                    'customer'  => 'Customer Events',
                    'team'      => 'Team Events',
                    'employee'  => 'Employee Events',
                    'admin'     => 'Admin Events',
                ];
            @endphp

            @foreach ($groups as $cat => $label)
                @php $catEvents = $events->filter(fn($e, $k) => ($preferences[$k]->category ?? $e['category']) === $cat); @endphp
                @if ($catEvents->isNotEmpty())
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 class="text-sm font-semibold text-neutral-700">{{ $label }}</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($catEvents as $key => $meta)
                            @php
                                $pref = $preferences[$key] ?? null;
                                $enabledByDefault = ($pref && $pref->is_enabled);
                                $oldValues = old('enabled_events', []);
                                $isChecked = in_array($key, $oldValues, true) || (!old() && $enabledByDefault);
                            @endphp
                            <label class="form-check rounded-lg border border-neutral-200 px-3 py-2 {{ $isChecked ? 'border-neutral-400 bg-neutral-50' : '' }}">
                                <input type="checkbox" name="enabled_events[]" value="{{ $key }}" @checked($isChecked)
                                       class="rounded border-neutral-300">
                                <span class="text-neutral-800">{{ $meta['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Save Event Settings
                </button>
            </div>
        </form>
    </div>

    {{-- TAB 3 – TEMPLATES --}}
    <div id="tab-templates" class="tab-content mt-5 hidden space-y-4">
        <p class="text-sm text-neutral-500">
            Manage email templates. Default templates are preserved and cannot be deleted.
            Placeholders use double-brace syntax, e.g. <code class="bg-neutral-100 px-1 rounded text-xs">customer_name</code>,
            <code class="bg-neutral-100 px-1 rounded text-xs">order_number</code> — see the variable list below.
        </p>

        @foreach ($templates as $category => $catTemplates)
            @php
                $catLabel = match ($category) {
                    'customer' => 'Customer Templates',
                    'team'     => 'Team Templates',
                    'employee' => 'Employee Templates',
                    'admin'    => 'Admin Templates',
                    default    => ucfirst((string) $category).' Templates',
                };
            @endphp
            <h2 class="text-sm font-semibold text-neutral-700">{{ $catLabel }}</h2>
            @foreach ($catTemplates as $template)
                @php
                    $preview = app(\App\Services\EmailService::class)->render(
                        $template->body,
                        [
                            'customer_name'   => 'Jane Doe',
                            'customer_email'  => 'jane@example.com',
                            'order_number'    => 'TC-2026-00042',
                            'product_name'    => 'API Pro (1y)',
                            'product_price'   => '120.00',
                            'closing_value'   => '120.00',
                            'billing_cycle'   => 'yearly',
                            'activation_date' => 'Jan 15, 2026',
                            'expiry_date'     => 'Jan 15, 2027',
                            'team_name'       => 'Team Alpha',
                            'days_before'     => 7,
                        ]
                    );
                    $previewSubject = app(\App\Services\EmailService::class)->render($template->subject, []);
                @endphp
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-800">{{ $template->name }}</h3>
                            <p class="text-xs text-neutral-400">Key: <code class="bg-neutral-100 px-1 rounded">{{ $template->key }}</code>
                               @if($template->is_default) · <span class="badge badge-warning">Default</span> @endif
                               · <span class="{{ $template->is_active ? 'badge-success' : 'badge-danger' }}">{{ $template->is_active ? 'Active' : 'Disabled' }}</span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="document.getElementById('preview-{{ $template->id }}').classList.toggle('hidden')"
                                    class="btn btn-secondary px-3 py-1 text-xs font-medium text-neutral-600">
                                Preview
                            </button>
                            <form method="POST" action="{{ route('admin.email.templates.toggle', $template) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-secondary px-3 py-1 text-xs font-medium text-neutral-600">
                                    {{ $template->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div id="preview-{{ $template->id }}" class="mt-3 hidden rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold text-neutral-600 mb-2">Preview subject</p>
                        <p class="text-sm text-neutral-800">{{ $previewSubject }}</p>
                        <p class="text-xs font-semibold text-neutral-600 mt-3 mb-1">Preview body</p>
                        <div class="text-sm text-neutral-700">{!! nl2br(e($preview)) !!}</div>
                    </div>

                    <form method="POST" action="{{ route('admin.email.templates.update', $template) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="form-label text-xs">Name</label>
                                <input name="name" type="text" value="{{ old('name', $template->name) }}"
                                       class="form-input">
                                @error('name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label text-xs">Subject</label>
                                <input name="subject" type="text" value="{{ old('subject', $template->subject) }}"
                                       class="form-input">
                                @error('subject') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label text-xs">Body HTML</label>
                            <textarea name="body" rows="5"
                                      class="form-input font-mono">{{ old('body', $template->body) }}</textarea>
                            @error('body') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label text-xs">Description</label>
                            <p class="text-xs text-neutral-500">{{ $template->description ?? 'No description.' }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <label class="form-check text-sm text-neutral-700">
                                <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-neutral-300">
                                <span>Active</span>
                            </label>
                            <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                                Update Template
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        @endforeach

        <div class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-4">
            <h3 class="text-sm font-medium text-neutral-600">Available Variables</h3>
            <p class="mt-1 text-xs text-neutral-400">Use these placeholders in any template body or subject. Unknown variables are removed automatically.</p>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($availableVariables as $var)
                    <code class="bg-neutral-100 text-neutral-600 px-1.5 py-0.5 rounded text-xs">{{ $var }}</code>
                @endforeach
            </div>
        </div>
    </div>

    {{-- TAB 4 – EXPIRY REMINDERS --}}
    <div id="tab-reminders" class="tab-content mt-5 hidden">
        @php $rc = $reminderConfig; @endphp

        <form method="POST" action="{{ route('admin.email.reminders.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-700">Expiry &amp; Renewal Reminder Emails</h2>
                <p class="mt-1 text-xs text-neutral-500">
                    Uses the existing subscription expiry calculation. Reminders fire once per window per
                    expiry cycle; the scheduler runs at the configured time.
                </p>

                <div class="mt-4 space-y-4">
                    <label class="form-check rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                        <input type="hidden" name="reminders_enabled" value="0">
                        <input type="checkbox" name="reminders_enabled" value="1" @checked($rc['enabled'])
                               class="rounded border-neutral-300">
                        <span class="text-neutral-800 font-medium">Enable expiry reminder emails</span>
                    </label>

                    <div>
                        <label class="form-label">Reminder days before expiry</label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-4">
                            @foreach ([1, 3, 7, 14, 30] as $day)
                                <label class="form-check rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="reminder_days[]" value="{{ $day }}"
                                           @checked(in_array($day, $rc['reminder_days'], true))
                                           class="rounded border-neutral-300">
                                    <span>{{ $day }} day{{ $day > 1 ? 's' : '' }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="form-hint">Unchecking all windows disables reminders entirely.</p>
                        @error('reminder_days') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="reminder_time" class="form-label">Reminder time (server timezone)</label>
                            <input id="reminder_time" name="reminder_time" type="time" value="{{ old('reminder_time', $rc['reminder_time']) }}"
                                   required class="form-input">
                            @error('reminder_time') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="form-check rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                            <input type="hidden" name="notify_customer" value="0">
                            <input type="checkbox" name="notify_customer" value="1" @checked($rc['notify_customer'])
                                   class="rounded border-neutral-300">
                            <span>Customer notification</span>
                        </label>
                        <label class="form-check rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                            <input type="hidden" name="notify_team" value="0">
                            <input type="checkbox" name="notify_team" value="1" @checked($rc['notify_team'])
                                   class="rounded border-neutral-300">
                            <span>Assigned team notification</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Save Reminder Settings
                </button>
            </div>
        </form>
    </div>

    {{-- TAB 5 – EMAIL LOGS --}}
    <div id="tab-logs" class="tab-content mt-5 hidden space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-neutral-700">Delivery History</h2>
            <form method="POST" action="{{ route('admin.email.logs.clear') }}" class="inline"
                  onsubmit="return confirm('Clear logs older than 90 days? This cannot be undone.');">
                @csrf
                <button type="submit" class="btn btn-secondary px-3 py-1.5 text-xs font-medium text-neutral-600">
                    Clear Old Logs (90+ days)
                </button>
            </form>
        </div>

        <div class="table-wrapper">
            <table class="data-table w-full" style="min-width:640px">
                <thead>
                    <tr class="text-left text-xs text-neutral-500 bg-neutral-50">
                        <th class="px-4 py-3 font-medium">Recipient</th>
                        <th class="px-4 py-3 font-medium">Event</th>
                        <th class="px-4 py-3 font-medium">Subject</th>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Sent At</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $statusColors = [
                                'pending'    => 'bg-neutral-100 text-neutral-600',
                                'processing' => 'bg-amber-100 text-amber-700',
                                'sent'       => 'bg-emerald-100 text-emerald-700',
                                'failed'     => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-neutral-700">{{ $log->recipient_email }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $log->event_key }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $log->subject }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $log->related_order_id ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $statusColors[$log->status] ?? 'bg-neutral-100 text-neutral-600' }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                                @if($log->error_summary)
                                    <p class="mt-0.5 text-xs text-red-500">{{ $log->error_summary }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-500">{{ $log->sent_at?->format('M d, H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($log->status !== 'sent')
                                    <form method="POST" action="{{ route('admin.email.logs.resend', $log) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">Resend</button>
                                    </form>
                                @else
                                    <span class="text-xs text-neutral-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-neutral-400">No email logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}

        <p class="text-xs text-neutral-400">
            Note: SMTP acceptance does not guarantee inbox delivery. "Sent" means the message was accepted by the mail server.
        </p>
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
        const hash = (window.location.hash || '').replace('#', '');
        if (hash && document.getElementById('tab-' + hash)) {
            switchTab(hash);
        }

        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                const tabId = this.id.replace('tab-', '').replace('-btn', '');
                if (tabId) {
                    switchTab(tabId);
                }
            });
        });
    });
    </script>
@endsection
