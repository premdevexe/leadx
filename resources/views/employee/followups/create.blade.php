@extends('layouts.'.$portal)

@section('title', 'Schedule Follow-up')

@section('content')
<div class="max-w-3xl mx-auto">
    <x-page-header title="Schedule Follow-up" subtitle="Plan the next conversation with a verified customer.">
        <x-slot:actions><a href="{{ route($portal.'.follow-ups') }}" class="btn btn-secondary">All Follow-ups</a></x-slot:actions>
    </x-page-header>
    @include('employee.followups._feedback')

    @if ($customers->isEmpty())
        <x-empty-state title="No verified customers yet" description="A customer becomes available here once a purchase has been verified. Contact your admin if a verified purchase is missing.">
            <x-slot:action><a href="{{ route($portal.'.customers') }}" class="btn btn-secondary">View Customers</a></x-slot:action>
        </x-empty-state>
    @else
        <form method="POST" action="{{ route($portal.'.follow-ups.store') }}" class="card p-6 space-y-5">
            @csrf
            <div class="form-group">
                <label for="customer_id" class="form-label">Customer <span aria-hidden="true">*</span></label>
                <select id="customer_id" name="customer_id" class="form-select w-full" required aria-describedby="customer_id-error" @error('customer_id') aria-invalid="true" @enderror>
                    <option value="">Select a verified customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                    @endforeach
                </select>
                <p id="customer_id-error" class="text-sm text-red-600">@error('customer_id'){{ $message }}@enderror</p>
            </div>
            <div class="form-group">
                <label for="title" class="form-label">Title <span aria-hidden="true">*</span></label>
                <input id="title" name="title" class="form-input w-full" value="{{ old('title') }}" maxlength="255" required aria-describedby="title-error" @error('title') aria-invalid="true" @enderror>
                <p id="title-error" class="text-sm text-red-600">@error('title'){{ $message }}@enderror</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="form-group">
                    <label for="scheduled_at" class="form-label">Scheduled date and time <span aria-hidden="true">*</span></label>
                    <input id="scheduled_at" name="scheduled_at" type="datetime-local" class="form-input w-full" value="{{ old('scheduled_at') }}" required aria-describedby="scheduled_at-help scheduled_at-error" @error('scheduled_at') aria-invalid="true" @enderror>
                    <p id="scheduled_at-help" class="text-sm text-neutral-500">Time zone: {{ config('app.timezone') }}</p>
                    <p id="scheduled_at-error" class="text-sm text-red-600">@error('scheduled_at'){{ $message }}@enderror</p>
                </div>
                <div class="form-group">
                    <label for="priority" class="form-label">Priority</label>
                    <select id="priority" name="priority" class="form-select w-full" aria-describedby="priority-error" @error('priority') aria-invalid="true" @enderror>
                        @foreach (\App\Models\FollowUp::PRIORITIES as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                    <p id="priority-error" class="text-sm text-red-600">@error('priority'){{ $message }}@enderror</p>
                </div>
                <div class="form-group">
                    <label for="follow_up_type" class="form-label">Type</label>
                    <select id="follow_up_type" name="follow_up_type" class="form-select w-full" aria-describedby="follow_up_type-error" @error('follow_up_type') aria-invalid="true" @enderror>
                        @foreach (\App\Models\FollowUp::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('follow_up_type', 'general') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    <p id="follow_up_type-error" class="text-sm text-red-600">@error('follow_up_type'){{ $message }}@enderror</p>
                </div>
                @if ($portal === 'team')
                    <div class="form-group">
                        <label for="assigned_employee_id" class="form-label">Assigned employee</label>
                        <select id="assigned_employee_id" name="assigned_employee_id" class="form-select w-full" aria-describedby="assigned_employee_id-error" @error('assigned_employee_id') aria-invalid="true" @enderror>
                            <option value="">Unassigned</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('assigned_employee_id') == $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <p id="assigned_employee_id-error" class="text-sm text-red-600">@error('assigned_employee_id'){{ $message }}@enderror</p>
                    </div>
                @endif
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Description <span class="text-neutral-500">(optional)</span></label>
                <textarea id="description" name="description" rows="4" maxlength="4000" class="form-textarea w-full" aria-describedby="description-error" @error('description') aria-invalid="true" @enderror>{{ old('description') }}</textarea>
                <p id="description-error" class="text-sm text-red-600">@error('description'){{ $message }}@enderror</p>
            </div>
            @if ($portal === 'employee')
                <div>
                    <label class="flex items-center gap-3" for="sync_to_calendar">
                        <input id="sync_to_calendar" name="sync_to_calendar" type="checkbox" value="1" @checked(old('sync_to_calendar')) aria-describedby="sync_to_calendar-help sync_to_calendar-error">
                        <span class="text-sm">Sync to my connected Google Calendar</span>
                    </label>
                    <p id="sync_to_calendar-help" class="mt-1 text-sm text-neutral-500">Calendar connection is managed from <a href="{{ route('employee.calendar') }}" class="underline">Google Calendar settings</a>.</p>
                    <p id="sync_to_calendar-error" class="text-sm text-red-600">@error('sync_to_calendar'){{ $message }}@enderror</p>
                </div>
            @endif
            <div class="flex flex-wrap gap-3 border-t pt-5">
                <button type="submit" class="btn btn-primary">Schedule Follow-up</button>
                <a href="{{ route($portal.'.follow-ups') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    @endif
</div>
@endsection
