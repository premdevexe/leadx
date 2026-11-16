@extends('layouts.admin')

@section('title', 'Permission Definitions')

@section('content')
<div class="space-y-8">
    {{-- Top Bar --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900">Permission Definitions</h1>
            <p class="text-sm text-neutral-500">Manage the permission keys that presets and employee overrides can grant or deny.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.presets') }}" class="inline-flex items-center rounded-lg border border-neutral-200 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 transition-colors">
                &larr; Back to Presets
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Create New Permission --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs space-y-4">
        <div>
            <h2 class="text-base font-semibold text-neutral-900">Create New Permission</h2>
            <p class="text-xs text-neutral-500">Names use lowercase dot-separated keys, e.g. <code class="font-mono">reports.export</code>. After creation you can edit it or use it in presets.</p>
        </div>

        <form method="POST" action="{{ route('admin.permissions.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Permission Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="e.g. reports.export"
                           pattern="^[a-z0-9_]+(\.[a-z0-9_]+)*$"
                           title="lowercase letters, numbers, underscores; dot-separated"
                           class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs font-mono text-neutral-900 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Module <span class="text-red-500">*</span></label>
                    <input type="text" name="module" value="{{ old('module', 'general') }}" required
                           placeholder="e.g. reports"
                           pattern="^[a-z0-9_]+$"
                           class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 mb-1">Action <span class="text-red-500">*</span></label>
                    <input type="text" name="action" value="{{ old('action') }}" required
                           placeholder="e.g. export"
                           pattern="^[a-z0-9_]+$"
                           class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-neutral-700 mb-1">Description</label>
                <input type="text" name="description" value="{{ old('description') }}"
                       placeholder="What does this permission allow?"
                       class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs text-neutral-900 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
            </div>

            @if ($presets->isNotEmpty())
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 mb-2">Grant in presets</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($presets as $preset)
                            <label class="inline-flex items-center gap-1.5 text-xs text-neutral-700">
                                <input type="checkbox" name="preset_ids[]" value="{{ $preset->id }}" class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900">
                                {{ $preset->name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-[11px] text-neutral-400">Checked presets immediately grant this permission to their employees.</p>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-neutral-100">
                @if ($errors->any())
                    <span class="text-xs text-red-600">{{ $errors->first() }}</span>
                @endif
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                    Create Permission
                </button>
            </div>
        </form>
    </div>

    {{-- Existing Permissions by Module --}}
    @forelse ($permissions as $module => $modulePermissions)
        <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
            <div class="border-b border-neutral-200 px-6 py-3">
                <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-700">{{ ucfirst(str_replace('_', ' ', $module)) }} Module</h2>
            </div>
            <table class="w-full text-left text-xs text-neutral-700">
                <thead class="border-b border-neutral-100 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
                    <tr>
                        <th class="px-6 py-2.5">Permission</th>
                        <th class="px-6 py-2.5">Action</th>
                        <th class="px-6 py-2.5">Description</th>
                        <th class="px-6 py-2.5 text-right">Granted in Presets</th>
                        <th class="px-6 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modulePermissions as $perm)
                        @php $grantedCount = $perm->presetItems->filter(fn ($i) => $i->granted)->count(); @endphp
                        <tr class="hover:bg-neutral-50">
                            <td class="px-6 py-2.5 font-mono font-medium text-neutral-900">{{ $perm->name }}</td>
                            <td class="px-6 py-2.5">{{ $perm->action }}</td>
                            <td class="px-6 py-2.5 text-neutral-500">{{ $perm->description ?? '—' }}</td>
                            <td class="px-6 py-2.5 text-right tabular-nums">{{ $grantedCount }} / {{ $perm->presetItems->count() }}</td>
                            <td class="px-6 py-2.5 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.permissions.destroy', $perm) }}" class="inline"
                                      onsubmit="return confirm('Delete permission \'{{ addslashes($perm->name) }}\'? Preset grants and employee overrides for it will be removed too.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded border border-red-200 px-2.5 py-1 text-[11px] font-medium text-red-700 hover:bg-red-50">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="rounded-xl border border-neutral-200 bg-white p-10 text-center text-sm text-neutral-400 shadow-xs">
            No permission definitions found. Create one above.
        </div>
    @endforelse
</div>
@endsection
