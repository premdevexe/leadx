@extends('layouts.employee')

@section('title', 'Invoices & Billing')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Invoices &amp; Billing</h1>
            <p class="mt-0.5 text-sm text-neutral-500">View permitted customer invoices and download PDFs.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('employee.billing.create') }}" class="btn btn-primary">+ Create Invoice</a>
        @endif
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-white overflow-hidden">
        @if ($invoices->isEmpty())
            <p class="p-8 text-center text-sm text-neutral-400">No invoices found.</p>
        @else
            <div class="table-wrapper">
                <table class="data-table w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Invoice #</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Issue Date</th>
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
                                <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $inv->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ $inv->customer_name }}</td>
                                <td class="px-4 py-3 text-xs text-neutral-600">{{ $inv->issue_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-sm font-semibold">{{ $cur }}{{ number_format((float) $inv->grand_total, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-sm">{{ $cur }}{{ number_format((float) $inv->balance_due, 2) }}</td>
                                <td class="px-4 py-3 text-xs"><span class="badge badge-neutral">{{ $statuses[$inv->status] ?? ucfirst($inv->status) }}</span></td>
                                <td class="px-4 py-3 text-right text-xs space-x-2">
                                    <a href="{{ route('employee.billing.show', $inv) }}" class="text-neutral-800 font-medium hover:underline">View</a>
                                    <a href="{{ route('employee.billing.pdf', $inv) }}" class="text-emerald-700 font-medium hover:underline">PDF</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-neutral-100">{{ $invoices->links() }}</div>
        @endif
    </div>
@endsection
