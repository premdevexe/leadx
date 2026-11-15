@extends('layouts.employee')

@section('title', $viewMode === 'team_data' ? 'Team Follow-ups' : 'Follow-ups')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">
                {{ $viewMode === 'team_data' ? 'Team Follow-ups (View-Only)' : 'Follow-ups' }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $viewMode === 'team_data' ? 'Follow-ups across your team within the granted window.' : 'Your scheduled follow-ups.' }}
            </p>
        </div>

        <div class="inline-flex rounded-lg border border-neutral-200 bg-neutral-100 p-1 text-xs font-semibold shadow-xs">
            <a href="{{ route('employee.follow-ups', ['view' => 'my_data']) }}"
               class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode !== 'team_data' ? 'bg-white text-neutral-900 shadow-xs' : 'text-neutral-600 hover:text-neutral-900' }}">
                My Data
            </a>
            @if ($hasTeamAccess)
                <a href="{{ route('employee.follow-ups', ['view' => 'team_data']) }}"
                   class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode === 'team_data' ? 'bg-white text-neutral-900 shadow-xs' : 'text-neutral-600 hover:text-neutral-900' }}">
                    Team Data
                </a>
            @else
                <button type="button" disabled class="cursor-not-allowed rounded-md px-3 py-1.5 text-neutral-400">Team Data (Locked)</button>
            @endif
        </div>
    </div>

    @if (session('warning'))
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if ($viewMode === 'team_data' && $activeGrant)
        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/70 p-3 text-xs text-indigo-900">
            <strong>Team Data Window:</strong> {{ $activeGrant->start_date->format('M d, Y') }} &rarr; {{ $activeGrant->end_date->format('M d, Y') }} (View-Only mode).
        </div>
    @endif

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-xs">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 font-semibold">
                <tr>
                    <th class="px-4 py-2.5">Title</th>
                    <th class="px-4 py-2.5">Scheduled</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">Customer</th>
                    @if ($viewMode === 'team_data')
                        <th class="px-4 py-2.5">Employee</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 text-xs">
                @forelse ($followUps as $followUp)
                    <tr class="hover:bg-neutral-50/50">
                        <td class="px-4 py-3 font-medium">{{ $followUp->title }}</td>
                        <td class="px-4 py-3">{{ $followUp->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold
                                {{ $followUp->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($followUp->status === 'scheduled' ? 'bg-amber-100 text-amber-800' : 'bg-neutral-100 text-neutral-700') }}">
                                {{ ucfirst($followUp->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $followUp->customer?->name ?? '—' }}</td>
                        @if ($viewMode === 'team_data')
                            <td class="px-4 py-3 text-neutral-600">{{ $followUp->assignedEmployee?->name ?? ($followUp->team?->team_name ?? '—') }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $viewMode === 'team_data' ? 5 : 4 }}" class="px-4 py-8 text-center text-neutral-400">No follow-ups found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $followUps->links() }}</div>
@endsection
