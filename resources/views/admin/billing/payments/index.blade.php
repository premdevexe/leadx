@extends('layouts.admin-dashboard')

@section('title', 'Payments & Ledger')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Payments &amp; Transaction History</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Unified ledger of invoice and order payments.</p>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.billing.payments.index') }}" class="grid gap-3 sm:grid-cols-5">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="UTR / Ref, customer, invoice #..." class="form-input text-xs sm:col-span-2">
            <select name="method" class="form-input text-xs">
                <option value="">All Methods</option>
                @foreach ($methods as $code => $label)
                    <option value="{{ $code }}" @selected(($filters['method'] ?? '') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="form-input text-xs">
                <option value="">All Statuses</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary text-xs">Filter</button>
        </form>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-white overflow-hidden">
        @if ($payments->isEmpty())
            <p class="p-8 text-center text-sm text-neutral-400">No payments found.</p>
        @else
            <div class="table-wrapper">
                <table class="data-table w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Invoice / Order</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-left">Reference / UTR</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $p)
                            @php $cur = \App\Models\Setting::currencySymbolFor($p->currency); @endphp
                            <tr>
                                <td class="px-4 py-3 text-xs text-neutral-600">{{ $p->payment_date?->format('M d, Y') ?? $p->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ $p->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs font-mono">
                                    @if ($p->invoice)
                                        <a href="{{ route('admin.billing.invoices.show', $p->invoice) }}" class="text-neutral-900 hover:underline">{{ $p->invoice->invoice_number }}</a>
                                    @elseif ($p->order)
                                        <a href="{{ route('admin.orders.show', $p->order) }}" class="text-neutral-700 hover:underline">{{ $p->order->order_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs uppercase">{{ $p->payment_method }}</td>
                                <td class="px-4 py-3 text-xs font-mono">{{ $p->transaction_reference ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-sm font-semibold">{{ $cur }}{{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="badge {{ $p->payment_status === 'paid' ? 'badge-success' : ($p->payment_status === 'failed' ? 'badge-danger' : 'badge-neutral') }}">
                                        {{ ucfirst($p->payment_status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    @if ($p->payment_status === 'pending' && $p->invoice)
                                        <form method="POST" action="{{ route('admin.billing.payments.confirm', $p) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-emerald-700 font-semibold hover:underline">Confirm</button>
                                        </form>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-neutral-100">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection
