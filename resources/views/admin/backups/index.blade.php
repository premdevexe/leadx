@extends('layouts.admin-dashboard')
@section('title', 'Backups')
@section('content')
<x-page-header title="Backups" subtitle="Create encrypted archives, check their integrity, and manage recovery." />
@include('admin.backups._nav')

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card title="Archives" :value="$totalBackups" subtitle="Saved backup records" />
    <x-stat-card title="Archive storage" :value="$totalStorageFormatted" :subtitle="$remoteStatus" />
    <x-stat-card title="Last verified" :value="$lastSuccessful?->verified_at?->format('M d, Y') ?? 'None yet'" :subtitle="$lastSuccessful?->verified_at?->format('H:i') ?? 'Create and verify your first backup'" />
    <x-stat-card title="Daily schedule" :value="$nextScheduled" subtitle="Requires the server scheduler to be running" />
</div>

<section class="card p-5 mb-6" aria-labelledby="create-backup-title">
    <h2 id="create-backup-title" class="text-lg font-semibold">Create a backup</h2>
    <p class="text-sm text-neutral-600 mt-1 mb-4">Full backups include the database and uploaded files. Keep this page open while the archive is prepared.</p>
    <form method="POST" action="{{ route('admin.backups.store') }}" class="flex flex-wrap items-end gap-3" data-backup-action="Creating backup…">
        @csrf
        <div class="flex-1" style="min-width: min(100%, 15rem)">
            <label for="type" class="form-label">Backup contents</label>
            <select id="type" name="type" class="form-select" aria-describedby="type-error">
                @foreach (['full' => 'Database and files', 'database' => 'Database only', 'storage' => 'Uploaded files only'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', 'full') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type') <p id="type-error" class="form-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary">Create backup</button>
    </form>
</section>

@if ($recentFailures->isNotEmpty())
    <section class="alert alert-warning mb-6" aria-labelledby="backup-attention-title">
        <div>
            <h2 id="backup-attention-title" class="font-semibold">Backups that need attention</h2>
            <ul class="mt-2 space-y-2">
                @foreach ($recentFailures as $failed)
                    <li><a class="underline" href="{{ route('admin.backups.show', $failed) }}" style="overflow-wrap: anywhere">{{ $failed->backup_id }}</a> — Review failed components.</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

<section class="card" aria-labelledby="backup-history-title">
    <div class="p-5 border-b border-neutral-200">
        <h2 id="backup-history-title" class="text-lg font-semibold mb-4">Backup history</h2>
        <form method="GET" action="{{ route('admin.backups.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div><label for="search" class="form-label">Search archive ID</label><input id="search" name="search" class="form-input" value="{{ request('search') }}" maxlength="255" placeholder="leadx-backup-…"></div>
            <div>
                <label for="filter-type" class="form-label">Contents</label>
                <select id="filter-type" name="type" class="form-select">
                    <option value="">All contents</option>
                    @foreach (['full' => 'Full', 'database' => 'Database', 'storage' => 'Files'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="form-label">Integrity</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach (['verified', 'unverified', 'failed'] as $value)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-secondary" href="{{ route('admin.backups.index') }}">Reset</a></div>
        </form>
    </div>
    @if ($backups->isEmpty())
        <div class="p-5"><x-empty-state title="No backups found" description="Create your first archive above, or adjust the filters to find an existing backup." /></div>
    @else
        <div class="table-wrapper" tabindex="0" role="region" aria-label="Backup history table">
            <table class="data-table">
                <thead><tr><th scope="col">Archive</th><th scope="col">Contents</th><th scope="col">Size</th><th scope="col">Integrity</th><th scope="col">Created by</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td><a class="font-medium underline" href="{{ route('admin.backups.show', $backup) }}">{{ $backup->backup_id }}</a><p class="text-xs text-neutral-600 mt-1">{{ $backup->created_at->format('M d, Y H:i') }}</p></td>
                            <td>{{ ucfirst($backup->backup_type) }}</td><td class="whitespace-nowrap">{{ $backup->formatted_size }}</td>
                            <td><x-status-badge :status="$backup->integrity_status" /></td><td>{{ $backup->creator?->name ?? 'Scheduled task' }}</td>
                            <td><a class="btn btn-secondary btn-sm" href="{{ route('admin.backups.show', $backup) }}" aria-label="View backup {{ $backup->backup_id }}">View details</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $backups->links() }}</div>
    @endif
</section>
@endsection
