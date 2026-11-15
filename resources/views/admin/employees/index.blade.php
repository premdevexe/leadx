@extends('layouts.admin-dashboard')

@section('title', 'Employees')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Employees</h1>
        <a href="{{ route('admin.employees.create') }}"
           class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
            New employee
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($employees->isEmpty())
        <div class="mt-5 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            No employees yet. Create your first employee
        </div>
    @else
        <div class="mt-5 w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="w-full min-w-[700px] text-sm whitespace-nowrap">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Team</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $employee->username ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $employee->name }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $employee->email }}</td>
                            <td class="px-4 py-3">
                                @if ($employee->team)
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="text-sm text-neutral-700">{{ $employee->team->team_name }}</span>
                                        <span class="rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-600">{{ $employee->team->team_code }}</span>
                                    </span>
                                @else
                                    <span class="text-xs text-neutral-400">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-1.5 text-xs {{ $employee->status === 'active' ? 'text-emerald-700' : 'text-neutral-500' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $employee->status === 'active' ? 'bg-emerald-500' : 'bg-neutral-300' }}"></span>
                                    {{ $employee->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.employees.edit', $employee) }}"
                                       class="inline-flex items-center rounded-lg border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 shadow-xs transition-colors hover:bg-neutral-50 hover:text-neutral-900">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete employee {{ $employee->name ?? $employee->username }}? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 transition-colors hover:bg-red-100 hover:text-red-900">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
