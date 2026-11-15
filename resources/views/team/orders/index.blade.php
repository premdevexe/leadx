@extends('layouts.team')

@section('title', 'Orders')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Orders</h1>
            <p class="mt-1 text-sm text-neutral-500">All orders for your team.</p>
        </div>
        <a href="{{ route('team.orders.create') }}" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
            Create Order
        </a>
    </div>

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Order</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Customer</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Closing</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Payment</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($orders as $order)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('team.orders.show', $order) }}" class="text-neutral-900 hover:underline">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $order->customer_name }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $order->closing_value, 2) }}</td>
                        <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($order->order_status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-neutral-500">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
