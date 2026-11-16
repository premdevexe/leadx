@extends('layouts.team')

@section('title', 'Payments')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Payments</h1>
    <p class="mt-1 text-sm text-neutral-500">Payments for your team's orders.</p>

    <div class="mt-5 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment->order?->order_number ?? '—' }}</td>
                        <td>{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ ucfirst($payment->payment_status) }}</td>
                        <td>{{ $payment->payment_method ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
@endsection
