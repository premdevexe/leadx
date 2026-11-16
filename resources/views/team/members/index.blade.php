@extends('layouts.team')

@section('title', 'Team Members')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Team Members</h1>
    <p class="mt-1 text-sm text-neutral-500">Current members of {{ $team->team_name }}.</p>

    <div class="mt-5 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>{{ $member->user?->name ?? '—' }}</td>
                        <td>{{ $member->user?->username ?? '—' }}</td>
                        <td>{{ $member->user?->email ?? '—' }}</td>
                        <td>{{ ucfirst($member->user?->role ?? '—') }}</td>
                        <td>{{ ucfirst($member->user?->status ?? '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No members found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links() }}</div>
@endsection
