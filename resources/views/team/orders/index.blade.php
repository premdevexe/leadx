@extends('layouts.team')

@section('title', 'Orders')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Orders</h1>
            <p class="mt-1 text-sm text-neutral-500">All orders for your team.</p>
        </div>
        <a href="{{ route('team.orders.create') }}" class="btn btn-primary btn-sm">
            Create Order
        </a>
    </div>

    <div class="mt-5 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Closing</th>
                    <th>Payment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('team.orders.show', $order) }}" class="text-neutral-900">{{ $order->order_number }}</a>
                        </td>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ number_format((float) $order->closing_value, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</td>
                        <td>{{ ucfirst($order->order_status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
