@extends('layouts.'.$portal)

@section('title', 'Follow-up Details')

@section('content')
<x-page-header title="Follow-up Details" subtitle="Track the conversation, next steps, and activity history.">
    <x-slot:actions><a href="{{ route($portal.'.follow-ups') }}" class="btn btn-secondary">All Follow-ups</a></x-slot:actions>
</x-page-header>
@include('employee.followups._feedback')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="space-y-6 min-w-0">
        <section class="card p-6" aria-labelledby="followup-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="followup-title" class="text-lg font-semibold break-words">{{ $followUp->title }}</h2>
                <x-status-badge :status="$followUp->status" />
            </div>
            <dl class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5 text-sm">
                <div><dt class="text-neutral-500">Customer</dt><dd class="mt-1 font-medium">{{ $followUp->customer?->name ?? 'Unavailable' }}</dd></div>
                <div><dt class="text-neutral-500">Assigned employee</dt><dd class="mt-1 font-medium">{{ $followUp->assignedEmployee?->name ?? 'Unassigned' }}</dd></div>
                <div><dt class="text-neutral-500">Scheduled ({{ config('app.timezone') }})</dt><dd class="mt-1">{{ $followUp->scheduled_at?->format('M j, Y · g:i A') ?? 'Not scheduled' }}</dd></div>
                <div><dt class="text-neutral-500">Priority</dt><dd class="mt-1">{{ ucfirst($followUp->priority) }}</dd></div>
                <div><dt class="text-neutral-500">Type</dt><dd class="mt-1">{{ ucwords(str_replace('_', ' ', $followUp->follow_up_type)) }}</dd></div>
                <div><dt class="text-neutral-500">Calendar sync</dt><dd class="mt-1">{{ ucwords(str_replace('_', ' ', $followUp->calendar_sync_status ?? 'not_synced')) }}</dd></div>
            </dl>
            @if ($followUp->description)
                <p class="mt-5 whitespace-pre-wrap break-words text-sm">{{ $followUp->description }}</p>
            @endif
            @if ($followUp->outcome)
                <div class="mt-5 border-t pt-4"><h3 class="text-sm font-semibold">Outcome</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $followUp->outcome }}</p></div>
            @endif
        </section>

        <section class="card p-6" aria-labelledby="history-title">
            <h2 id="history-title" class="text-base font-semibold">Activity History</h2>
            <ol class="mt-4 divide-y">
                @forelse ($followUp->history as $entry)
                    <li class="py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2"><x-status-badge :status="$entry->status" /><time class="text-sm text-neutral-500" datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('M j, Y · g:i A') }}</time></div>
                        @if ($entry->scheduled_at)<p class="mt-2 text-sm">Scheduled for {{ $entry->scheduled_at->format('M j, Y · g:i A') }}</p>@endif
                        @if ($entry->notes)<p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $entry->notes }}</p>@endif
                        @if ($entry->outcome)<p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $entry->outcome }}</p>@endif
                    </li>
                @empty
                    <li class="py-4 text-sm text-neutral-500">No activity has been recorded yet.</li>
                @endforelse
            </ol>
        </section>
    </div>

    @if ($canManage && $followUp->status !== 'completed')
        <section class="card p-6 self-start" aria-labelledby="update-title">
            <h2 id="update-title" class="text-base font-semibold">Update Follow-up</h2>
            <p class="mt-1 text-sm text-neutral-500">Record the outcome or choose a new appointment time.</p>
            <form method="POST" action="{{ route($portal.'.follow-ups.reschedule', $followUp) }}" class="mt-5 space-y-5">
                @csrf
                @if ($followUp->status !== 'cancelled')
                    <div class="form-group">
                        <label for="outcome" class="form-label">Outcome <span class="text-neutral-500">(optional, when completing)</span></label>
                        <textarea id="outcome" name="outcome" rows="3" maxlength="4000" class="form-textarea w-full" aria-describedby="outcome-error" @error('outcome') aria-invalid="true" @enderror>{{ old('outcome') }}</textarea>
                        <p id="outcome-error" class="text-sm text-red-600">@error('outcome'){{ $message }}@enderror</p>
                    </div>
                @endif
                <div class="form-group">
                    <label for="scheduled_at" class="form-label">New date and time <span class="text-neutral-500">(required to reschedule)</span></label>
                    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="form-input w-full" aria-describedby="scheduled_at-error" @error('scheduled_at') aria-invalid="true" @enderror>
                    <p id="scheduled_at-error" class="text-sm text-red-600">@error('scheduled_at'){{ $message }}@enderror</p>
                </div>
                <div class="form-group">
                    <label for="notes" class="form-label">Activity note <span class="text-neutral-500">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000" class="form-textarea w-full" aria-describedby="notes-error" @error('notes') aria-invalid="true" @enderror>{{ old('notes') }}</textarea>
                    <p id="notes-error" class="text-sm text-red-600">@error('notes'){{ $message }}@enderror</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($followUp->status !== 'cancelled')
                        <button type="submit" formaction="{{ route($portal.'.follow-ups.complete', $followUp) }}" class="btn btn-primary">Mark Completed</button>
                    @endif
                    <button type="submit" class="btn btn-secondary">Reschedule</button>
                    @if ($followUp->status !== 'cancelled')
                        <button type="submit" formaction="{{ route($portal.'.follow-ups.cancel', $followUp) }}" class="btn btn-danger" onclick="return confirm('Cancel this follow-up? Its activity history will be retained.');">Cancel Follow-up</button>
                    @endif
                </div>
            </form>
        </section>
    @endif
</div>
@endsection
