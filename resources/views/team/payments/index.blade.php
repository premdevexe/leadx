@extends('layouts.team')

@section('title', 'Payments')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Payments</h1>
    <p class="mt-1 text-sm text-neutral-500">Payments for your team's orders.</p>

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Order</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Amount</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Status</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Method</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($payments as $payment)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">{{ $payment->order?->order_number ?? '—' }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($payment->payment_status) }}</td>
                        <td class="px-4 py-3">{{ $payment->payment_method ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-500">No payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
@endsection
