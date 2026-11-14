@extends('layouts.admin-dashboard')

@section('title', 'Edit Admin')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Edit Admin</h1>
    <p class="mt-1 text-sm text-neutral-500">{{ $admin->username }} ({{ $admin->email }})</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.admins.update', $admin) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @method('PUT')
        @include('admin.admins._form')
    </form>
@endsection
