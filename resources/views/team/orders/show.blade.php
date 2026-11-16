@extends('layouts.team')

@section('title', 'Order '.$order->order_number)

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Order {{ $order->order_number }}</h1>
    <p class="mt-1 text-sm text-neutral-500">Created {{ $order->created_at?->diffForHumans() }}</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Customer</p>
            <p class="mt-1 text-sm font-medium">{{ $order->customer_name }}</p>
            <p class="text-xs text-neutral-500">{{ $order->customer_phone }}</p>
            @if($order->customer_email)<p class="text-xs text-neutral-500">{{ $order->customer_email }}</p>@endif
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Product</p>
            <p class="mt-1 text-sm font-medium">{{ $order->product_name_snapshot ?? $order->product_name }}</p>
            <p class="text-xs text-neutral-500">Duration: {{ $order->product_duration_snapshot ?? $order->duration }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Financials</p>
            <p class="mt-1 text-sm">Value: {{ number_format((float) $order->product_value, 2) }}</p>
            <p class="text-sm">Discount: {{ number_format((float) $order->discount_amount, 2) }}</p>
            <p class="text-sm">Closing: {{ number_format((float) $order->closing_value, 2) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Status</p>
            <p class="mt-1 text-sm">Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</p>
            <p class="text-sm">Order: {{ ucfirst($order->order_status) }}</p>
        </div>
    </div>

    <div class="mt-6">
        <h2 class="text-sm font-semibold text-neutral-700">Payments</h2>
        @forelse($order->payments as $payment)
            <div class="mt-2 rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                <p>{{ number_format((float) $payment->amount, 2) }} &middot; {{ ucfirst($payment->payment_status) }}</p>
            </div>
        @empty
            <p class="mt-2 text-sm text-neutral-500">No payments recorded.</p>
        @endforelse
    </div>

    <div class="mt-6">
        <h2 class="text-sm font-semibold text-neutral-700">Subscription</h2>
        @if($order->subscription)
            <div class="mt-2 rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                <p>Status: {{ ucfirst($order->subscription->status) }}</p>
                @if($order->subscription->started_at)<p>Started: {{ $order->subscription->started_at }}</p>@endif
                @if($order->subscription->expires_at)<p>Expires: {{ $order->subscription->expires_at }}</p>@endif
            </div>
        @else
            <p class="mt-2 text-sm text-neutral-500">No subscription yet.</p>
        @endif
    </div>

    <div class="mt-6">
        <a href="{{ route('team.orders') }}" class="text-sm text-neutral-600">&larr; Back to orders</a>
    </div>
@endsection
