@extends('layouts.admin-dashboard')

@section('title', 'Customer: '.$customer->name)

@php
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ $customer->name }}</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Customer profile · Team {{ $customer->team?->team_code ?? '—' }} · Member since {{ $customer->created_at->format('M d, Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.billing.invoices.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary text-xs">+ Create Invoice</a>
            <a href="{{ route('admin.customers') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; All customers</a>
        </div>
    </div>

    {{-- Profile Tabs --}}
    <div class="mt-4 flex gap-2 border-b border-neutral-200 pb-2">
        <button type="button" data-tab-btn="overview" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-neutral-900 text-white">Overview &amp; Orders</button>
        <button type="button" data-tab-btn="billing" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-neutral-100 text-neutral-700 hover:bg-neutral-200">Billing History ({{ $customer->invoices->count() }})</button>
    </div>

    <div id="tab-overview" class="mt-5 grid gap-5 lg:grid-cols-3">
        {{-- Profile Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 lg:col-span-1">
            <h2 class="text-sm font-semibold text-neutral-500">Customer Info</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Phone</dt>
                    <dd class="font-mono text-xs">{{ $customer->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Email</dt>
                    <dd class="text-xs text-neutral-700">{{ $customer->email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Status</dt>
                    <dd>
                        <span class="badge badge-success">
                            {{ ucfirst($customer->status) }}
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Team</dt>
                    <dd>{{ $customer->team?->team_name ?? '—' }} ({{ $customer->team?->team_code ?? '—' }})</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Total Purchases</dt>
                    <dd class="tabular-nums font-semibold">{{ $customer->purchases_count }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Total Spent</dt>
                    <dd class="tabular-nums font-semibold text-emerald-700">{{ $cur }}{{ number_format((float) $customer->total_purchased, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Last Purchase</dt>
                    <dd class="text-xs text-neutral-500">{{ $customer->last_purchase_at ? $customer->last_purchase_at->format('M d, Y H:i') : '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Subscriptions & Products Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-neutral-500">Subscriptions & Products</h2>
            @if ($customer->subscriptions->isEmpty())
                <p class="mt-3 text-sm text-neutral-400">No active or historical subscriptions recorded for this customer.</p>
            @else
                <div class="mt-3 table-wrapper">
                    <table class="data-table w-full" style="min-width:560px">
                        <thead>
                            <tr>
                                <th class="px-3 py-2">Product / Plan</th>
                                <th class="px-3 py-2 text-right">Price</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Started</th>
                                <th class="px-3 py-2 text-left">Expires</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer->subscriptions as $sub)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">
                                        {{ $sub->product?->name ?? $sub->plan_snapshot ?? 'Standard Product' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $cur }}{{ number_format((float) ($sub->price_snapshot ?? $sub->amount_paid), 2) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="badge {{ $sub->status === 'active' ? 'badge-success' : 'badge-neutral' }}">
                                            {{ ucfirst($sub->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-neutral-500">{{ $sub->started_at ? $sub->started_at->toDateString() : '—' }}</td>
                                    <td class="px-3 py-2 {{ $sub->isActive() ? 'font-medium text-neutral-900' : 'text-neutral-400' }}">
                                        {{ $sub->expires_at ? $sub->expires_at->toDateString() : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Purchase & Order History --}}
            <h2 class="mt-6 text-sm font-semibold text-neutral-500">Order & Purchase History</h2>
            @if ($orders->isEmpty())
                <p class="mt-3 text-sm text-neutral-400">No sales orders found.</p>
            @else
                <div class="mt-3 table-wrapper">
                    <table class="data-table w-full" style="min-width:640px">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Order #</th>
                                <th class="px-3 py-2 text-left">Product Snapshot</th>
                                <th class="px-3 py-2 text-right">Closing Value</th>
                                <th class="px-3 py-2 text-right">Received</th>
                                <th class="px-3 py-2 text-left">Payment</th>
                                <th class="px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="px-3 py-2 font-mono font-medium">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="text-neutral-900 hover:underline">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-neutral-700">
                                        {{ $order->product_name_snapshot ?? $order->product_name ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $cur }}{{ number_format((float) $order->closing_value, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-emerald-700">
                                        {{ $cur }}{{ number_format((float) $order->amount_received, 2) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="badge {{ $order->payment_status === 'paid' ? 'badge-success' : 'badge-neutral' }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="text-[11px] text-neutral-600 hover:text-neutral-900">
                                            View Order &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Billing Tab --}}
    <div id="tab-billing" class="mt-5 space-y-5 hidden">
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                <div class="text-xs text-neutral-500">Total Purchased</div>
                <div class="mt-1 text-xl font-bold tabular-nums">{{ $cur }}{{ number_format($billingSummary['total_purchased'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                <div class="text-xs text-neutral-500">Total Paid</div>
                <div class="mt-1 text-xl font-bold tabular-nums text-emerald-700">{{ $cur }}{{ number_format($billingSummary['total_paid'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                <div class="text-xs text-neutral-500">Pending Amount</div>
                <div class="mt-1 text-xl font-bold tabular-nums text-amber-700">{{ $cur }}{{ number_format($billingSummary['pending_amount'] ?? 0, 2) }}</div>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                <div class="text-xs text-neutral-500">Outstanding Balance</div>
                <div class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ $cur }}{{ number_format($billingSummary['outstanding_balance'] ?? 0, 2) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-neutral-800">Invoice History</h2>
                <a href="{{ route('admin.billing.invoices.create', ['customer_id' => $customer->id]) }}" class="text-xs font-semibold text-emerald-700 hover:underline">+ New Invoice</a>
            </div>
            @if ($customer->invoices->isEmpty())
                <p class="text-sm text-neutral-400">No invoices issued for this customer.</p>
            @else
                <div class="table-wrapper">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Invoice #</th>
                                <th class="px-3 py-2 text-left">Issue Date</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Balance</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer->invoices as $inv)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs font-semibold">
                                        <a href="{{ route('admin.billing.invoices.show', $inv) }}" class="hover:underline">{{ $inv->invoice_number }}</a>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-neutral-600">{{ $inv->issue_date->format('M d, Y') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs font-semibold">{{ \App\Models\Setting::currencySymbolFor($inv->currency) }}{{ number_format((float) $inv->grand_total, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs">{{ \App\Models\Setting::currencySymbolFor($inv->currency) }}{{ number_format((float) $inv->balance_due, 2) }}</td>
                                    <td class="px-3 py-2 text-xs"><span class="badge badge-neutral">{{ \App\Models\Invoice::STATUSES[$inv->status] ?? ucfirst($inv->status) }}</span></td>
                                    <td class="px-3 py-2 text-right text-xs space-x-2">
                                        <a href="{{ route('admin.billing.invoices.show', $inv) }}" class="text-neutral-700 hover:underline">Open</a>
                                        <a href="{{ route('admin.billing.invoices.pdf', $inv) }}" class="text-emerald-700 hover:underline">PDF</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-800 mb-3">Payment History</h2>
                @if ($customer->payments->isEmpty())
                    <p class="text-sm text-neutral-400">No payments recorded.</p>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($customer->payments as $p)
                            <li class="flex justify-between border-b border-neutral-100 pb-1.5">
                                <span>{{ $p->payment_date?->format('M d, Y') ?? $p->created_at->format('M d, Y') }} &middot; {{ strtoupper($p->payment_method) }} {{ $p->invoice ? '('.$p->invoice->invoice_number.')' : '' }}</span>
                                <span class="font-semibold tabular-nums text-emerald-700">{{ \App\Models\Setting::currencySymbolFor($p->currency) }}{{ number_format((float) $p->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-800 mb-3">Refund History</h2>
                @if ($customer->refunds->isEmpty())
                    <p class="text-sm text-neutral-400">No refunds recorded.</p>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($customer->refunds as $r)
                            <li class="flex justify-between border-b border-neutral-100 pb-1.5">
                                <span>{{ $r->processed_at?->format('M d, Y') ?? $r->created_at->format('M d, Y') }} &middot; {{ $r->reason }}</span>
                                <span class="font-semibold tabular-nums text-red-600">-{{ $cur }}{{ number_format((float) $r->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const buttons = document.querySelectorAll('[data-tab-btn]');
            const overview = document.getElementById('tab-overview');
            const billing = document.getElementById('tab-billing');
            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const target = btn.dataset.tabBtn;
                    buttons.forEach(b => {
                        b.classList.remove('bg-neutral-900', 'text-white');
                        b.classList.add('bg-neutral-100', 'text-neutral-700');
                    });
                    btn.classList.remove('bg-neutral-100', 'text-neutral-700');
                    btn.classList.add('bg-neutral-900', 'text-white');
                    overview.classList.toggle('hidden', target !== 'overview');
                    billing.classList.toggle('hidden', target !== 'billing');
                });
            });
        })();
    </script>
@endsection
