@extends('layouts.employee')

@section('title', 'Follow-ups')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Follow-ups</h1>
    <p class="mt-1 text-sm text-neutral-500">Your scheduled follow-ups.</p>

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Title</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Scheduled</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Status</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Customer</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($followUps as $followUp)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">{{ $followUp->title }}</td>
                        <td class="px-4 py-3">{{ $followUp->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($followUp->status) }}</td>
                        <td class="px-4 py-3">{{ $followUp->customer?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-500">No follow-ups found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $followUps->links() }}</div>
@endsection
