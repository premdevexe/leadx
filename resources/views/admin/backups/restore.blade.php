@extends('layouts.admin-dashboard')
@section('title', 'Restore a backup')
@section('content')
<x-page-header title="Restore a backup" subtitle="Recover your database or uploaded files from a verified archive." />
@include('admin.backups._nav')
<div class="alert alert-warning mb-5" role="note">
    <div><p class="font-semibold">Restoring replaces current data.</p><p class="text-sm mt-1">Choose the archive and contents carefully. The system checks integrity and creates a safety snapshot before starting. Keep this page open until the restore finishes.</p></div>
</div>
<section class="card p-5 mb-6" aria-labelledby="restore-form-title">
    <h2 id="restore-form-title" class="text-lg font-semibold mb-4">Recovery options</h2>
    @if ($verifiedBackups->isEmpty())
        <x-empty-state title="No verified backups available" description="Create a backup and verify it before starting a restore."><x-slot:action><a class="btn btn-primary" href="{{ route('admin.backups.index') }}">Go to backups</a></x-slot:action></x-empty-state>
    @else
        <form method="POST" action="{{ route('admin.backups.restore.perform') }}" data-backup-action="Restoring…" data-confirm="Restore the selected archive and replace current data?">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="backup-id" class="form-label">Verified archive</label>
                    <select id="backup-id" name="backup_id" class="form-select" required aria-describedby="backup-id-error">
                        <option value="">Choose a backup</option>
                        @foreach ($verifiedBackups as $backup)
                            <option value="{{ $backup->backup_id }}" data-backup-type="{{ $backup->backup_type }}" @selected(old('backup_id', request('backup_id')) === $backup->backup_id)>{{ $backup->created_at->format('M d, Y H:i') }} · {{ ucfirst($backup->backup_type) }} · {{ $backup->backup_id }}</option>
                        @endforeach
                    </select>
                    @error('backup_id')<p id="backup-id-error" class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="restore-type" class="form-label">Contents to restore</label>
                    <select id="restore-type" name="restore_type" class="form-select" required aria-describedby="restore-type-error">
                        @foreach (['full' => 'Database and files', 'database' => 'Database only', 'storage' => 'Uploaded files only'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('restore_type', 'full') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('restore_type')<p id="restore-type-error" class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-5" style="max-width: 30rem">
                <label for="confirm-text" class="form-label">Type CONFIRM RESTORE to continue</label>
                <input id="confirm-text" name="confirm_text" class="form-input" required autocomplete="off" spellcheck="false" aria-describedby="confirm-text-hint confirm-text-error">
                <p id="confirm-text-hint" class="form-hint">This confirms that current data can be replaced by the selected backup.</p>
                @error('confirm_text')<p id="confirm-text-error" class="form-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-danger mt-5">Restore selected backup</button>
        </form>
    @endif
</section>
<section class="card p-5" aria-labelledby="restore-history-title">
    <h2 id="restore-history-title" class="text-lg font-semibold mb-4">Restore history</h2>
    @forelse ($restoreLogs as $log)
        <div class="py-4 border-t border-neutral-200">
            <div class="flex flex-wrap items-center gap-3"><x-status-badge :status="$log->status" /><span class="text-sm">{{ $log->started_at?->format('M d, Y H:i') }} · {{ $log->admin?->name ?? 'Deleted admin' }} · {{ ucfirst($log->restore_type) }}</span></div>
            <p class="text-sm mt-2" style="overflow-wrap: anywhere">Archive: @if($log->backup)<a class="underline" href="{{ route('admin.backups.show', $log->backup) }}">{{ $log->backup_id }}</a>@else{{ $log->backup_id }} (deleted)@endif</p>
            @if ($log->pre_restore_backup_id)<p class="text-sm text-neutral-600 mt-1" style="overflow-wrap: anywhere">Safety snapshot: {{ $log->pre_restore_backup_id }}</p>@endif
            @if ($log->error_message)<p class="text-sm text-red-700 mt-2">{{ $log->error_message }}</p>@endif
            @if ($log->log_output)<details class="mt-3 text-sm"><summary class="cursor-pointer">Execution log</summary><pre class="mt-2 p-3 bg-neutral-50 rounded-lg" style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $log->log_output }}</pre></details>@endif
        </div>
    @empty
        <p class="text-sm text-neutral-600">No restores have been requested.</p>
    @endforelse
    <div class="mt-4">{{ $restoreLogs->links() }}</div>
</section>
@endsection
@push('scripts')
<script>
const backupPicker = document.getElementById('backup-id');
const restorePicker = document.getElementById('restore-type');
function matchRestoreContents() {
    const type = backupPicker?.selectedOptions[0]?.dataset.backupType;
    if (!restorePicker) return;
    [...restorePicker.options].forEach(option => option.disabled = Boolean(type && type !== 'full' && option.value !== type));
    if (restorePicker.selectedOptions[0]?.disabled) restorePicker.value = type;
}
backupPicker?.addEventListener('change', matchRestoreContents);
matchRestoreContents();
</script>
@endpush
