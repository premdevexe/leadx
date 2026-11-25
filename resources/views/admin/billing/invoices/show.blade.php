@extends('layouts.admin-dashboard')

@section('title', 'Invoice ' . $invoice->invoice_number)

@php
    $cur = \App\Models\Setting::currencySymbolFor($invoice->currency);
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold tracking-tight font-mono">{{ $invoice->invoice_number }}</h1>
                <span class="badge {{ $invoice->status === 'paid' ? 'badge-success' : ($invoice->status === 'overdue' || $invoice->status === 'cancelled' ? 'badge-danger' : 'badge-neutral') }}">
                    {{ \App\Models\Invoice::STATUSES[$invoice->status] ?? ucfirst($invoice->status) }}
                </span>
            </div>
            <p class="mt-0.5 text-sm text-neutral-500">
                Customer: <a href="{{ $invoice->customer ? route('admin.customers.show', $invoice->customer) : '#' }}" class="font-medium text-neutral-800 hover:underline">{{ $invoice->customer_name }}</a>
                &middot; Issued {{ $invoice->issue_date->format('M d, Y') }}
                @if ($invoice->due_date)
                    &middot; Due {{ $invoice->due_date->format('M d, Y') }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @php $activeTpl = $invoice->template ?: ($billing['default_template'] ?? 'classic_navy'); @endphp
            <select id="invoice-tpl-switch" class="form-input text-xs py-1.5 w-auto" onchange="const t=this.value; document.getElementById('btn-preview-inv').href='{{ route('admin.billing.invoices.preview', $invoice) }}?template='+t; document.getElementById('btn-pdf-inv').href='{{ route('admin.billing.invoices.pdf', $invoice) }}?template='+t;">
                @foreach (\App\Models\Invoice::TEMPLATES as $tplKey => $tplLabel)
                    <option value="{{ $tplKey }}" @selected($activeTpl === $tplKey)>{{ $tplLabel }}</option>
                @endforeach
            </select>
            <a id="btn-preview-inv" href="{{ route('admin.billing.invoices.preview', $invoice) }}" target="_blank" class="btn btn-secondary text-xs">Preview / Print</a>
            <a id="btn-pdf-inv" href="{{ route('admin.billing.invoices.pdf', $invoice) }}" class="btn btn-primary text-xs">Download PDF</a>
            <form method="POST" action="{{ route('admin.billing.invoices.email', $invoice) }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-secondary text-xs">Email Invoice</button>
            </form>
            @if ($invoice->status === 'draft')
                <form method="POST" action="{{ route('admin.billing.invoices.send', $invoice) }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary text-xs">Mark as Sent</button>
                </form>
            @endif
            @if (! $invoice->isFinanciallyLocked() && (float) $invoice->amount_paid == 0)
                <a href="{{ route('admin.billing.invoices.edit', $invoice) }}" class="btn btn-secondary text-xs">Edit</a>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        {{-- Left 2 columns: Invoice Items, Payments, Refunds, Adjustments --}}
        <div class="space-y-5 lg:col-span-2">
            {{-- Line Items --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-800 mb-3">Line Items</h2>
                <div class="table-wrapper">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Description</th>
                                <th class="px-3 py-2 text-left">Duration</th>
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-right">Unit Price</th>
                                <th class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="px-3 py-2 text-sm font-medium text-neutral-900">{{ $item->description }}</td>
                                    <td class="px-3 py-2 text-xs text-neutral-500">{{ $item->duration ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs">{{ $cur }}{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-sm font-semibold">{{ $cur }}{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row sm:justify-between gap-4 border-t border-neutral-200 pt-4">
                    <div class="text-xs text-neutral-600 max-w-sm">
                        <div class="font-semibold text-neutral-800">Amount in Words</div>
                        <div class="mt-0.5">{{ \App\Services\Billing\AmountInWordsService::convert((float) $invoice->grand_total, $invoice->currency) }}</div>
                        @if ($invoice->notes)
                            <div class="mt-2 font-semibold text-neutral-800">Notes</div>
                            <div>{{ $invoice->notes }}</div>
                        @endif
                    </div>
                    <div class="w-full sm:w-64 space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-neutral-500">Subtotal</span><span class="tabular-nums">{{ $cur }}{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
                        @if ((float) $invoice->discount_amount > 0)
                            <div class="flex justify-between"><span class="text-neutral-500">Discount</span><span class="tabular-nums text-red-600">-{{ $cur }}{{ number_format((float) $invoice->discount_amount, 2) }}</span></div>
                        @endif
                        @if ((float) $invoice->tax_amount > 0)
                            <div class="flex justify-between"><span class="text-neutral-500">Tax ({{ number_format((float) $invoice->tax_rate, 2) }}%)</span><span class="tabular-nums">{{ $cur }}{{ number_format((float) $invoice->tax_amount, 2) }}</span></div>
                        @endif
                        <div class="flex justify-between border-t border-neutral-200 pt-1.5 font-bold"><span>Grand Total</span><span class="tabular-nums">{{ $cur }}{{ number_format((float) $invoice->grand_total, 2) }}</span></div>
                        <div class="flex justify-between text-emerald-700"><span>Paid</span><span class="tabular-nums">{{ $cur }}{{ number_format((float) $invoice->amount_paid, 2) }}</span></div>
                        @if ((float) $invoice->amount_refunded > 0)
                            <div class="flex justify-between text-red-600"><span>Refunded</span><span class="tabular-nums">{{ $cur }}{{ number_format((float) $invoice->amount_refunded, 2) }}</span></div>
                        @endif
                        <div class="flex justify-between border-t border-neutral-200 pt-1.5 font-bold text-amber-700"><span>Balance Due</span><span class="tabular-nums">{{ $cur }}{{ number_format((float) $invoice->balance_due, 2) }}</span></div>
                    </div>
                </div>
            </div>

            {{-- Payment History --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-800 mb-3">Payment Transactions</h2>
                @if ($invoice->payments->isEmpty())
                    <p class="text-sm text-neutral-400">No payments recorded for this invoice yet.</p>
                @else
                    <div class="table-wrapper">
                        <table class="data-table w-full">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Method</th>
                                    <th class="px-3 py-2 text-left">Reference / UTR</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->payments as $pmt)
                                    <tr>
                                        <td class="px-3 py-2 text-xs">{{ $pmt->payment_date?->format('M d, Y') ?? $pmt->created_at->format('M d, Y') }}</td>
                                        <td class="px-3 py-2 text-xs uppercase font-medium">{{ $pmt->payment_method }}</td>
                                        <td class="px-3 py-2 text-xs font-mono">{{ $pmt->transaction_reference ?: '—' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-xs font-semibold">{{ $cur }}{{ number_format((float) $pmt->amount, 2) }}</td>
                                        <td class="px-3 py-2 text-xs">
                                            <span class="badge {{ $pmt->payment_status === 'paid' ? 'badge-success' : 'badge-neutral' }}">{{ ucfirst($pmt->payment_status) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-xs">
                                            @if ($pmt->payment_status === 'pending')
                                                <form method="POST" action="{{ route('admin.billing.payments.confirm', $pmt) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-emerald-700 font-semibold hover:underline">Confirm</button>
                                                </form>
                                            @else
                                                <span class="text-neutral-400">Verified</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Refunds & Credit Notes --}}
            @if ($invoice->refunds->isNotEmpty() || $invoice->adjustments->isNotEmpty())
                <div class="rounded-xl border border-neutral-200 bg-white p-5 space-y-4">
                    @if ($invoice->refunds->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-800 mb-2">Refunds</h3>
                            <ul class="space-y-1.5 text-xs">
                                @foreach ($invoice->refunds as $ref)
                                    <li class="flex justify-between border-b border-neutral-100 pb-1.5">
                                        <span>{{ $ref->processed_at?->format('M d, Y') ?? $ref->created_at->format('M d, Y') }} — {{ $ref->reason }}</span>
                                        <span class="font-semibold text-red-600 tabular-nums">-{{ $cur }}{{ number_format((float) $ref->amount, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($invoice->adjustments->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-800 mb-2">Credit Notes &amp; Adjustments</h3>
                            <ul class="space-y-1.5 text-xs">
                                @foreach ($invoice->adjustments as $adj)
                                    <li class="flex justify-between border-b border-neutral-100 pb-1.5">
                                        <span><strong class="font-mono">{{ $adj->adjustment_number }}</strong> ({{ \App\Models\InvoiceAdjustment::TYPES[$adj->type] ?? $adj->type }}) — {{ $adj->reason }}</span>
                                        <span class="font-semibold tabular-nums">{{ $cur }}{{ number_format((float) $adj->amount, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Audit Trail --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-neutral-800 mb-3">Audit Trail</h2>
                @if ($auditLogs->isEmpty())
                    <p class="text-xs text-neutral-400">No audit entries recorded yet.</p>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($auditLogs as $log)
                            <li class="flex justify-between text-neutral-600 border-b border-neutral-100 pb-1.5">
                                <span class="font-medium text-neutral-800">{{ $log->event }}</span>
                                <span class="text-neutral-400">{{ $log->created_at->format('M d, Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Right column: Record Payment, Refund, Credit Note, Cancel --}}
        <div class="space-y-5">
            @if ((float) $invoice->balance_due > 0 && ! in_array($invoice->status, ['cancelled', 'refunded'], true))
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 class="text-sm font-semibold text-neutral-800 mb-3">Record Payment</h2>
                    <form method="POST" action="{{ route('admin.billing.invoices.payments.store', $invoice) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="form-label text-xs">Amount ({{ $cur }}) *</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" name="amount" value="{{ $invoice->balance_due }}" class="form-input text-xs" required>
                        </div>
                        <div>
                            <label class="form-label text-xs">Payment Method *</label>
                            <select name="payment_method" class="form-input text-xs" required>
                                @foreach ($paymentMethods as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Reference / UTR Number</label>
                            <input type="text" name="transaction_reference" class="form-input text-xs" placeholder="e.g. UTR123456789">
                        </div>
                        <div>
                            <label class="form-label text-xs">Payment Date</label>
                            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="form-input text-xs">
                        </div>
                        <div>
                            <label class="form-label text-xs">Notes</label>
                            <textarea name="notes" rows="2" class="form-input text-xs"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-full text-xs">Confirm &amp; Record Payment</button>
                    </form>
                </div>
            @endif

            @if ((float) $invoice->amount_paid > (float) $invoice->amount_refunded)
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 class="text-sm font-semibold text-neutral-800 mb-3">Record Refund</h2>
                    <form method="POST" action="{{ route('admin.billing.invoices.refunds.store', $invoice) }}" class="space-y-3" onsubmit="return confirm('Record this refund? This action is audited.');">
                        @csrf
                        <div>
                            <label class="form-label text-xs">Refund Amount ({{ $cur }}) *</label>
                            <input type="number" step="0.01" min="0.01" max="{{ (float) $invoice->amount_paid - (float) $invoice->amount_refunded }}" name="amount" class="form-input text-xs" required>
                        </div>
                        <div>
                            <label class="form-label text-xs">Gateway / Bank Reference</label>
                            <input type="text" name="gateway_reference" class="form-input text-xs">
                        </div>
                        <div>
                            <label class="form-label text-xs">Reason *</label>
                            <textarea name="reason" rows="2" class="form-input text-xs" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary w-full text-xs text-red-700">Process Refund</button>
                    </form>
                </div>
            @endif

            @if ($invoice->isFinanciallyLocked())
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 class="text-sm font-semibold text-neutral-800 mb-3">Issue Credit Note / Adjustment</h2>
                    <form method="POST" action="{{ route('admin.billing.invoices.adjustments.store', $invoice) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="form-label text-xs">Type</label>
                            <select name="type" class="form-input text-xs">
                                <option value="credit_note">Credit Note</option>
                                <option value="debit_note">Debit Note</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Amount ({{ $cur }}) *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-input text-xs" required>
                        </div>
                        <div>
                            <label class="form-label text-xs">Reason *</label>
                            <textarea name="reason" rows="2" class="form-input text-xs" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary w-full text-xs">Create Adjustment</button>
                    </form>
                </div>
            @elseif ((float) $invoice->amount_paid == 0 && $invoice->status !== 'cancelled')
                <div class="rounded-xl border border-neutral-200 bg-white p-5">
                    <h2 class="text-sm font-semibold text-red-700 mb-3">Cancel Invoice</h2>
                    <form method="POST" action="{{ route('admin.billing.invoices.cancel', $invoice) }}" class="space-y-3" onsubmit="return confirm('Cancel this invoice?');">
                        @csrf
                        <div>
                            <label class="form-label text-xs">Cancellation Reason *</label>
                            <textarea name="reason" rows="2" class="form-input text-xs" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary w-full text-xs text-red-700">Cancel Invoice</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
