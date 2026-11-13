@extends('layouts.admin-dashboard')

@section('title', 'Teams')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Teams</h1>
        <a href="{{ route('admin.teams.create') }}"
           class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
            New team
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($teams->isEmpty())
        <div class="mt-5 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            No teams yet. Create your first team
        </div>
    @else
        <div class="mt-5 space-y-4">
            @foreach ($teams as $team)
                @php
                    $teamInitial = strtoupper(mb_substr($team->team_name, 0, 1, 'UTF-8'));
                @endphp
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-medium text-neutral-700">
                                {{ $teamInitial }}
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ $team->team_name }}</span>
                                    <span class="rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-600">{{ $team->team_code }}</span>
                                    <span class="flex items-center gap-1.5 text-xs {{ $team->status === 'active' ? 'text-emerald-700' : 'text-neutral-500' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $team->status === 'active' ? 'bg-emerald-500' : 'bg-neutral-300' }}"></span>
                                        {{ $team->status }}
                                    </span>
                                </div>
                                <div class="mt-0.5 text-xs text-neutral-500">
                                    Login username: <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs">{{ $team->username }}</code>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-xs tabular-nums text-neutral-500">
                                {{ count($team->members) }} {{ count($team->members) === 1 ? 'member' : 'members' }}
                            </span>
                            <a href="{{ route('admin.orders', ['team_id' => $team->id]) }}"
                               class="text-xs tabular-nums text-neutral-500 hover:text-neutral-900">
                                {{ $team->orders_count }} orders
                            </a>
                            @if ($team->orders_count > 0)
                                <span class="rounded-md bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-500" title="Team code is locked after the first order">code locked</span>
                            @endif
                            <a href="{{ route('admin.teams.edit', $team) }}" class="text-sm text-neutral-600 transition-colors hover:text-neutral-900">Edit</a>
                            <form method="POST" action="{{ route('admin.teams.destroy', $team) }}"
                                  onsubmit="return confirm('Delete team {{ $team->team_name }}? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 transition-colors hover:text-red-800">Delete</button>
                            </form>
                        </div>
                    </div>

                    @if ($team->members->isEmpty())
                        <p class="mt-3 text-sm text-neutral-400">No active members</p>
                    @else
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($team->members as $member)
                                <li class="rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 text-xs">
                                    <span class="font-medium text-neutral-800">{{ $member->user?->username ?? ('User #'.$member->user_id) }}</span>
                                    <span class="ml-1 text-neutral-400">{{ $member->user?->email }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold text-neutral-500">Unassigned employees</h2>
        @if ($unassigned->isEmpty())
            <p class="text-sm text-neutral-400">All employees are assigned to a team</p>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                            <th class="px-4 py-3">Username</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unassigned as $user)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="px-4 py-3">{{ $user->username ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-neutral-500">{{ $user->email }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
