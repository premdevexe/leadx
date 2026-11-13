@php
    $isEdit = $team->exists;
    $codeLocked = $isEdit && $team->hasOrders();
    $selectedIds = $isEdit ? $team->members->pluck('user_id')->all() : old('user_ids', []);
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-xs font-medium text-neutral-500">Team name</label>
        <input type="text" name="team_name" value="{{ old('team_name', $team->team_name) }}" required
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('team_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Team code</label>
        <input type="text" name="team_code" value="{{ old('team_code', $team->team_code) }}"
               required @disabled($codeLocked) uppercase placeholder="e.g. GEM or TEAM01"
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-sm focus:border-neutral-500 focus:outline-none {{ $codeLocked ? 'bg-neutral-100 text-neutral-500' : '' }}">
        @if ($codeLocked)
            <p class="mt-1 text-xs text-neutral-400">Locked — this team has generated orders, so the code can no longer change.</p>
        @else
            <p class="mt-1 text-xs text-neutral-400">2-6 uppercase letters (e.g. GEM), or TEAM followed by 1-4 digits (e.g. TEAM01).</p>
        @endif
        @error('team_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Login username</label>
        <input type="text" name="username" value="{{ old('username', $team->username) }}" required
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Status</label>
        <select name="status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            <option value="active" @selected(old('status', $team->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $team->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">
            Password {{ $isEdit ? '(leave blank to keep current)' : '' }}
        </label>
        <input type="password" name="password" {{ $isEdit ? '' : 'required' }}
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">
            {{ $isEdit ? 'Password confirmation' : 'Confirm password' }}
        </label>
        <input type="password" name="password_confirmation" {{ $isEdit ? '' : 'required' }}
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
    </div>
</div>

<div class="mt-6">
    <label class="block text-xs font-medium text-neutral-500">Assign employees</label>
    @if (empty($employees))
        <p class="mt-2 text-sm text-neutral-400">No employees available. <a href="{{ route('admin.employees') }}" class="text-neutral-600 underline">Create one</a>.</p>
    @else
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            {{-- Sentinel so an empty selection still submits user_ids[] (removes all members). --}}
            <input type="hidden" name="user_ids[]" value="">
            @foreach ($employees as $employee)
                <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm {{ in_array($employee->id, $selectedIds, true) ? 'border-neutral-400 bg-neutral-50' : '' }}">
                    <input type="checkbox" name="user_ids[]" value="{{ $employee->id }}"
                           @checked(in_array($employee->id, $selectedIds, true))
                           class="rounded border-neutral-300">
                    <span class="font-medium text-neutral-800">{{ $employee->username }}</span>
                    <span class="text-neutral-400">{{ $employee->email }}</span>
                </label>
            @endforeach
        </div>
        @error('user_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @endif
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
        {{ $isEdit ? 'Save changes' : 'Create team' }}
    </button>
    <a href="{{ route('admin.teams') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
</div>
