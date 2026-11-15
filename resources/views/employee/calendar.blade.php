@extends('layouts.employee')

@section('title', 'My Google Calendar')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-neutral-900">Google Calendar Integration</h1>
        <p class="text-sm text-neutral-500">Sync customer follow-ups and expiring subscription reminders directly to your Google Calendar.</p>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-800">
            {{ session('error') }}
        </div>
    @endif

    {{-- 36-Hour Expiry Notification Configuration Banner --}}
    <div class="rounded-2xl border border-blue-200 bg-blue-50/70 p-5 shadow-2xs">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-blue-900">36-Hour Advance Expiry Notification Configured</h3>
                <p class="mt-1 text-xs text-blue-800 leading-relaxed">
                    LeadX synchronizes subscription expiration reminders directly to your Google Calendar with a mandatory <strong>36-hour lead time</strong> (2,160 minutes before expiration). You will receive both a Google Calendar popup alarm and an email notification 36 hours prior to any customer subscription ending.
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs">
        <div class="flex items-start justify-between border-b border-neutral-100 pb-5">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-neutral-900">Google Calendar Sync</h2>
                    <p class="text-xs text-neutral-500">Scheduled reminders are automatically placed on your personal calendar.</p>
                </div>
            </div>

            @if ($connection)
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                    Connected
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600">
                    Not Connected
                </span>
            @endif
        </div>

        <div class="mt-6 space-y-4">
            @if ($connection)
                <div class="rounded-xl bg-neutral-50 p-4 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-neutral-500">Connected Account:</span>
                        <span class="font-semibold text-neutral-800">{{ $connection->google_email ?? 'Google Account' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500">Calendar ID:</span>
                        <span class="font-mono text-neutral-800">{{ $connection->calendar_id ?? 'primary' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-neutral-500">Connected Since:</span>
                        <span class="text-neutral-800">{{ $connection->created_at?->format('M d, Y H:i') ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-neutral-200">
                        <span class="text-neutral-500">Expiry Reminder Lead Time:</span>
                        <span class="font-semibold text-blue-700">36 Hours Before Expiry (Popup & Email)</span>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <form action="{{ route('employee.calendar.disconnect') }}" method="POST" onsubmit="return confirm('Disconnect Google Calendar?');">
                        @csrf
                        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                            Disconnect Calendar
                        </button>
                    </form>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-neutral-900">No Google Calendar Connected</p>
                    <p class="mt-1 text-xs text-neutral-500 max-w-sm mx-auto">
                        Connect your personal Google account to synchronize follow-up appointments and receive automatic notifications 36 hours before subscription expiry.
                    </p>
                    <div class="mt-5">
                        <a href="{{ route('employee.calendar.connect') }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            Connect Google Calendar
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
