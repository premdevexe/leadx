@extends('layouts.employee')

@section('title', 'Invoice ' . $invoice->invoice_number)

@php
    $cur = \App\Models\Setting::currencySymbolFor($invoice->currency);
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold font-mono">{{ $invoice->invoice_number }}</h1>
            <p class="text-sm text-neutral-500">{{ $invoice->customer_name }} &middot; {{ \App\Models\Invoice::STATUSES[$invoice->status] ?? ucfirst($invoice->status) }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('employee.billing.pdf', $invoice) }}" class="btn btn-primary text-xs">Download PDF</a>
            <a href="{{ route('employee.billing.index') }}" class="btn btn-secondary text-xs">&larr; Back</a>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 lg:col-span-2">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Unit Price</th>
                        <th class="px-3 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="px-3 py-2 text-sm">{{ $item->description }}</td>
                            <td class="px-3 py-2 text-right text-xs">{{ $item->quantity }}</td>
                            <td class="px-3 py-2 text-right text-xs">{{ $cur }}{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold">{{ $cur }}{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4 text-right text-sm space-y-1">
                <div>Grand Total: <strong>{{ $cur }}{{ number_format((float) $invoice->grand_total, 2) }}</strong></div>
                <div>Paid: <strong class="text-emerald-700">{{ $cur }}{{ number_format((float) $invoice->amount_paid, 2) }}</strong></div>
                <div>Balance Due: <strong class="text-amber-700">{{ $cur }}{{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
            </div>
        </div>

        @if ($canRecordPayment && (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled')
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-800 mb-3">Record Payment (Pending Admin Confirmation)</h2>
                <form method="POST" action="{{ route('employee.billing.payments.store', $invoice) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="form-label text-xs">Amount *</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" name="amount" value="{{ $invoice->balance_due }}" class="form-input text-xs" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Method *</label>
                        <select name="payment_method" class="form-input text-xs" required>
                            <option value="upi">UPI</option>
                            <option value="phonepe">PhonePe</option>
                            <option value="gpay">Google Pay</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs">Reference / UTR</label>
                        <input type="text" name="transaction_reference" class="form-input text-xs">
                    </div>
                    <button type="submit" class="btn btn-primary w-full text-xs">Submit Payment</button>
                </form>
            </div>
        @endif
    </div>
@endsection
