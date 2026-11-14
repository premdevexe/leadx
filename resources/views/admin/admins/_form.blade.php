@php
    $isEdit = $admin->exists;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-xs font-medium text-neutral-500">Full name</label>
        <input type="text" name="name" value="{{ old('name', $admin->name) }}" required
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Username</label>
        <input type="text" name="username" value="{{ old('username', $admin->username) }}" required
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-neutral-500">Email</label>
        <input type="email" name="email" value="{{ old('email', $admin->email) }}" required
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
        {{ $isEdit ? 'Save changes' : 'Create admin' }}
    </button>
    <a href="{{ route('admin.admins') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
</div>
