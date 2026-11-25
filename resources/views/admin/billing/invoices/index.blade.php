@extends('layouts.admin-dashboard')

@section('title', 'Invoices')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Invoices</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Create, manage, preview, and track customer invoices.</p>
        </div>
        <a href="{{ route('admin.billing.invoices.create') }}" class="btn btn-primary">+ Create Invoice</a>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.billing.invoices.index') }}" class="grid gap-3 sm:grid-cols-5">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Invoice #, customer, email..." class="form-input text-xs sm:col-span-2">
            <select name="status" class="form-input text-xs">
                <option value="">All Statuses</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input text-xs">
            <div class="flex gap-2">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input text-xs flex-1">
                <button type="submit" class="btn btn-secondary text-xs">Filter</button>
            </div>
        </form>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-white overflow-hidden">
        @if ($invoices->isEmpty())
            <div class="p-8 text-center">
                <p class="text-sm text-neutral-500">No invoices match your filters.</p>
                <a href="{{ route('admin.billing.invoices.create') }}" class="mt-3 inline-block btn btn-primary text-xs">Create First Invoice</a>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Invoice #</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Issue Date</th>
                            <th class="px-4 py-3 text-left">Due Date</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Balance Due</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $inv)
                            @php $cur = \App\Models\Setting::currencySymbolFor($inv->currency); @endphp
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs font-semibold">
                                    <a href="{{ route('admin.billing.invoices.show', $inv) }}" class="text-neutral-900 hover:underline">{{ $inv->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-neutral-900">{{ $inv->customer_name }}</div>
                                    <div class="text-xs text-neutral-400">{{ $inv->customer_email ?? $inv->customer_phone ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-neutral-600">{{ $inv->issue_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-xs {{ $inv->isOverdue() ? 'text-red-600 font-semibold' : 'text-neutral-600' }}">
                                    {{ $inv->due_date ? $inv->due_date->format('M d, Y') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-sm font-semibold">{{ $cur }}{{ number_format((float) $inv->grand_total, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-sm {{ (float) $inv->balance_due > 0 ? 'text-amber-700 font-medium' : 'text-neutral-400' }}">
                                    {{ $cur }}{{ number_format((float) $inv->balance_due, 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $inv->status === 'paid' ? 'badge-success' : ($inv->status === 'overdue' || $inv->status === 'cancelled' ? 'badge-danger' : 'badge-neutral') }}">
                                        {{ $statuses[$inv->status] ?? ucfirst($inv->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs space-x-2">
                                    <a href="{{ route('admin.billing.invoices.show', $inv) }}" class="text-neutral-700 hover:text-neutral-900 font-medium">View</a>
                                    <a href="{{ route('admin.billing.invoices.pdf', $inv) }}" class="text-emerald-700 hover:text-emerald-900 font-medium">PDF</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-neutral-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection
