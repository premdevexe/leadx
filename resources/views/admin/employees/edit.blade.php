@extends('layouts.admin-dashboard')

@section('title', 'Edit Employee')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Edit Employee</h1>
    <p class="mt-1 text-sm text-neutral-500">{{ $employee->username ?? '—' }} ({{ $employee->email }})</p>

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @method('PUT')
        @include('admin.employees._form')
    </form>
@endsection
