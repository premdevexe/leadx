@extends('layouts.team')

@section('title', 'Follow-ups')

@section('content')
<x-page-header title="Team Follow-ups" subtitle="Manage customer conversations and the team's next steps.">
    <x-slot:actions><a href="{{ route('team.follow-ups.create') }}" class="btn btn-primary">Schedule Follow-up</a></x-slot:actions>
</x-page-header>
@include('employee.followups._feedback')
@include('employee.followups._filters')
<div class="mt-5 table-wrap overflow-x-auto card">
    <table class="data-table">
        <thead><tr><th scope="col">Title</th><th scope="col">Customer</th><th scope="col">Assigned employee</th><th scope="col">Scheduled</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
        <tbody>
            @forelse ($followUps as $followUp)
                <tr>
                    <td class="font-medium">{{ $followUp->title }}</td>
                    <td>{{ $followUp->customer?->name ?? 'Unavailable' }}</td>
                    <td>{{ $followUp->assignedEmployee?->name ?? 'Unassigned' }}</td>
                    <td>{{ $followUp->scheduled_at?->format('M j, Y · g:i A') ?? 'Not scheduled' }}</td>
                    <td><x-status-badge :status="$followUp->status" /></td>
                    <td><a class="btn btn-secondary btn-sm" href="{{ route('team.follow-ups.show', $followUp) }}" aria-label="View follow-up: {{ $followUp->title }}">View Details</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No follow-ups match this view. Schedule a follow-up to plan the next customer conversation.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $followUps->links() }}</div>
@endsection
