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
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.orders.edit', $order) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-sm hover:bg-neutral-50 transition">
                Edit Order
            </a>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">← All orders</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        {{-- Left: order details --}}
        <div class="space-y-5">
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-500">Order Information</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-500">Customer</dt>
                        <dd class="text-right">
                            <span class="font-medium text-neutral-900">{{ $order->customer_name }}</span><br>
                            <span class="text-xs text-neutral-400">{{ $order->customer_phone ?? 'no phone on file' }}</span>
                            @if ($order->customer_email)
                                <br><span class="text-xs text-neutral-400">{{ $order->customer_email }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-500">Team</dt>
                        <dd>{{ $order->team?->team_name ?? '—' }} <span class="font-mono text-xs text-neutral-400">({{ $order->team_code_snapshot }})</span></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-500">Assigned Employee</dt>
                        <dd>{{ $order->assignedEmployee?->username ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-neutral-500">Catalog Product</dt>
                        <dd class="text-right">
                            <span class="font-medium text-neutral-900">{{ $order->product_name_snapshot ?? $order->product_name ?? 'Manual / Custom' }}</span>
                            <br><span class="text-xs text-neutral-400">Duration: {{ \App\Models\SalesOrder::DURATIONS[$order->product_duration_snapshot ?? $order->duration] ?? ($order->product_duration_snapshot ?? $order->duration ?? '1 year') }}</span>
                        </dd>
                    </div>
                </dl>

                <h2 class="mt-5 text-sm font-semibold text-neutral-500">Financial Summary</h2>
                <dl class="mt-2 space-y-1.5 text-sm">
                    @foreach ([
                        'Product catalog value' => $order->product_price_snapshot ?? $order->product_value,
                        'Discount applied' => $order->discount_amount,
                        'Closing value (due)' => $order->closing_value,
                        'Verified collected revenue' => $order->amount_received,
                    ] as $label => $amount)
                        <div class="flex justify-between tabular-nums">
                            <dt class="text-neutral-500">{{ $label }}</dt>
                            <dd class="font-medium text-neutral-900">{{ number_format((float) $amount, 2) }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 flex items-center gap-3">
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-neutral-100 text-neutral-600' }}">
                        Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                    </span>
                    @if ($order->payment_method)
                        <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600">
                            Via: {{ \App\Models\Setting::paymentMethodLabel($order->payment_method) }}
                        </span>
                    @endif
                    <span class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600">
                        Status: {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                    </span>
                </div>
            </div>

            {{-- Payments Management Card --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-500">Payments & Receipts</h2>
                    <span class="text-xs text-neutral-400">{{ $order->payments->count() }} record(s)</span>
                </div>

                @if ($order->payments->isEmpty())
                    <p class="mt-3 text-sm text-neutral-400">No payment receipts recorded for this order yet.</p>
                @else
                    <div class="mt-3 overflow-hidden rounded-lg border border-neutral-200">
                        <table class="w-full text-xs">
                            <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Method</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @foreach ($order->payments as $payment)
                                    <tr>
                                        <td class="px-3 py-2 text-neutral-600">{{ $payment->created_at->format('M d, Y') }}</td>
                                        <td class="px-3 py-2 uppercase font-mono text-neutral-700">{{ $payment->payment_method }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums font-medium">{{ number_format((float) $payment->amount, 2) }}</td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $paymentColors[$payment->payment_status] ?? 'bg-neutral-100' }}">
                                                {{ ucfirst($payment->payment_status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            @if ($payment->payment_status === 'pending')
                                                <form method="POST" action="{{ route('admin.orders.payments.verify', [$order, $payment]) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-medium text-emerald-700 hover:text-emerald-900" title="Verify payment">
                                                        Verify
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.orders.payments.reject', [$order, $payment]) }}" class="inline ml-2">
                                                    @csrf
                                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800" title="Reject payment">
                                                        Reject
                                                    </button>
                                                </form>
                                            @elseif ($payment->isVerified())
                                                <span class="text-[10px] text-emerald-700">✓ Verified</span>
                                            @else
                                                <span class="text-[10px] text-neutral-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Record a new payment form --}}
                <form method="POST" action="{{ route('admin.orders.payments.store', $order) }}" class="mt-4 border-t border-neutral-100 pt-3">
                    @csrf
                    <p class="text-xs font-medium text-neutral-700 mb-2">Record a Payment Receipt</p>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div>
                            <input type="number" step="0.01" min="0.01" name="amount"
                                   value="{{ old('amount', max((float)$order->closing_value - (float)$order->amount_received, 0) ?: '') }}"
                                   placeholder="Amount" required
                                   class="w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                        </div>
                        <div>
                            <select name="payment_method" required
                                    class="w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                                @foreach ($enabledPaymentMethods ?? ['bank_transfer', 'cash', 'upi', 'gateway'] as $pm)
                                    <option value="{{ $pm }}">{{ ucfirst(str_replace('_', ' ', $pm)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="w-full rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-neutral-700">
                                Record Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right: subscription + actions + WhatsApp --}}
        <div class="space-y-5">
            {{-- Subscription + activate --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-500">Subscription Status</h2>
                @if ($order->subscription)
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Status</dt>
                            <dd>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $order->subscription->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                                    {{ ucfirst($order->subscription->status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Plan / Snapshot</dt>
                            <dd class="font-medium">{{ $order->subscription->plan_snapshot ?? $order->product_name_snapshot ?? $order->product_name ?? 'Standard' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Price Snapshot</dt>
                            <dd class="tabular-nums">{{ number_format((float) ($order->subscription->price_snapshot ?? $order->product_price_snapshot ?? $order->product_value), 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Verified Amount Paid</dt>
                            <dd class="tabular-nums font-medium text-emerald-700">{{ number_format((float) ($order->subscription->amount_paid ?? $order->amount_received), 2) }}</dd>
                        </div>
                        @if ($order->subscription->started_at)
                            <div class="flex justify-between"><dt class="text-neutral-500">Started</dt><dd>{{ $order->subscription->started_at->toDateString() }}</dd></div>
                        @endif
                        @if ($order->subscription->expires_at)
                            <div class="flex justify-between"><dt class="text-neutral-500">Expires</dt><dd class="font-medium text-neutral-900">{{ $order->subscription->expires_at->toDateString() }}</dd></div>
                        @endif
                        @if ($order->subscription->renewal_date)
                            <div class="flex justify-between"><dt class="text-neutral-500">Renewal Date</dt><dd>{{ $order->subscription->renewal_date->toDateString() }}</dd></div>
                        @endif
                    </dl>
                @else
                    <p class="mt-2 text-sm text-neutral-400">No active subscription row for this order.</p>
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
