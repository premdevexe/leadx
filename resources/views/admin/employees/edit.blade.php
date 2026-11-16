@extends('layouts.admin-dashboard')

@section('title', 'Edit Employee')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Edit Employee</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ $employee->name }} ({{ $employee->username ?? '—' }} · {{ $employee->email }})</p>
        </div>
        <form id="delete-employee-form" method="POST" action="{{ route('admin.employees.destroy', $employee) }}"
              onsubmit="return confirm('Are you sure you want to delete employee {{ $employee->name ?? $employee->username }}? This action cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="btn btn-danger px-3 py-1.5 text-sm font-medium hover:bg-red-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1.5 h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                Delete employee
            </button>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="mt-5 space-y-5">
        @csrf
        @method('PUT')
        @include('admin.employees._form')
    </form>

    {{-- Historical Team Data Access --}}
    @if ($employee->team_id)
        <div class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">Historical Team Data Access</h2>
                        <p class="text-xs text-neutral-500">Grant temporary view-only access to team orders for a historical date range. Required for the employee to switch to Team Data view.</p>
                    </div>
                    @if ($employee->hasActiveTeamAccess())
                        <span class="badge badge-success px-3 py-1 text-xs font-semibold">
                            Active Grant in Effect
                        </span>
                    @else
                        <span class="badge badge-neutral px-3 py-1 text-xs font-semibold">
                            No Active Team Grant
                        </span>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.employees.team-access.grant', $employee) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end bg-neutral-50 p-4 rounded-lg border border-neutral-200">
                    @csrf
                    <div>
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" required value="{{ old('start_date', now()->subDays(30)->toDateString()) }}" class="form-input mt-1 block w-full text-xs">
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" required value="{{ old('end_date', now()->toDateString()) }}" class="form-input mt-1 block w-full text-xs">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary w-full py-1.5 px-4 text-xs font-semibold">
                            Grant Access Window
                        </button>
                    </div>
                </form>

                @if ($teamGrants->isNotEmpty())
                    <div class="overflow-x-auto pt-2">
                        <table class="data-table w-full text-left text-xs text-neutral-600">
                            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500 font-semibold">
                                <tr>
                                    <th class="px-4 py-2">Window Range</th>
                                    <th class="px-4 py-2">Granted By</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teamGrants as $grant)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-neutral-900">
                                            {{ $grant->start_date->format('M d, Y') }} &rarr; {{ $grant->end_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-2 text-neutral-700">{{ $grant->admin?->name ?? 'Admin' }}</td>
                                        <td class="px-4 py-2">
                                            @if ($grant->revoked_at !== null)
                                                <span class="badge badge-danger text-[10px] font-semibold">Revoked</span>
                                            @elseif ($grant->isActive())
                                                <span class="badge badge-success text-[10px] font-semibold">Active</span>
                                            @else
                                                <span class="badge badge-neutral text-[10px] font-semibold">Expired</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            @if ($grant->isActive())
                                                <form method="POST" action="{{ route('admin.employees.team-access.revoke', $grant) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" onclick="return confirm('Revoke this access grant immediately?')" class="btn btn-outline px-2 py-1 text-[11px] font-medium text-red-700 border-red-200 hover:bg-red-50">
                                                        Revoke Now
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
@endsection
