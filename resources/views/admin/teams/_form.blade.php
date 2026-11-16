@php
    $isEdit = $team->exists;
    $codeLocked = $isEdit && $team->hasOrders();
    $selectedIds = $isEdit ? $team->members->pluck('user_id')->all() : old('user_ids', []);
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="form-label">Team name</label>
        <input type="text" name="team_name" value="{{ old('team_name', $team->team_name) }}" required
               class="form-input mt-1 w-full">
        @error('team_name') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Team code</label>
        <input type="text" name="team_code" value="{{ old('team_code', $team->team_code) }}"
               required @disabled($codeLocked) uppercase placeholder="e.g. GEM or TEAM01"
               class="form-input mt-1 w-full {{ $codeLocked ? 'bg-neutral-100 text-neutral-500' : '' }}">
        @if ($codeLocked)
            <p class="form-hint">Locked — this team has generated orders, so the code can no longer change.</p>
        @else
            <p class="form-hint">2-6 uppercase letters (e.g. GEM), or TEAM followed by 1-4 digits (e.g. TEAM01).</p>
        @endif
        @error('team_code') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Login username</label>
        <input type="text" name="username" value="{{ old('username', $team->username) }}" required
               class="form-input mt-1 w-full">
        @error('username') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-select mt-1 w-full">
            <option value="active" @selected(old('status', $team->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $team->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
        @error('status') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">
            Password {{ $isEdit ? '(leave blank to keep current)' : '' }}
        </label>
        <input type="password" name="password" {{ $isEdit ? '' : 'required' }}
               class="form-input mt-1 w-full">
        @error('password') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">
            {{ $isEdit ? 'Password confirmation' : 'Confirm password' }}
        </label>
        <input type="password" name="password_confirmation" {{ $isEdit ? '' : 'required' }}
               class="form-input mt-1 w-full">
    </div>
</div>

<div class="mt-6">
    <label class="form-label">Assign employees</label>
    @if (empty($employees))
        <p class="mt-2 text-sm text-neutral-400">No employees available. <a href="{{ route('admin.employees') }}" class="text-blue-600 underline hover:text-blue-800">Create one</a>.</p>
    @else
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            {{-- Sentinel so an empty selection still submits user_ids[] (removes all members). --}}
            <input type="hidden" name="user_ids[]" value="">
            @foreach ($employees as $employee)
                <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm overflow-hidden {{ in_array($employee->id, $selectedIds, true) ? 'border-neutral-400 bg-neutral-50' : '' }}">
                    <input type="checkbox" name="user_ids[]" value="{{ $employee->id }}"
                           @checked(in_array($employee->id, $selectedIds, true))
                           class="rounded border-neutral-300 shrink-0">
                    <span class="font-medium text-neutral-800 shrink-0">{{ $employee->username }}</span>
                    <span class="text-neutral-400 truncate">{{ $employee->email }}</span>
                </label>
            @endforeach
        </div>
        @error('user_ids') <p class="form-error">{{ $message }}</p> @enderror
    @endif
</div>

<div class="mt-6 flex flex-wrap items-center gap-3">
    <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
        {{ $isEdit ? 'Save changes' : 'Create team' }}
    </button>
    <a href="{{ route('admin.teams') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
</div>
