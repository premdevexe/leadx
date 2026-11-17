@extends('layouts.public-order')

@section('title', 'Order '.$order->order_number)

@php
    $subscription = $order->subscription;
    $currency = \App\Models\Setting::currencySymbol();
    $durationCode = $order->product_duration_snapshot ?? $order->duration;
    $durationLabel = \App\Models\SalesOrder::DURATIONS[$durationCode] ?? ($durationLabel ?? ($durationCode ?: '1 year'));
    $orderBadge = match ($order->order_status) {
        'active', 'confirmed' => 'badge-success',
        'pending'             => 'badge-warning',
        'suspended'          => 'badge-warning',
        'cancelled', 'expired'=> 'badge-danger',
        default               => 'badge-neutral',
    };
    $paymentBadge = match ($order->payment_status) {
        'paid'              => 'badge-success',
        'partially_paid'    => 'badge-info',
        'pending'           => 'badge-warning',
        'failed'            => 'badge-danger',
        'refunded','partially_refunded' => 'badge-neutral',
        default             => 'badge-neutral',
    };
@endphp

@section('content')
    <header class="public-order-head">
        <div>
            <h1 class="public-order-title">Order <span class="font-mono">{{ $order->order_number ?? 'Unnumbered' }}</span></h1>
            <p class="public-order-sub">Shared view for <strong>{{ $order->customer_name }}</strong></p>
        </div>
        <span class="badge {{ $orderBadge }}"><span class="badge-dot"></span> {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</span>
    </header>

    {{-- Status-only facts. The customer's API key never renders here by
         default — only the masked indicator, revealed on explicit request. --}}
    <div class="public-order-grid">
        <div class="card p-4">
            <p class="public-label">Product</p>
            <p class="public-value">{{ $order->product_name_snapshot ?? $order->product_name ?? 'Manual / Custom' }}</p>
            <p class="public-muted">Duration: {{ $durationLabel }}</p>
        </div>

        <div class="card p-4">
            <p class="public-label">Subscription</p>
            @if ($subscription && $subscription->status)
                <p class="public-value">{{ ucfirst($subscription->status) }}</p>
                @if ($subscription->started_at)
                    <p class="public-muted">Active since {{ $subscription->started_at->toDateString() }}</p>
                @endif
                @if ($subscription->expires_at)
                    <p class="public-muted">Expires {{ $subscription->expires_at->toDateString() }}</p>
                @endif
            @else
                <p class="public-value public-muted">Not yet active</p>
            @endif
        </div>

        <div class="card p-4">
            <p class="public-label">Payment</p>
            <p class="public-value">
                {{ $currency }}{{ number_format((float) $order->closing_value, 2) }}
                <span class="public-muted">due</span>
            </p>
            <p class="public-muted">Collected: {{ $currency }}{{ number_format((float) $order->amount_received, 2) }}</p>
            @if ($order->payment_method)
                <p class="public-muted">Via: {{ \App\Models\Setting::paymentMethodLabel($order->payment_method) }}</p>
            @endif
            <span class="badge {{ $paymentBadge }}" style="margin-top:0.5rem">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</span>
        </div>

        <div class="card p-4">
            <p class="public-label">Created</p>
            <p class="public-value">{{ $order->created_at?->format('M d, Y') }}</p>
            @if ($subscription?->renewal_date)
                <p class="public-muted">Renewal: {{ $subscription->renewal_date->toDateString() }}</p>
            @endif
        </div>
    </div>

    {{-- Customer's delivered API key (only when the share opted in). --}}
    @if ($keyIncluded ?? false)
        <div class="card p-4 public-key-card">
            <div class="public-key-head">
                <p class="public-label">Your API Key</p>
                @if ($maskedKey ?? null)
                    <span class="public-muted">Stored as <code class="font-mono">{{ $maskedKey }}</code></span>
                @endif
            </div>

            @if ($revealedKey ?? null)
                <div class="public-key-reveal">
                    <code class="font-mono public-key-text" id="public-api-key">{{ $revealedKey }}</code>
                    <button type="button" class="btn btn-primary btn-sm"
                            onclick="navigator.clipboard.writeText(document.getElementById('public-api-key').textContent)"
                            title="Copy your API key">Copy key</button>
                </div>
            @else
                <p class="public-muted">
                    Your API key is hidden. Reveal it to copy — it shows for this view only and is not stored.
                </p>
                <form method="POST" action="{{ route('public.orders.api-key.reveal', $share->token) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Reveal my API key</button>
                </form>
                @if (session('publicOrderKeyStatus'))
                    <p class="public-key-note text-emerald-800">{{ session('publicOrderKeyStatus') }}</p>
                @endif
                @if (session('publicOrderKeyError'))
                    <p class="public-key-note text-amber-800">{{ session('publicOrderKeyError') }}</p>
                @endif
            @endif
        </div>
    @endif

    <footer class="public-order-footnote">
        Questions? Contact your team to help you with this order.
    </footer>
@endsection
