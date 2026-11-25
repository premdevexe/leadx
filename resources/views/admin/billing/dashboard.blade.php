@extends('layouts.admin-dashboard')

@section('title', 'Billing Dashboard')

@php
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Billing &amp; Revenue Dashboard</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Real-time invoicing, collections, outstanding balances, and profit overview.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.billing.invoices.create') }}" class="btn btn-primary">+ New Invoice</a>
            <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-secondary">All Invoices</a>
        </div>
    </div>

    {{-- Date Range Filters --}}
    <div class="mt-4 rounded-xl border border-neutral-200 bg-white p-3">
        <form method="GET" action="{{ route('admin.billing.dashboard') }}" class="flex flex-wrap items-center gap-2">
            @foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year', 'all' => 'All Time'] as $key => $label)
                <a href="{{ route('admin.billing.dashboard', ['range' => $key]) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $preset === $key ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200' }}">
                    {{ $label }}
                </a>
            @endforeach
            <div class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="range" value="custom">
                <input type="date" name="from" value="{{ $customFrom }}" class="form-input text-xs py-1">
                <span class="text-xs text-neutral-400">to</span>
                <input type="date" name="to" value="{{ $customTo }}" class="form-input text-xs py-1">
                <button type="submit" class="btn btn-secondary text-xs py-1.5 px-3">Apply Range</button>
            </div>
        </form>
    </div>

    {{-- KPI Cards --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs font-medium text-neutral-500">Total Revenue (Invoiced)</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-neutral-900">{{ $cur }}{{ number_format($metrics['total_revenue'], 2) }}</div>
            <div class="mt-1 text-xs text-neutral-400">{{ $metrics['total_invoices'] }} total invoices</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs font-medium text-neutral-500">Payments Received</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ $cur }}{{ number_format($metrics['total_payments_received'], 2) }}</div>
            <div class="mt-1 text-xs text-emerald-600">{{ $metrics['paid_invoices'] }} paid invoices</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs font-medium text-neutral-500">Outstanding Amount</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ $cur }}{{ number_format($metrics['outstanding_amount'], 2) }}</div>
            <div class="mt-1 text-xs text-neutral-500">{{ $metrics['unpaid_invoices'] }} unpaid &middot; {{ $metrics['overdue_invoices'] }} overdue</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs font-medium text-neutral-500">Pending Payments</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-neutral-900">{{ $cur }}{{ number_format($metrics['pending_payments_amount'], 2) }}</div>
            <div class="mt-1 text-xs text-neutral-400">{{ $metrics['pending_payments_count'] }} awaiting confirmation</div>
        </div>
    </div>

    {{-- Invoice Status Breakdown + Net Profit --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">Total Invoices</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ $metrics['total_invoices'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">Paid / Unpaid</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">
                <span class="text-emerald-700">{{ $metrics['paid_invoices'] }}</span> / <span class="text-amber-700">{{ $metrics['unpaid_invoices'] }}</span>
            </div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">Overdue Invoices</div>
            <div class="mt-1 text-xl font-semibold tabular-nums text-red-600">{{ $metrics['overdue_invoices'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">Net Profit (Collections - Expenses)</div>
            <div class="mt-1 text-xl font-semibold tabular-nums {{ $metrics['net_profit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $cur }}{{ number_format($metrics['net_profit'], 2) }}
            </div>
            <div class="text-xs text-neutral-400">Expenses: {{ $cur }}{{ number_format($metrics['total_expenses'], 2) }}</div>
        </div>
    </div>

    {{-- Monthly Revenue Chart --}}
    <div class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-neutral-800">Monthly Revenue vs Expenses (Last 6 Months)</h2>
            <div class="flex items-center gap-4 text-xs">
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-600 inline-block"></span> Revenue</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-neutral-400 inline-block"></span> Expenses</span>
            </div>
        </div>
        @php
            $maxVal = max(1, collect($metrics['monthly_chart'])->flatMap(fn($m) => [$m['revenue'], $m['expenses']])->max());
        @endphp
        <div class="grid grid-cols-6 gap-4 items-end h-44 pt-4 border-b border-neutral-200">
            @foreach ($metrics['monthly_chart'] as $point)
                @php
                    $revH = round(($point['revenue'] / $maxVal) * 100);
                    $expH = round(($point['expenses'] / $maxVal) * 100);
                @endphp
                <div class="flex flex-col items-center h-full justify-end">
                    <div class="flex items-end gap-1.5 w-full justify-center h-32">
                        <div class="w-5 bg-emerald-600 rounded-t" style="height: {{ max(4, $revH) }}%" title="Revenue: {{ $cur }}{{ number_format($point['revenue'], 2) }}"></div>
                        <div class="w-5 bg-neutral-300 rounded-t" style="height: {{ max(4, $expH) }}%" title="Expenses: {{ $cur }}{{ number_format($point['expenses'], 2) }}"></div>
                    </div>
                    <div class="mt-2 text-xs text-neutral-500">{{ $point['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Invoices & Recent Transactions --}}
    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-neutral-800">Recent Invoices</h2>
                <a href="{{ route('admin.billing.invoices.index') }}" class="text-xs text-neutral-600 hover:text-neutral-900">View all &rarr;</a>
            </div>
            @if ($metrics['recent_invoices']->isEmpty())
                <p class="text-sm text-neutral-400 py-4">No invoices created yet.</p>
            @else
                <div class="table-wrapper">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Invoice #</th>
                                <th class="px-3 py-2 text-left">Customer</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics['recent_invoices'] as $inv)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">
                                        <a href="{{ route('admin.billing.invoices.show', $inv) }}" class="text-neutral-900 font-semibold hover:underline">{{ $inv->invoice_number }}</a>
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ $inv->customer_name }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs font-medium">{{ \App\Models\Setting::currencySymbolFor($inv->currency) }}{{ number_format((float) $inv->grand_total, 2) }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="badge {{ $inv->status === 'paid' ? 'badge-success' : ($inv->status === 'overdue' ? 'badge-danger' : 'badge-neutral') }}">
                                            {{ \App\Models\Invoice::STATUSES[$inv->status] ?? ucfirst($inv->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-neutral-800">Recent Transactions</h2>
                <a href="{{ route('admin.billing.payments.index') }}" class="text-xs text-neutral-600 hover:text-neutral-900">View ledger &rarr;</a>
            </div>
            @if ($metrics['recent_transactions']->isEmpty())
                <p class="text-sm text-neutral-400 py-4">No transactions recorded yet.</p>
            @else
                <div class="table-wrapper">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Customer</th>
                                <th class="px-3 py-2 text-left">Method</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                                <th class="px-3 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics['recent_transactions'] as $tx)
                                <tr>
                                    <td class="px-3 py-2 text-xs">
                                        <div class="font-medium text-neutral-900">{{ $tx->customer?->name ?? '—' }}</div>
                                        <div class="text-neutral-400 font-mono">{{ $tx->invoice?->invoice_number ?? $tx->order?->order_number ?? '—' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-xs uppercase">{{ $tx->payment_method }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs font-semibold">{{ \App\Models\Setting::currencySymbolFor($tx->currency) }}{{ number_format((float) $tx->amount, 2) }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="badge {{ $tx->payment_status === 'paid' ? 'badge-success' : ($tx->payment_status === 'failed' ? 'badge-danger' : 'badge-neutral') }}">
                                            {{ ucfirst($tx->payment_status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
