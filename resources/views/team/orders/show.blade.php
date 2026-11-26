@extends('layouts.team')

@section('title', 'Order '.$order->order_number)

@section('content')
    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-xs text-red-800">{{ session('error') }}</div>
    @endif

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
            @if ($order->items->count() > 1)
                <p class="text-xs text-neutral-500">+ {{ $order->items->count() - 1 }} more item(s) · {{ $order->items->sum('quantity') }} total units</p>
            @else
                <p class="text-xs text-neutral-500">Duration: {{ $order->product_duration_snapshot ?? $order->duration }}</p>
            @endif
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

    @if ($order->items->isNotEmpty())
        <div class="mt-6 card p-5">
            <h2 class="text-sm font-semibold text-neutral-900">Order Items</h2>
            @include('partials.order-items-table', ['order' => $order, 'showSubscriptionCol' => true])
        </div>
    @endif

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
        @if ($order->subscriptions->count() > 1)
            <div class="mt-2 space-y-2">
                @foreach ($order->subscriptions as $sub)
                    <div class="rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                        <p class="font-medium">{{ $sub->plan_snapshot ?? 'Subscription #' . $sub->id }} &middot; {{ ucfirst($sub->status) }}</p>
                        @if ($sub->started_at)<p class="text-xs text-neutral-500">Started: {{ $sub->started_at }}</p>@endif
                        @if ($sub->expires_at)<p class="text-xs text-neutral-500">Expires: {{ $sub->expires_at }}</p>@endif
                    </div>
                @endforeach
            </div>
        @elseif($order->subscription)
            <div class="mt-2 rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                <p>Status: {{ ucfirst($order->subscription->status) }}</p>
                @if($order->subscription->started_at)<p>Started: {{ $order->subscription->started_at }}</p>@endif
                @if($order->subscription->expires_at)<p>Expires: {{ $order->subscription->expires_at }}</p>@endif
            </div>
        @else
            <p class="mt-2 text-sm text-neutral-500">No subscription yet.</p>
        @endif
    </div>

    {{-- Public customer share link --}}
    <div class="mt-6">
        <h2 class="text-sm font-semibold text-neutral-700">Public Link</h2>
        <p class="mt-1 text-xs text-neutral-500">A secure, no-login link you can share with the customer to view their order status. Only one live link exists at a time.</p>

        @if ($publicShare ?? null)
            <div class="mt-3 rounded-lg border border-neutral-200 bg-white p-3">
                <div class="flex items-center justify-between gap-2">
                    <code class="break-all font-mono text-xs text-neutral-800" id="public-link-url">{{ $publicShare->url() }}</code>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('public-link-url').textContent)"
                            class="rounded border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50 shrink-0">
                        Copy
                    </button>
                </div>
                <p class="mt-2 text-[11px] text-neutral-400">
                    Status-only (no API key included)
                    @if ($publicShare->expires_at)
                        · expires {{ $publicShare->expires_at->toDateTimeString() }}
                    @else
                        · no expiry
                    @endif
                </p>
                <form method="POST" action="{{ route('team.orders.public-share.revoke', $order) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 underline"
                            onclick="return confirm('Revoke this public link? The customer will lose access.')">
                        Revoke link
                    </button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('team.orders.public-share.store', $order) }}" class="mt-3 space-y-2">
                @csrf
                <div>
                    <label class="block text-[11px] text-neutral-500">Link expiry (optional)</label>
                    <input type="datetime-local" name="expires_at" class="form-input mt-0.5 w-full text-xs">
                </div>
                <button type="submit" class="rounded bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-neutral-800">
                    Generate public link
                </button>
            </form>
        @endif

        @if (session('status'))
            <p class="mt-3 text-xs text-emerald-700">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="mt-3 text-xs text-red-700">{{ session('error') }}</p>
        @endif
    </div>

    <div class="mt-6">
        <a href="{{ route('team.orders') }}" class="text-sm text-neutral-600">&larr; Back to orders</a>
    </div>
@endsection
