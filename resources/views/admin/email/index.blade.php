@extends('layouts.admin-dashboard')

@section('title', 'Email Management')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Email Management</h1>
    <p class="mt-1 text-sm text-neutral-500">Configure SMTP settings, manage templates, notification events, and view delivery logs.</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
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
        <button type="button" onclick="switchTab('smtp')" id="tab-smtp-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg bg-neutral-900 text-white">SMTP Configuration</button>
        <button type="button" onclick="switchTab('events')" id="tab-events-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Notification Events</button>
        <button type="button" onclick="switchTab('templates')" id="tab-templates-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Templates</button>
        <button type="button" onclick="switchTab('reminders')" id="tab-reminders-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Expiry Reminders</button>
        <button type="button" onclick="switchTab('logs')" id="tab-logs-btn" class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg text-neutral-500 hover:text-neutral-700">Email Logs</button>
    </nav>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 1 – SMTP CONFIGURATION
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-smtp" class="tab-content mt-5 space-y-5">
        <form method="POST" action="{{ route('admin.email.smtp.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-700">SMTP Connection Settings</h2>
                <p class="mt-1 text-xs text-neutral-500">Credentials are encrypted at rest and never exposed in API responses.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="smtp_host" class="text-sm font-medium text-neutral-800">SMTP Host *</label>
                        <input id="smtp_host" name="smtp_host" type="text" value="{{ $smtpHost }}" required maxlength="255"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('smtp_host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_port" class="text-sm font-medium text-neutral-800">SMTP Port *</label>
                        <input id="smtp_port" name="smtp_port" type="number" value="{{ $smtpPort }}" required min="1" max="65535"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('smtp_port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_username" class="text-sm font-medium text-neutral-800">SMTP Username</label>
                        <input id="smtp_username" name="smtp_username" type="text" value="{{ $smtpUser }}" maxlength="255"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('smtp_username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_password" class="text-sm font-medium text-neutral-800">SMTP Password / App Password</label>
                        <input id="smtp_password" name="smtp_password" type="password"
                               placeholder="{{ $hasSmtp ? '•••••••••••• (leave blank to keep)' : 'Enter password' }}"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        <p class="mt-1 text-xs text-neutral-400">Leave blank to keep the existing password.</p>
                        @error('smtp_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="smtp_encryption" class="text-sm font-medium text-neutral-800">Encryption *</label>
                        <select id="smtp_encryption" name="smtp_encryption"
                                class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                            <option value="tls" @selected($smtpEnc === 'tls')>TLS</option>
                            <option value="ssl" @selected($smtpEnc === 'ssl')>SSL</option>
                            <option value="none" @selected($smtpEnc === 'none')>None</option>
                        </select>
                        @error('smtp_encryption') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="connection_timeout" class="text-sm font-medium text-neutral-800">Connection Timeout (seconds) *</label>
                        <input id="connection_timeout" name="connection_timeout" type="number" value="{{ $timeout }}" min="1" max="120"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('connection_timeout') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="from_email" class="text-sm font-medium text-neutral-800">From Email Address *</label>
                        <input id="from_email" name="from_email" type="email" value="{{ $fromEmail }}" required maxlength="255"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('from_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="from_name" class="text-sm font-medium text-neutral-800">From Name *</label>
                        <input id="from_name" name="from_name" type="text" value="{{ $fromName }}" required maxlength="255"
                               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @error('from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">Save SMTP Settings</button>
                <span id="smtp-status-indicator" class="text-xs {{ $hasSmtp ? 'text-emerald-600' : 'text-neutral-400' }}">
                    {{ $hasSmtp ? '✓ Custom SMTP configured' : 'Using application default (.env)' }}
                </span>
            </div>
        </form>

        {{-- Test Email --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-700">Test Email</h2>
            <p class="mt-1 text-xs text-neutral-500">Send a test email to verify your SMTP configuration. Success means SMTP accepted the message.</p>

            <form method="POST" action="{{ route('admin.email.test') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-[200px]">
                    <label for="test_email" class="text-sm font-medium text-neutral-800">Recipient Email</label>
                    <input id="test_email" name="test_email" type="email" required
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                </div>
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">Send Test Email</button>
            </form>
            <p class="mt-2 text-xs text-neutral-400">Test emails use your configured SMTP settings directly. Never expose credentials in test results.</p>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 2 – NOTIFICATION EVENTS
         ════════════════════════════════════════════════════════════════ --}}
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
                            <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm {{ $isChecked ? 'border-neutral-400 bg-neutral-50' : '' }}">
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
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">Save Event Settings</button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 3 – TEMPLATES
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-templates" class="tab-content mt-5 space-y-4">
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
                    // Sample preview with sample values so the admin can see
                    // what the rendered email will look like.
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
                               @if($template->is_default) · <span class="text-amber-600">Default</span> @endif
                               · <span class="{{ $template->is_active ? 'text-emerald-600' : 'text-red-600' }}">{{ $template->is_active ? 'Active' : 'Disabled' }}</span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="document.getElementById('preview-{{ $template->id }}').classList.toggle('hidden')"
                                    class="text-xs rounded-lg border border-neutral-300 px-3 py-1 text-neutral-600 hover:bg-neutral-50">Preview</button>
                            <form method="POST" action="{{ route('admin.email.templates.toggle', $template) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs rounded-lg border border-neutral-300 px-3 py-1 text-neutral-600 hover:bg-neutral-50">
                                    {{ $template->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Live preview (rendered with sample data) --}}
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
                                <label class="text-xs font-medium text-neutral-600">Name</label>
                                <input name="name" type="text" value="{{ old('name', $template->name) }}"
                                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                                @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs font-medium text-neutral-600">Subject</label>
                                <input name="subject" type="text" value="{{ old('subject', $template->subject) }}"
                                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                                @error('subject') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-neutral-600">Body HTML</label>
                            <textarea name="body" rows="5"
                                      class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm font-mono">{{ old('body', $template->body) }}</textarea>
                            @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-neutral-600">Description</label>
                            <p class="text-xs text-neutral-500">{{ $template->description ?? 'No description.' }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-2 text-sm text-neutral-700">
                                <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-neutral-300">
                                Active
                            </label>
                            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">Update Template</button>
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

    {{-- ════════════════════════════════════════════════════════════════
         TAB 4 – EXPIRY REMINDER SETTINGS (Section 6)
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-reminders" class="tab-content mt-5 hidden">
        @php
            $rc = $reminderConfig;
        @endphp

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
                    <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                        <input type="hidden" name="reminders_enabled" value="0">
                        <input type="checkbox" name="reminders_enabled" value="1" @checked($rc['enabled'])
                               class="rounded border-neutral-300">
                        <span class="text-neutral-800 font-medium">Enable expiry reminder emails</span>
                    </label>

                    <div>
                        <label class="text-sm font-medium text-neutral-800">Reminder days before expiry</label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-4">
                            @foreach ([1, 3, 7, 14, 30] as $day)
                                <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="reminder_days[]" value="{{ $day }}"
                                           @checked(in_array($day, $rc['reminder_days'], true))
                                           class="rounded border-neutral-300">
                                    <span>{{ $day }} day{{ $day > 1 ? 's' : '' }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-neutral-400">Unchecking all windows disables reminders entirely.</p>
                        @error('reminder_days') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="reminder_time" class="text-sm font-medium text-neutral-800">Reminder time (server timezone)</label>
                            <input id="reminder_time" name="reminder_time" type="time" value="{{ old('reminder_time', $rc['reminder_time']) }}"
                                   required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                            @error('reminder_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                            <input type="hidden" name="notify_customer" value="0">
                            <input type="checkbox" name="notify_customer" value="1" @checked($rc['notify_customer'])
                                   class="rounded border-neutral-300">
                            <span>Customer notification</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                            <input type="hidden" name="notify_team" value="0">
                            <input type="checkbox" name="notify_team" value="1" @checked($rc['notify_team'])
                                   class="rounded border-neutral-300">
                            <span>Assigned team notification</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">Save Reminder Settings</button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         TAB 5 – EMAIL LOGS
         ════════════════════════════════════════════════════════════════ --}}
    <div id="tab-logs" class="tab-content mt-5 hidden space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-neutral-700">Delivery History</h2>
            <form method="POST" action="{{ route('admin.email.logs.clear') }}" class="inline"
                  onsubmit="return confirm('Clear logs older than 90 days? This cannot be undone.');">
                @csrf
                <button type="submit" class="text-xs rounded-lg border border-neutral-300 px-3 py-1.5 text-neutral-600 hover:bg-neutral-50">Clear Old Logs (90+ days)</button>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50">
                        <th class="px-4 py-3 font-medium text-neutral-600">Recipient</th>
                        <th class="px-4 py-3 font-medium text-neutral-600">Event</th>
                        <th class="px-4 py-3 font-medium text-neutral-600">Subject</th>
                        <th class="px-4 py-3 font-medium text-neutral-600">Order</th>
                        <th class="px-4 py-3 font-medium text-neutral-600">Status</th>
                        <th class="px-4 py-3 font-medium text-neutral-600">Sent At</th>
                        <th class="px-4 py-3 font-medium text-neutral-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
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
                            <td class="px-4 py-3 text-neutral-700 max-w-[200px] truncate">{{ $log->subject }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $log->related_order_id ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded px-2 py-0.5 text-xs font-medium {{ $statusColors[$log->status] ?? 'bg-neutral-100 text-neutral-600' }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                                @if($log->error_summary)
                                    <p class="mt-0.5 text-xs text-red-500">{{ $log->error_summary }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-500 whitespace-nowrap">{{ $log->sent_at?->format('M d, H:i') ?? '—' }}</td>
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
@endsection

@section('scripts')
<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
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
}
</script>
@endsection
