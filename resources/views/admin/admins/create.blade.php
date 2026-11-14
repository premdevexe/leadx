@extends('layouts.admin-dashboard')

@section('title', 'New Admin')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">New Admin</h1>

    <form method="POST" action="{{ route('admin.admins.store') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @include('admin.admins._form')
    </form>
@endsection
