@extends('layouts.team')

@section('title', 'Team Members')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Team Members</h1>
    <p class="mt-1 text-sm text-neutral-500">Current members of {{ $team->team_name }}.</p>

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Name</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Username</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Email</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Role</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($members as $member)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">{{ $member->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $member->user?->username ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $member->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($member->user?->role ?? '—') }}</td>
                        <td class="px-4 py-3">{{ ucfirst($member->user?->status ?? '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-500">No members found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links() }}</div>
@endsection
