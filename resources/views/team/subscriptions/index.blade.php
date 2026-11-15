@extends('layouts.team')

@section('title', 'Subscriptions')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Subscriptions</h1>
    <p class="mt-1 text-sm text-neutral-500">Subscriptions for your team.</p>

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Order</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Status</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Started</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Expires</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($subscriptions as $subscription)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">{{ $subscription->order?->order_number ?? '—' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($subscription->status) }}</td>
                        <td class="px-4 py-3">{{ $subscription->started_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $subscription->expires_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-500">No subscriptions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
@endsection
