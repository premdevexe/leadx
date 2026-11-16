@extends('layouts.team')

@section('title', 'Subscriptions')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Subscriptions</h1>
    <p class="mt-1 text-sm text-neutral-500">Subscriptions for your team.</p>

    <div class="mt-5 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Expires</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->order?->order_number ?? '—' }}</td>
                        <td>{{ ucfirst($subscription->status) }}</td>
                        <td>{{ $subscription->started_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $subscription->expires_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No subscriptions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
@endsection
