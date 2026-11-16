@extends('layouts.admin')

@section('title', 'Edit Preset: ' . $preset->name)

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900">Edit Preset: {{ $preset->name }}</h1>
            <p class="text-sm text-neutral-500">Update baseline permissions for employees assigned to this preset.</p>
        </div>
        <a href="{{ route('admin.presets') }}" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
            &larr; Back to Presets
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800">
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.presets.update', $preset) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Preset Details Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs space-y-4">
            <h2 class="text-base font-semibold text-neutral-900">Preset Details</h2>

            <div>
                <label for="name" class="block text-xs font-semibold text-neutral-700 uppercase tracking-wider">Preset Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $preset->name) }}" required
                       class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold text-neutral-700 uppercase tracking-wider">Description</label>
                <textarea name="description" id="description" rows="2"
                          class="mt-1 block w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">{{ old('description', $preset->description) }}</textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_default" id="is_default" value="1" {{ old('is_default', $preset->is_default) ? 'checked' : '' }}
                       class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900">
                <label for="is_default" class="text-xs font-medium text-neutral-700">Set as default preset for new employees</label>
            </div>
        </div>

        {{-- Permissions by Module --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <div>
                    <h2 class="text-base font-semibold text-neutral-900">Module Permissions & Visibilities</h2>
                    <p class="text-xs text-neutral-500">Check the permissions to grant for this preset.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = true)"
                            class="text-xs text-neutral-700 underline hover:text-neutral-900">Select All</button>
                    <span class="text-neutral-300">|</span>
                    <button type="button" onclick="document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false)"
                            class="text-xs text-neutral-700 underline hover:text-neutral-900">Clear All</button>
                </div>
            </div>

            @foreach ($permissionsByModule as $module => $modulePermissions)
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500 border-b border-neutral-100 pb-1">
                        {{ ucfirst(str_replace('_', ' ', $module)) }} Module
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($modulePermissions as $perm)
                            @php
                                $isChecked = in_array($perm->id, old('permissions', $assignedPermissionIds), false);
                            @endphp
                            <label class="flex items-start gap-2.5 rounded-lg border border-neutral-200 p-3 hover:bg-neutral-50 cursor-pointer transition-colors">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" {{ $isChecked ? 'checked' : '' }} class="perm-cb mt-0.5 h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900">
                                <div class="text-xs">
                                    <div class="font-medium text-neutral-900">{{ $perm->name }}</div>
                                    <div class="text-neutral-500 text-[11px] leading-tight">{{ $perm->description }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="{{ route('admin.presets') }}" class="rounded-lg border border-neutral-200 px-4 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-neutral-900 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                Update Preset
            </button>
        </div>
    </form>
</div>
@endsection
