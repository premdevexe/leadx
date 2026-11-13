@extends('layouts.admin-dashboard')

@section('title', 'Edit Team')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Edit Team</h1>
    <p class="mt-1 text-sm text-neutral-500">{{ $team->team_name }} ({{ $team->team_code }})</p>

    <form method="POST" action="{{ route('admin.teams.update', $team) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @method('PUT')
        @include('admin.teams._form')
    </form>
@endsection
