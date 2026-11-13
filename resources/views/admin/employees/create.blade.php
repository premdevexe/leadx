@extends('layouts.admin-dashboard')

@section('title', 'New Employee')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">New Employee</h1>

    <form method="POST" action="{{ route('admin.employees.store') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @include('admin.employees._form')
    </form>
@endsection
