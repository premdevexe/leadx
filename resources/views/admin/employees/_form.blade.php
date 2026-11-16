@php
    $isEdit = $employee->exists;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="form-label">Full name</label>
        <input type="text" name="name" value="{{ old('name', $employee->name) }}" required
               class="form-input mt-1 w-full">
        @error('name') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Username</label>
        <input type="text" name="username" value="{{ old('username', $employee->username) }}" required
               class="form-input mt-1 w-full">
        @error('username') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $employee->email) }}" required
               class="form-input mt-1 w-full">
        @error('email') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $employee->phone) }}"
               class="form-input mt-1 w-full">
        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
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

    <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-select mt-1 w-full">
            <option value="active" @selected(old('status', $employee->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $employee->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
        @error('status') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Team</label>
        <select name="team_id" class="form-select mt-1 w-full">
            <option value="">— No team —</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((string) old('team_id', $employee->team_id ?? '') === (string) $team->id)>
                    {{ $team->team_name }} ({{ $team->team_code }})
                </option>
            @endforeach
        </select>
        @error('team_id') <p class="form-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-5">
    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
            {{ $isEdit ? 'Save changes' : 'Create employee' }}
        </button>
        <a href="{{ route('admin.employees') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
    </div>
    @if ($isEdit)
        <button type="button"
                onclick="if(confirm('Are you sure you want to delete employee {{ $employee->name ?? $employee->username }}? This action cannot be undone.')) { document.getElementById('delete-employee-form').submit(); }"
                class="btn btn-danger px-4 py-2 text-sm font-medium hover:bg-red-800">
            Delete employee
        </button>
    @endif
</div>
