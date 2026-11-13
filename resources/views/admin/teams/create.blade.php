@extends('layouts.admin-dashboard')

@section('title', 'New Team')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">New Team</h1>

    <form method="POST" action="{{ route('admin.teams.store') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @include('admin.teams._form')
    </form>
@endsection
