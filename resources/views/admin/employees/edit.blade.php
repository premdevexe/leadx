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
                    class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition-colors hover:bg-red-100 hover:text-red-900">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1.5 h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                Delete employee
            </button>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @method('PUT')
        @include('admin.employees._form')
    </form>
@endsection
