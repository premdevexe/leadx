@extends('layouts.admin-dashboard')

@section('title', 'Send Notification')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Send notification</h1>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <p class="mt-2 text-sm text-neutral-500">
        Sends a web push notification to every registered device subscribed to the interest(s) below
        (e.g. <code class="rounded bg-neutral-100 px-1 py-0.5 font-mono text-xs">{{ config('services.pusher.device_interest', 'hello') }}</code>).
        Devices register when an admin opens the app with notifications allowed.
    </p>

    <form method="POST" action="{{ route('admin.notifications.store') }}"
          class="mt-5 max-w-xl rounded-xl border border-neutral-200 bg-white p-5">
        @csrf

        <div class="grid gap-4">
            <div>
                <label class="block text-xs font-medium text-neutral-500" for="title">Title</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" maxlength="100" required
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500" for="body">Body</label>
                <textarea name="body" id="body" rows="3" maxlength="300" required
                          class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">{{ old('body') }}</textarea>
                @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500" for="interests">
                    Interests <span class="text-neutral-400">(comma-separated, defaults to <code class="font-mono text-xs">{{ config('services.pusher.device_interest', 'hello') }}</code>)</span>
                </label>
                <p class="mt-1 text-xs text-neutral-400">
                    Interest names may only contain letters, numbers and <code class="font-mono">_ - = @ , . ;</code> — no spaces (use e.g. <code class="font-mono">Divi-Chellow</code> instead of "Divi Chellow").
                </p>
                <input type="text" name="interests" id="interests" value="{{ old('interests', config('services.pusher.device_interest', 'hello')) }}" required
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-sm focus:border-neutral-500 focus:outline-none">
                @error('interests') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
                Send notification
            </button>
        </div>
    </form>

    <div class="mt-10 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold tracking-tight">Send history</h2>
        <div class="flex flex-wrap items-center gap-3 text-xs text-neutral-400">
            @php $filterKey = $status === 'failed' ? 'failed' : ($status === 'sent' ? 'sent' : 'all'); @endphp
            <div class="flex items-center gap-1">
                @foreach ([['all', 'All'], ['failed', 'Failed'], ['sent', 'Sent']] as [$key, $label])
                    <a href="{{ $key === 'all' ? route('admin.notifications') : route('admin.notifications', ['status' => $key]) }}"
                       class="rounded-lg px-2.5 py-1 font-medium transition-colors {{ $filterKey === $key ? 'bg-neutral-900 text-white' : 'hover:bg-neutral-100 hover:text-neutral-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('admin.notifications') }}" class="flex items-center gap-2">
                @if ($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <select name="limit" onchange="this.form.submit()"
                        class="rounded-lg border border-neutral-300 px-2 py-1 text-xs focus:border-neutral-500 focus:outline-none">
                    @foreach ([10, 25, 50, 100] as $option)
                        <option value="{{ $option }}" @selected($limit === $option)>Last {{ $option }}</option>
                    @endforeach
                </select>
            </form>
            @if ($history->isNotEmpty())
                <form method="POST" action="{{ route('admin.notifications.clear') }}"
                      onsubmit="return confirm('Clear all notification history? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50">
                        Clear history
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($history->isEmpty())
        <div class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            No notifications sent yet.
        </div>
    @else
        <div class="mt-4 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-xs text-neutral-500">
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Body</th>
                        <th class="px-4 py-3 font-medium">Interests</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Publish ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($history as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-neutral-500">{{ $log->created_at->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-neutral-800">{{ $log->title }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ Str::limit($log->body, 60) }}</td>
                            <td class="px-4 py-3">
                                @foreach ($log->interestList() as $interest)
                                    <span class="mr-1 rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-600">{{ $interest }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-xs {{ $log->status === 'sent' ? 'text-emerald-700' : 'text-red-600' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $log->status === 'sent' ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $log->status }}
                                </span>
                                @if ($log->error)
                                    <div class="mt-1 max-w-xs text-xs text-red-500" title="{{ $log->error }}">{{ Str::limit($log->error, 120) }}</div>
                                @endif
                                @if ($log->status !== 'sent')
                                    <form method="POST"
                                          action="{{ route('admin.notifications.resend', $log) }}"
                                          class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="text"
                                               name="interests"
                                               value="{{ session('retry_interests.'.$log->id, $log->interests) }}"
                                               placeholder="Interests (comma-separated)"
                                               title="Interests this retry will be sent to — edit to correct a failed interest, e.g. replace a space with a hyphen"
                                               class="w-44 rounded-lg border border-neutral-300 px-2 py-1 font-mono text-xs focus:border-neutral-500 focus:outline-none">
                                        <button type="submit"
                                                class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-500 hover:bg-neutral-50">
                                            ↻ Retry
                                        </button>
                                    </form>
                                    @if (session('retry_error.'.$log->id))
                                        <p class="mt-1 text-xs text-red-600">{{ session('retry_error.'.$log->id) }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-400">{{ $log->publish_id ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
