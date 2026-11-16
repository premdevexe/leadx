@extends('layouts.admin-dashboard')

@section('title', 'Order '.$order->order_number)

@php
    $paymentStyles = [
        'pending'        => 'background:var(--c-amber-50);color:var(--c-amber-800)',
        'partially_paid' => 'background:var(--c-blue-50);color:var(--c-blue-800)',
        'paid'           => 'background:var(--c-green-50);color:var(--c-green-800)',
        'failed'         => 'background:var(--c-red-50);color:var(--c-red-800)',
        'refunded'       => 'background:var(--c-neutral-100);color:var(--c-neutral-500)',
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
            <a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-secondary px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                Edit Order
            </a>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; All orders</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 alert alert-danger text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 alert alert-danger text-sm">{{ $errors->first() }}</div>
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

            <p class="text-xs text-amber-900 leading-relaxed">
                @if ((float) $order->minimum_closing_price_snapshot > 0 && (float) $order->closing_value < (float) $order->minimum_closing_price_snapshot)
                    The employee set a closing value of <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->closing_value, 2) }}</strong>,
                    which is below the product's minimum floor price of <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->minimum_closing_price_snapshot, 2) }}</strong>
                    (Catalog Base Price: {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->product_value, 2) }}).
                @else
                    The employee submitted this deal with a closing value of <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->closing_value, 2) }}</strong>
                    (Catalog Base Price: <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->product_value, 2) }}</strong>,
                    Discount: <strong>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->discount_amount, 2) }}</strong>).
                    Super Admin approval is required before the subscription can be activated.
                @endif
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-amber-200">
                {{-- Accept Form --}}
                <form method="POST" action="{{ route('admin.orders.closing-approval.accept', $order) }}" class="space-y-3 rounded-xl border border-amber-200 bg-white p-4 shadow-xs">
                    @csrf
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Option A: Accept Deal</span>
                        <span class="text-[10px] badge badge-success">Approve</span>
                    </div>
                    <div>
                        <label class="form-label">Adjusted Value (optional)</label>
                        <input type="number" step="0.01" name="adjusted_closing_value" placeholder="{{ $order->closing_value }}" class="form-input mt-1 w-full text-xs">
                        <p class="form-hint">Leave blank to accept {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->closing_value, 2) }}.</p>
                    </div>
                    <div>
                        <label class="form-label">Approval notes (optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Approved discount" class="form-input mt-1 w-full text-xs">
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-2 px-4 text-xs font-semibold">
                        Accept Deal
                    </button>
                </form>

                {{-- Reject Form --}}
                <form method="POST" action="{{ route('admin.orders.closing-approval.reject', $order) }}" class="space-y-3 rounded-xl border border-amber-200 bg-white p-4 shadow-xs">
                    @csrf
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-neutral-800">Option B: Reject deal</span>
                        <span class="text-[10px] badge badge-danger">Suspend order</span>
                    </div>
                    <div>
                        <label class="form-label">Mandatory rejection reason *</label>
                        <textarea name="rejection_reason" required rows="2" placeholder="Explain why this closing value is rejected…" class="form-input mt-1 w-full text-xs"></textarea>
                    </div>
                    <button type="submit" onclick="return confirm('Reject this closing value? Order will be suspended.')" class="btn btn-danger w-full py-2 px-4 text-xs font-semibold">
                        Reject Deal
                    </button>
                        Reject
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Modify Accepted Closing Value Card (Section 4.3) --}}
    @if ($order->closing_value_status === 'Accepted' && $order->order_status !== 'cancelled')
        <div class="mt-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-xs">
            <details class="group">
                <summary class="flex items-center justify-between cursor-pointer list-none text-xs font-semibold text-neutral-700">
                    <span>Modify Accepted Closing Value</span>
                    <span class="text-neutral-400 group-open:rotate-180 transition-transform">&#x25BE;</span>
                </summary>
                <form method="POST" action="{{ route('admin.orders.closing-value.modify', $order) }}" class="mt-3 space-y-2 border-t border-neutral-100 pt-3 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] text-neutral-500">New Closing Value</label>
                            <input type="number" step="0.01" min="0.01" name="new_closing_value" value="{{ $order->closing_value }}" required
                                   class="form-input mt-0.5 w-full text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-neutral-500">Mandatory Reason</label>
                            <input type="text" name="modification_reason" required placeholder="Reason for adjusting accepted value…"
                                   class="form-input mt-0.5 w-full text-xs">
                        </div>
                    </div>
                    <button type="submit" onclick="return confirm('Modify accepted closing value? This will recalculate discount and notify the employee.')"
                            class="btn btn-primary px-3 py-1.5 text-xs font-medium">
                        Update Closing Value
                    </button>
                </form>
            </details>
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
                    <span class="badge {{ $order->payment_status === 'paid' ? 'badge-success' : ($order->payment_status === 'pending' ? 'badge-warning' : 'badge-neutral') }}">
                        <span class="badge-dot"></span> Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                    </span>
                    @if ($order->payment_method)
                        <span class="badge badge-neutral">
                            Via: {{ \App\Models\Setting::paymentMethodLabel($order->payment_method) }}
                        </span>
                    @endif
                    <span class="badge badge-neutral">
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
                    <div class="mt-3 table-wrapper">
                        <table class="data-table w-full" style="min-width:460px">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Method</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->payments as $payment)
                                    <tr>
                                        <td class="px-3 py-2 text-neutral-600">{{ $payment->created_at->format('M d, Y') }}</td>
                                        <td class="px-3 py-2 uppercase font-mono text-neutral-700">{{ $payment->payment_method }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums font-medium">{{ \App\Models\Setting::currencySymbolFor($payment->currency) }}{{ number_format((float) $payment->amount, 2) }}</td>
                                        <td class="px-3 py-2">
                                            <span class="badge {{ $paymentStyles[$payment->payment_status] ?? 'badge-neutral' }}">
                                                {{ ucfirst($payment->payment_status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
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
                                                <span class="text-[10px] text-emerald-700">&#x2713; Verified</span>
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
                                   class="form-input w-full text-xs">
                        </div>
                        <div>
                            <select name="payment_method" required
                                    class="form-select w-full text-xs">
                                @foreach ($enabledPaymentMethods ?? ['bank_transfer', 'cash', 'upi', 'gateway'] as $pm)
                                    <option value="{{ $pm }}">{{ ucfirst(str_replace('_', ' ', $pm)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary w-full py-1.5 px-3 text-xs font-medium">
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
                    <div class="mt-3 text-xs">
                        @foreach ($order->refunds as $refund)
                            <div class="py-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-neutral-900">
                                            {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float)$refund->amount, 2) }}
                                        </span>
                                        <span class="badge {{ $refund->type === 'full' ? 'badge-danger' : 'badge-warning' }}">
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
                                       class="form-input w-full text-xs">
                            </div>
                            <div>
                                <label class="block text-[10px] text-neutral-500 mb-0.5">Type</label>
                                <select name="type" class="form-select w-full text-xs">
                                    <option value="full">Full Refund</option>
                                    <option value="partial">Partial Refund</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-neutral-500 mb-0.5">Mandatory Reason</label>
                            <input type="text" name="reason" placeholder="Reason for refund (chargeback, customer request, etc.)…" required
                                   class="form-input w-full text-xs">
                        </div>
                        <button type="submit" onclick="return confirm('Process this refund? This updates financial status and notifies assigned staff.')"
                                class="btn btn-primary w-full px-3 py-1.5 text-xs font-medium">
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
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $order->subscription->status === 'active' ? 'badge-success' : 'badge-neutral' }}">
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
                        @if ($order->subscription->calendar_sync_status)
                            <div class="flex justify-between items-center pt-1 border-t border-neutral-100">
                                <dt class="text-neutral-500">Google Sync</dt>
                                <dd>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $order->subscription->calendar_sync_status === 'synced' ? 'badge-success' : 'badge-neutral' }}" style="{{ $order->subscription->calendar_sync_status === 'synced' ? 'border:1px solid var(--c-green-200)' : '' }}">
                                        {{ ucfirst($order->subscription->calendar_sync_status) }}
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4 pt-3 border-t border-neutral-100 flex flex-wrap items-center gap-2">
                        <a href="{{ $order->googleCalendarUrl() }}"
                           target="_blank"
                           onclick="window.open(this.href, 'google_calendar_popup', 'width=750,height=650,left=200,top=100,scrollbars=yes,resizable=yes'); return false;"
                           class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 shadow-xs transition">
                            <svg class="h-4 w-4" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            Add to Google Calendar (Popup)
                        </a>

                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800 border border-amber-200">
                            <svg class="h-3 w-3 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            36h Expiry Alert
                        </span>
                    </div>
                @else
                    <p class="mt-2 text-sm text-neutral-400">No active subscription row for this order.</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a href="{{ $order->googleCalendarUrl() }}"
                           target="_blank"
                           onclick="window.open(this.href, 'google_calendar_popup', 'width=750,height=650,left=200,top=100,scrollbars=yes,resizable=yes'); return false;"
                           class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 shadow-xs transition">
                            <svg class="h-4 w-4" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            Add to Google Calendar (Popup)
                        </a>

                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800 border border-amber-200">
                            <svg class="h-3 w-3 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            36h Expiry Alert
                        </span>
                    </div>
                @endif

                @if ($order->order_status !== 'active')
                    <form method="POST" action="{{ route('admin.orders.activate', $order) }}" class="mt-4">
                        @csrf
                        @method('PUT')
                        @if ($order->payment_status === 'paid')
                            <button type="submit" class="btn btn-primary px-3 py-1.5 text-sm font-medium">
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
                    <label for="api_key_input" class="form-label">Save / replace API key</label>
                    <div class="mt-1 flex gap-2">
                        <input type="text" id="api_key_input" name="api_key" placeholder="Enter customer API key…" required
                               class="form-input flex-1 text-xs font-mono">
                        <button type="submit" class="btn btn-primary px-3 py-1.5 text-xs font-medium">
                            Save Key
                        </button>
                    </div>
                    @error('api_key')
                        <p class="form-error">{{ $message }}</p>
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
                            <label class="form-label">Mandatory Cancellation Reason</label>
                            <textarea name="cancellation_reason" required rows="2" placeholder="State reason for order cancellation…"
                                      class="form-input mt-1 w-full text-xs"></textarea>
                        </div>
                        <button type="submit" onclick="return confirm('Are you sure you want to cancel this order? This action will suspend subscriptions.')"
                                class="btn btn-danger px-4 py-2 text-xs font-semibold text-white hover:bg-red-800">
                            Cancel Order
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    {{-- Audit & History Section (Section 4.2, 4.3, 4.4) --}}
    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        {{-- Closing Value Approval History --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-500">Closing Value History</h2>
            @if ($order->closingValueHistories->isNotEmpty())
                <div class="mt-3 text-xs">
                    @foreach ($order->closingValueHistories as $h)
                        <div class="py-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-neutral-800">
                                    {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $h->proposed_value, 2) }}
                                    <span class="ml-1 text-[10px] text-neutral-400 font-normal">({{ ucfirst($h->actor_type) }})</span>
                                </span>
                                <span class="badge {{ $h->status === 'Accepted' ? 'badge-success' : ($h->status === 'Rejected' ? 'badge-danger' : 'badge-warning') }}">
                                    {{ $h->status }}
                                </span>
                            </div>
                            @if ($h->notes)
                                <p class="mt-1 text-neutral-600 italic">"{{ $h->notes }}"</p>
                            @endif
                            <p class="mt-0.5 text-[10px] text-neutral-400">{{ $h->created_at->format('M d, Y H:i') }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-xs text-neutral-400">No closing value history recorded.</p>
            @endif
        </div>

        {{-- Order Edit Audit History --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-500">Order Edit Audit History</h2>
            @if ($order->editHistories->isNotEmpty())
                <div class="mt-3 text-xs">
                    @foreach ($order->editHistories as $eh)
                        <div class="py-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-neutral-800">
                                    Field: <code class="font-mono text-neutral-600">{{ $eh->field_name }}</code>
                                </span>
                                <span class="text-[10px] text-neutral-400">{{ $eh->created_at->format('M d, Y H:i') }}</span>
                            </div>
                            <div class="mt-1 text-[11px] text-neutral-600">
                                <span class="line-through text-neutral-400">{{ $eh->old_value ?? '—' }}</span>
                                <span class="mx-1">&rarr;</span>
                                <strong class="text-neutral-900">{{ $eh->new_value ?? '—' }}</strong>
                            </div>
                            <p class="mt-1 text-neutral-600 italic">"{{ $eh->reason }}"</p>
                            <p class="mt-0.5 text-[10px] text-neutral-400">Admin: {{ $eh->admin?->name ?? 'Super Admin' }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-xs text-neutral-400">No admin edits logged yet.</p>
            @endif
        </div>
    </div>
@endsection
