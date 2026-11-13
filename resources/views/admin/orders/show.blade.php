@extends('layouts.admin-dashboard')

@section('title', 'Order '.$order->order_number)

@php
    $paymentColors = [
        'pending' => 'bg-amber-50 text-amber-700',
        'partially_paid' => 'bg-blue-50 text-blue-700',
        'paid' => 'bg-emerald-50 text-emerald-700',
        'failed' => 'bg-red-50 text-red-700',
        'refunded' => 'bg-neutral-100 text-neutral-500',
    ];
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                <span class="font-mono">{{ $order->order_number ?? 'Unnumbered' }}</span>
            </h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Team {{ $order->team_code_snapshot }} · created {{ $order->created_at->format('M d, Y H:i') }}
            </p>
        </div>
        <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">← All orders</a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        {{-- Left: order details --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-500">Order</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Customer</dt>
                    <dd class="text-right">{{ $order->customer_name }}<br>
                        <span class="text-xs text-neutral-400">{{ $order->customer_phone ?? 'no phone on file' }}</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Team</dt>
                    <dd>{{ $order->team?->team_name ?? '—' }} <span class="font-mono text-xs text-neutral-400">({{ $order->team_code_snapshot }})</span></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Employee</dt>
                    <dd>{{ $order->assignedEmployee?->username ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Product</dt>
                    <dd>{{ $order->product_name ?? '—' }} @if($order->subscription_plan)<span class="text-xs text-neutral-400">{{ $order->subscription_plan }}</span>@endif</dd>
                </div>
            </dl>

            <h2 class="mt-5 text-sm font-semibold text-neutral-500">Money</h2>
            <dl class="mt-2 space-y-1.5 text-sm">
                @foreach ([
                    'Product value' => $order->product_value,
                    'Discount' => $order->discount_amount,
                    'Closing value' => $order->closing_value,
                    'Amount received' => $order->amount_received,
                ] as $label => $amount)
                    <div class="flex justify-between tabular-nums">
                        <dt class="text-neutral-500">{{ $label }}</dt>
                        <dd>{{ number_format((float) $amount, 2) }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-5 flex items-center gap-3">
                <span class="rounded-full px-2.5 py-0.5 text-xs {{ $paymentColors[$order->payment_status] ?? 'bg-neutral-100 text-neutral-600' }}">
                    Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                </span>
                <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs text-neutral-600">
                    {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                </span>
            </div>
        </div>

        {{-- Right: subscription + actions + WhatsApp --}}
        <div class="space-y-5">
            {{-- Subscription + activate --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-500">Subscription</h2>
                @if ($order->subscription)
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Status</dt>
                            <dd>{{ ucfirst($order->subscription->status) }}</dd>
                        </div>
                        @if ($order->subscription->started_at)
                            <div class="flex justify-between"><dt class="text-neutral-500">Started</dt><dd>{{ $order->subscription->started_at->toDateString() }}</dd></div>
                        @endif
                        @if ($order->subscription->expires_at)
                            <div class="flex justify-between"><dt class="text-neutral-500">Expires</dt><dd>{{ $order->subscription->expires_at->toDateString() }}</dd></div>
                        @endif
                    </dl>
                @else
                    <p class="mt-2 text-sm text-neutral-400">No subscription yet.</p>
                @endif

                @if ($order->order_status !== 'active')
                    <form method="POST" action="{{ route('admin.orders.activate', $order) }}" class="mt-4">
                        @csrf
                        @method('PUT')
                        @if ($order->payment_status === 'paid')
                            <button type="submit" class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700">
                                Activate subscription
                            </button>
                        @else
                            <p class="text-xs text-amber-700">Payment must be <strong>verified (paid)</strong> before this order can become an active subscription.</p>
                        @endif
                    </form>
                @endif
            </div>

            {{-- WhatsApp share --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-500">WhatsApp share</h2>

                @if ($share['eligible'])
                    <p class="mt-2 text-sm text-neutral-600">
                        Shareable. Open the link to review and send from your device — the server never sends on its own.
                    </p>
                    <textarea id="whatsapp-message" rows="6" readonly
                              class="mt-2 w-full rounded-lg border border-neutral-200 bg-neutral-50 p-3 font-mono text-xs text-neutral-700">{{ $share['message'] }}</textarea>

                    <div class="mt-3 space-y-2">
                        <a href="{{ $share['url'] }}" target="_blank" rel="noopener"
                           class="block rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                            {{ \Illuminate\Support\Str::limit(urldecode($share['url']), 60, '…') }}
                        </a>
                        <button type="button"
                                class="w-full rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-700"
                                onclick="navigator.clipboard.writeText(document.getElementById('whatsapp-message').value)"
                                title="Copy the prefilled message to the clipboard">
                            Copy message
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.orders.whatsapp-log', $order) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="message" value="{{ $share['message'] }}">
                        <button type="submit" class="text-xs text-neutral-500 underline hover:text-neutral-800">
                            Record that this share was prepared
                        </button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-neutral-500">{{ $share['reason'] }}</p>
                @endif

                @if ($logs->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold text-neutral-400">Recent share activity</h3>
                    <ul class="mt-2 space-y-1 text-xs text-neutral-500">
                        @foreach ($logs as $log)
                            <li class="flex items-center justify-between">
                                <span>
                                    {{ $log->normalized_phone }}
                                    <span class="text-neutral-400">· {{ $log->status }}</span>
                                </span>
                                <span class="text-neutral-400">{{ $log->created_at->format('M d H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
