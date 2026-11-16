@extends('layouts.admin-dashboard')
@section('title', 'Backup details')
@section('content')
<x-page-header title="Backup details" :subtitle="$backup->backup_id" />
@include('admin.backups._nav')
<section class="card p-5 mb-5" aria-labelledby="archive-overview-title">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h2 id="archive-overview-title" class="text-lg font-semibold">Archive overview</h2>
        <x-status-badge :status="$backup->integrity_status" />
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 text-sm">
        <div><dt class="text-neutral-600">Contents</dt><dd class="font-medium mt-1">{{ ucfirst($backup->backup_type) }}</dd></div>
        <div><dt class="text-neutral-600">Archive size</dt><dd class="font-medium mt-1">{{ $backup->formatted_size }}</dd></div>
        <div><dt class="text-neutral-600">Created</dt><dd class="font-medium mt-1">{{ $backup->created_at->format('M d, Y H:i') }}</dd></div>
        <div><dt class="text-neutral-600">Created by</dt><dd class="font-medium mt-1">{{ $backup->creator?->name ?? 'Scheduled task' }}</dd></div>
        <div><dt class="text-neutral-600">Encryption</dt><dd class="font-medium mt-1">{{ $backup->is_encrypted ? 'Encrypted' : 'Not encrypted' }}</dd></div>
        <div><dt class="text-neutral-600">Last verified</dt><dd class="font-medium mt-1">{{ $backup->verified_at?->format('M d, Y H:i') ?? 'Not yet verified' }}</dd></div>
        <div><dt class="text-neutral-600 mb-1">Database</dt><dd><x-status-badge :status="$backup->database_status" /></dd></div>
        <div><dt class="text-neutral-600 mb-1">Uploaded files</dt><dd><x-status-badge :status="$backup->storage_status" /></dd></div>
        <div><dt class="text-neutral-600 mb-1">Remote copy ({{ strtoupper($backup->disk) }})</dt><dd><x-status-badge :status="$backup->remote_status" /></dd></div>
    </dl>
    <div class="mt-5 text-sm"><p class="text-neutral-600">SHA-256 checksum</p><code class="block mt-1" style="overflow-wrap: anywhere">{{ $backup->checksum ?? 'Not available' }}</code></div>
    @if ($backup->error_message)<div class="alert alert-danger mt-5" role="alert">{{ $backup->error_message }}</div>@endif
    <div class="flex flex-wrap gap-3 mt-6">
        <form method="POST" action="{{ route('admin.backups.verify', $backup) }}" data-backup-action="Verifying…">@csrf<button class="btn btn-primary" type="submit">Verify integrity</button></form>
        <a class="btn btn-secondary" href="{{ route('admin.backups.download', $backup) }}">Download archive</a>
        @if ($backup->integrity_status === 'verified' && $backup->database_status !== 'failed' && $backup->storage_status !== 'failed')
            <a class="btn btn-secondary" href="{{ route('admin.backups.restore', ['backup_id' => $backup->backup_id]) }}">Restore from this backup</a>
        @endif
    </div>
</section>

<section class="card p-5 mb-5" aria-labelledby="restore-history-title">
    <h2 id="restore-history-title" class="text-lg font-semibold mb-4">Restore history</h2>
    @forelse ($backup->restoreLogs as $log)
        <div class="border-t border-neutral-200 py-4">
            <div class="flex flex-wrap gap-3 items-center"><x-status-badge :status="$log->status" /><span class="text-sm">{{ $log->started_at?->format('M d, Y H:i') }} · {{ $log->admin?->name ?? 'Deleted admin' }} · {{ ucfirst($log->restore_type) }}</span></div>
            @if ($log->error_message)<p class="text-sm text-red-700 mt-2">{{ $log->error_message }}</p>@endif
            @if ($log->log_output)<details class="mt-3 text-sm"><summary class="cursor-pointer">Execution log</summary><pre class="mt-2 p-3 bg-neutral-50 rounded-lg" style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $log->log_output }}</pre></details>@endif
        </div>
    @empty
        <p class="text-sm text-neutral-600">This archive has not been used for a restore.</p>
    @endforelse
</section>
<section class="card p-5" aria-labelledby="delete-backup-title">
    <h2 id="delete-backup-title" class="font-semibold">Delete this backup</h2>
    <p class="text-sm text-neutral-600 mt-1 mb-4">Permanently removes the local archive and its backup record. Keep another verified backup before deleting this one.</p>
    <form method="POST" action="{{ route('admin.backups.destroy', $backup) }}" data-backup-action="Deleting…" data-confirm="Permanently delete this backup archive? This cannot be undone.">
        @csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete backup</button>
    </form>
</section>
@endsection
