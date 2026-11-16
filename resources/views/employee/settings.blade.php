@extends('layouts.employee')

@section('title', 'Employee Settings')

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">Account Settings</h1>
        <p class="mt-1 text-sm text-neutral-500">Your employee profile and preferences.</p>
    </div>

    <div class="card p-6 divide-y divide-neutral-100">
        <dl class="flex items-center justify-between py-2 text-xs">
            <dt class="font-medium text-neutral-500">Full Name</dt>
            <dd class="text-neutral-900 font-semibold">{{ $employee->name }}</dd>
        </dl>
        <dl class="flex items-center justify-between py-2 text-xs">
            <dt class="font-medium text-neutral-500">Username</dt>
            <dd class="text-neutral-900">{{ $employee->username }}</dd>
        </dl>
        <dl class="flex items-center justify-between py-2 text-xs">
            <dt class="font-medium text-neutral-500">Email Address</dt>
            <dd class="text-neutral-900">{{ $employee->email }}</dd>
        </dl>
        <dl class="flex items-center justify-between py-2 text-xs">
            <dt class="font-medium text-neutral-500">Assigned Team</dt>
            <dd class="text-neutral-900 font-medium">{{ $employee->team?->team_name ?? 'None' }}</dd>
        </dl>
    </div>
</div>
@endsection
