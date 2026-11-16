@extends('layouts.admin-dashboard')
@section('title', 'Backup settings')
@section('content')
<x-page-header title="Backup settings" subtitle="Set the daily backup time, retention window, and archive destination." />
@include('admin.backups._nav')
<section class="card p-5" aria-labelledby="backup-settings-title" style="max-width: 48rem">
    <h2 id="backup-settings-title" class="text-lg font-semibold mb-5">Schedule & retention</h2>
    <form method="POST" action="{{ route('admin.backups.settings.update') }}" data-backup-action="Saving…">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="schedule-time" class="form-label">Daily backup time ({{ config('app.timezone', 'UTC') }})</label>
                <input id="schedule-time" type="time" name="schedule_time" class="form-input" value="{{ old('schedule_time', $scheduleTime) }}" required aria-describedby="schedule-time-hint schedule-time-error">
                <p id="schedule-time-hint" class="form-hint">Uses the server scheduler in the displayed timezone.</p>
                @error('schedule_time')<p id="schedule-time-error" class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="retention-days" class="form-label">Keep backups for (days)</label>
                <input id="retention-days" type="number" name="retention_days" class="form-input" min="1" max="365" value="{{ old('retention_days', $retentionDays) }}" required aria-describedby="retention-days-hint retention-days-error">
                <p id="retention-days-hint" class="form-hint">1–365 days. Older backups are pruned only when a newer verified archive exists.</p>
                @error('retention_days')<p id="retention-days-error" class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-5">
            <label for="disk" class="form-label">Backup destination</label>
            <select id="disk" name="disk" class="form-select" required aria-describedby="disk-hint disk-error">
                @foreach ($availableDisks as $disk)
                    <option value="{{ $disk }}" @selected(old('disk', $currentDisk) === $disk)>{{ $disk === 'local' ? 'Local storage' : strtoupper($disk).' remote storage' }}</option>
                @endforeach
            </select>
            <p id="disk-hint" class="form-hint">Remote destinations require working storage credentials and a configured driver. A local archive is kept for downloads and restores.</p>
            @error('disk')<p id="disk-error" class="form-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary mt-6">Save backup settings</button>
    </form>
</section>
@endsection
