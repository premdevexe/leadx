@php
    $isEdit = $admin->exists;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="form-label">Full name</label>
        <input type="text" name="name" value="{{ old('name', $admin->name) }}" required
               class="form-input mt-1 w-full">
        @error('name') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Username</label>
        <input type="text" name="username" value="{{ old('username', $admin->username) }}" required
               class="form-input mt-1 w-full">
        @error('username') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $admin->email) }}" required
               class="form-input mt-1 w-full">
        @error('email') <p class="form-error">{{ $message }}</p> @enderror
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

<div class="mt-6 flex flex-wrap items-center gap-3">
    <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
        {{ $isEdit ? 'Save changes' : 'Create admin' }}
    </button>
    <a href="{{ route('admin.admins') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
</div>
