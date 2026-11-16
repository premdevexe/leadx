@extends('layouts.employee')

@section('title', 'My Profile')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900">My Profile</h1>
            <p class="text-sm text-neutral-500">Manage your personal credentials, contact info, and security settings.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ ($employee->status ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' }}">
                {{ ucfirst($employee->status ?? 'active') }} Account
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-800">
            <p class="font-semibold mb-1">Please correct the following errors:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Left Column: System-Assigned Info (Read-Only) --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="card p-6">
                <div class="flex flex-col items-center text-center pb-5 border-b border-neutral-100">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-neutral-900 text-2xl font-bold text-white shadow-sm">
                        {{ strtoupper(substr($employee->name, 0, 1)) }}
                    </div>
                    <h2 class="mt-3 text-base font-semibold text-neutral-900">{{ $employee->name }}</h2>
                    <p class="text-xs text-neutral-500">{{ $employee->email }}</p>
                    <span class="mt-2 inline-flex items-center rounded-md bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 font-mono">
                        {{ $employee->username }}
                    </span>
                </div>

                <div class="mt-5 space-y-3 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-neutral-50">
                        <span class="text-neutral-500">Employee ID</span>
                        <span class="font-mono font-medium text-neutral-800">#{{ $employee->id }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-neutral-50">
                        <span class="text-neutral-500">System Role</span>
                        <span class="font-medium text-neutral-800">{{ ucfirst($employee->role ?? 'Employee') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-neutral-50">
                        <span class="text-neutral-500">Assigned Team</span>
                        <span class="font-medium text-neutral-800">{{ $employee->team?->team_name ?? 'None' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-neutral-50">
                        <span class="text-neutral-500">Team Code</span>
                        <span class="font-mono font-medium text-neutral-800">{{ $employee->team?->team_code ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-neutral-50">
                        <span class="text-neutral-500">Permission Preset</span>
                        <span class="font-medium text-neutral-800">{{ $employee->preset?->name ?? 'Standard Preset' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-neutral-500">Member Since</span>
                        <span class="text-neutral-800">{{ $employee->created_at?->format('M d, Y') ?? '—' }}</span>
                    </div>
                </div>

                <div class="mt-5 rounded-lg bg-neutral-50 p-3 text-[11px] text-neutral-500 text-center">
                    Team assignment and role permissions are managed exclusively by Super Admin.
                </div>
            </div>
        </div>

        {{-- Right Column: Editable Details & Security --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Edit Contact Info --}}
            <div class="card p-6">
                <h2 class="text-base font-semibold text-neutral-900 border-b border-neutral-100 pb-3">
                    Contact Information
                </h2>
                <form action="{{ route('employee.profile.update') }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $employee->name) }}"
                               class="form-input">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" required value="{{ old('email', $employee->email) }}"
                                   class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $employee->phone) }}" placeholder="+1 234 567 890"
                                   class="form-input">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="card p-6">
                <h2 class="text-base font-semibold text-neutral-900 border-b border-neutral-100 pb-3">
                    Security & Password
                </h2>
                <form action="{{ route('employee.profile.password') }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" name="current_password" id="current_password" required
                               class="form-input">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" name="password" id="password" required minlength="8"
                                   class="form-input">
                            <span class="mt-1 text-[11px] text-neutral-400">Must be at least 8 characters with letters & numbers.</span>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8"
                                   class="form-input">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
