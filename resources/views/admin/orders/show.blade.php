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
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                <span class="font-mono">{{ $order->order_number ?? 'Unnumbered' }}</span>
            </h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Team {{ $order->team_code_snapshot }} · created {{ $order->created_at->format('M d, Y H:i') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
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

    {{-- Closing Value Approval Banner --}}
    @if ($order->closing_value_status === 'Requested')
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-200 text-amber-900 font-bold text-xs">!</span>
                    <h2 class="text-sm font-bold text-amber-900">Closing Value Approval Requested</h2>
                </div>
                <span class="text-xs text-amber-700">Requested {{ $order->closing_approval_requested_at?->diffForHumans() }}</span>
            </div>

            <p class="text-xs text-amber-800 leading-relaxed">
                The employee set a closing value of <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->closing_value, 2) }}</strong>,
                which is below the product's minimum floor price of <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->minimum_closing_price_snapshot, 2) }}</strong>
                (Catalog Base Price: {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->product_value, 2) }}).
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-amber-200">
                {{-- Accept Form --}}
                <form method="POST" action="{{ route('admin.orders.closing-approval.accept', $order) }}" class="space-y-2 bg-white p-3.5 rounded-lg border border-amber-200">
                    @csrf
                    <span class="text-xs font-semibold text-emerald-800 block">Option A: Accept Closing Value</span>
                    <div>
                        <label class="block text-[11px] text-neutral-500">Adjusted Value (leave blank to accept {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->closing_value, 2) }})</label>
                        <input type="number" step="0.01" name="adjusted_closing_value" placeholder="{{ $order->closing_value }}" class="mt-0.5 w-full rounded border border-neutral-300 px-2 py-1 text-xs">
                    </div>
                    <div>
                        <input type="text" name="notes" placeholder="Approval notes (optional)" class="w-full rounded border border-neutral-300 px-2 py-1 text-xs">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-emerald-700 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
                        Accept Closing Value
                    </button>
                </form>

                {{-- Reject Form --}}
                <form method="POST" action="{{ route('admin.orders.closing-approval.reject', $order) }}" class="space-y-2 bg-white p-3.5 rounded-lg border border-amber-200">
                    @csrf
                    <span class="text-xs font-semibold text-red-800 block">Option B: Reject Closing Value</span>
                    <div>
                        <label class="block text-[11px] text-neutral-500">Mandatory Rejection Reason</label>
                        <textarea name="rejection_reason" required rows="2" placeholder="Explain why this closing value is rejected…" class="mt-0.5 w-full rounded border border-neutral-300 px-2 py-1 text-xs"></textarea>
                    </div>
                    <button type="submit" onclick="return confirm('Reject this closing value? Order will be suspended.')" class="w-full rounded-md bg-red-700 py-1.5 text-xs font-semibold text-white hover:bg-red-800">
                        Reject Closing Value
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Order Cancellation Banner if Cancelled --}}
    @if ($order->order_status === 'cancelled')
        <div class="mt-4 rounded-xl border border-red-300 bg-red-100/70 p-4 text-xs text-red-900">
            <strong>Order Cancelled:</strong> This order was formally cancelled on {{ $order->cancelled_at?->format('M d, Y H:i') }}.
            @if ($order->cancellation_reason)
                <p class="mt-1">Reason: {{ $order->cancellation_reason }}</p>
            @endif
        </div>
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
                            <dd class="font-medium text-neutral-900">{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $amount, 2) }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 flex flex-wrap items-center gap-2">
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
                    <div class="mt-3 w-full overflow-x-auto rounded-lg border border-neutral-200">
                        <table class="w-full min-w-[460px] text-xs whitespace-nowrap">
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
                                        <td class="px-3 py-2 text-right tabular-nums font-medium">{{ \App\Models\Setting::currencySymbolFor($payment->currency) }}{{ number_format((float) $payment->amount, 2) }}</td>
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

            {{-- Refunds Management Card (Phase 11) --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-500">Refunds & Reversals</h2>
                    <span class="text-xs text-neutral-400">{{ $order->refunds->count() }} refund(s)</span>
                </div>

                <div class="mt-3 flex items-center justify-between border-b border-neutral-100 pb-3 text-xs">
                    <div>
                        <span class="text-neutral-500">Total Refunded:</span>
                        <strong class="font-medium text-neutral-900">{{ \App\Models\Setting::currencySymbol() }}{{ number_format($order->totalRefunded(), 2) }}</strong>
                    </div>
                    <div>
                        <span class="text-neutral-500">Remaining Refundable:</span>
                        <strong class="font-medium {{ $order->remainingRefundable() > 0 ? 'text-emerald-700' : 'text-neutral-400' }}">
                            {{ \App\Models\Setting::currencySymbol() }}{{ number_format($order->remainingRefundable(), 2) }}
                        </strong>
                    </div>
                </div>

                @if ($order->refunds->isNotEmpty())
                    <div class="mt-3 divide-y divide-neutral-100 text-xs">
                        @foreach ($order->refunds as $refund)
                            <div class="py-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-neutral-900">
                                            {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float)$refund->amount, 2) }}
                                        </span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $refund->type === 'full' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ ucfirst($refund->type) }}
                                        </span>
                                    </div>
                                    <span class="text-neutral-400 text-[10px]">{{ $refund->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="mt-1 text-neutral-600 italic">"{{ $refund->reason }}"</p>
                                <p class="mt-0.5 text-[10px] text-neutral-400">Processed by: {{ $refund->admin?->name ?? 'Super Admin' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-xs text-neutral-400">No refunds recorded on this order.</p>
                @endif

                @if ($order->remainingRefundable() > 0)
                    <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}" class="mt-4 border-t border-neutral-100 pt-3 space-y-2">
                        @csrf
                        <h3 class="text-xs font-semibold text-neutral-700">Issue Refund</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-neutral-500 mb-0.5">Amount</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $order->remainingRefundable() }}" name="amount"
                                       value="{{ old('amount', $order->remainingRefundable()) }}" placeholder="Amount" required
                                       class="w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 mb-0.5">Type</label>
                                <select name="type" class="w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                                    <option value="full">Full Refund</option>
                                    <option value="partial">Partial Refund</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-neutral-500 mb-0.5">Mandatory Reason</label>
                            <input type="text" name="reason" placeholder="Reason for refund (chargeback, customer request, etc.)…" required
                                   class="w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                        </div>
                        <button type="submit" onclick="return confirm('Process this refund? This updates financial status and notifies assigned staff.')"
                                class="w-full rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-neutral-700">
                            Process Refund
                        </button>
                    </form>
                @endif
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
                            <dd class="tabular-nums">{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) ($order->subscription->price_snapshot ?? $order->product_price_snapshot ?? $order->product_value), 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Verified Amount Paid</dt>
                            <dd class="tabular-nums font-medium text-emerald-700">{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) ($order->subscription->amount_paid ?? $order->amount_received), 2) }}</dd>
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

            {{-- API Key delivery --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-500">Customer API Key</h2>
                <p class="mt-1 text-xs text-neutral-400">
                    Store the customer's own API key (delivered manually). The key is encrypted at rest and never generated by LeadX.
                </p>

                @if ($apiKeyStatus['configured'])
                    <div class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-medium text-neutral-700">Stored key</span>
                                <span class="ml-2 font-mono text-xs text-neutral-500">{{ $apiKeyStatus['masked_key'] }}</span>
                            </div>
                            <form method="POST" action="{{ route('admin.orders.api-key.reveal', $order) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-neutral-700 underline hover:text-neutral-900">
                                    Reveal
                                </button>
                            </form>
                        </div>
                        <div class="mt-1 text-[10px] text-neutral-400">
                            Updated {{ \Illuminate\Support\Carbon::parse($apiKeyStatus['updated_at'])->diffForHumans() }} by {{ $apiKeyStatus['updated_by_type'] ?? '—' }}
                        </div>
                        @if ($revealedKey && (int) $revealedKey['order_id'] === $order->id)
                            <div class="mt-2 rounded border border-amber-200 bg-amber-50 p-2">
                                <p class="text-[10px] font-medium text-amber-800">Revealed (one-time display — copy now):</p>
                                <code class="block mt-1 break-all font-mono text-xs text-amber-900">{{ $revealedKey['key'] }}</code>
                                <p class="mt-1 text-[10px] text-amber-600">This value is not stored in any log. It will be cleared from this session.</p>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="mt-2 text-xs text-neutral-500">No API key has been stored for this order yet.</p>
                @endif

                <form method="POST" action="{{ route('admin.orders.api-key.store', $order) }}" class="mt-3">
                    @csrf
                    <label for="api_key_input" class="text-xs font-medium text-neutral-700">Save / replace API key</label>
                    <div class="mt-1 flex gap-2">
                        <input type="text" id="api_key_input" name="api_key" placeholder="Enter customer API key…" required
                               class="flex-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-mono focus:border-neutral-500 focus:outline-none">
                        <button type="submit" class="rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-neutral-700">
                            Save Key
                        </button>
                    </div>
                    @error('api_key')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </form>
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
                           class="block rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-sm font-medium text-emerald-700 hover:bg-emerald-100 break-all">
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

            {{-- Super Admin Order Cancellation Card (Phase 11) --}}
            @if ($order->order_status !== 'cancelled')
                <div class="rounded-xl border border-red-200 bg-white p-5 shadow-xs">
                    <h2 class="text-sm font-semibold text-red-900">Cancel Order</h2>
                    <p class="mt-1 text-xs text-neutral-500">Cancelling this order stops any active subscriptions and marks the deal as cancelled.</p>

                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-neutral-700">Mandatory Cancellation Reason</label>
                            <textarea name="cancellation_reason" required rows="2" placeholder="State reason for order cancellation…"
                                      class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-1.5 text-xs focus:border-red-500 focus:outline-none"></textarea>
                        </div>
                        <button type="submit" onclick="return confirm('Are you sure you want to cancel this order? This action will suspend subscriptions.')"
                                class="rounded-lg bg-red-700 px-4 py-2 text-xs font-semibold text-white hover:bg-red-800">
                            Cancel Order
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
