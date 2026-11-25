@extends('layouts.admin-dashboard')

@section('title', 'Expenses & Financial Reports')

@php
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Expenses &amp; Financial Reports</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Track business expenses, revenue vs expenses, and net profit.</p>
        </div>
    </div>

    {{-- P&L Summary --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">This Month Revenue</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ $cur }}{{ number_format($summary['monthly_revenue'], 2) }}</div>
            <div class="text-xs text-neutral-400">All-time: {{ $cur }}{{ number_format($summary['total_revenue'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">This Month Expenses</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-red-600">{{ $cur }}{{ number_format($summary['monthly_expenses'], 2) }}</div>
            <div class="text-xs text-neutral-400">All-time: {{ $cur }}{{ number_format($summary['total_expenses'], 2) }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-xs text-neutral-500">Net Profit (All-Time)</div>
            <div class="mt-1 text-2xl font-bold tabular-nums {{ $summary['net_profit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ $cur }}{{ number_format($summary['net_profit'], 2) }}</div>
            <div class="text-xs text-neutral-400">This month: {{ $cur }}{{ number_format($summary['monthly_profit'], 2) }}</div>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        {{-- Record Expense Form --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-800 mb-3">Record Business Expense</h2>
            <form method="POST" action="{{ route('admin.billing.expenses.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="form-label text-xs">Title *</label>
                    <input type="text" name="title" class="form-input text-xs" required placeholder="e.g. AWS Server Hosting">
                </div>
                <div>
                    <label class="form-label text-xs">Category *</label>
                    <select name="category" class="form-input text-xs" required>
                        @foreach ($categories as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label text-xs">Amount ({{ $cur }}) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-input text-xs" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Date *</label>
                        <input type="date" name="expense_date" value="{{ now()->toDateString() }}" class="form-input text-xs" required>
                    </div>
                </div>
                <div>
                    <label class="form-label text-xs">Vendor</label>
                    <input type="text" name="vendor" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Payment Method *</label>
                    <select name="payment_method" class="form-input text-xs" required>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="card">Card</option>
                        <option value="upi">UPI</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label text-xs">Receipt Attachment (JPG/PNG/PDF)</label>
                    <input type="file" name="receipt" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Notes</label>
                    <textarea name="notes" rows="2" class="form-input text-xs"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-full text-xs">Save Expense</button>
            </form>
        </div>

        {{-- Expenses List --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="text-sm font-semibold text-neutral-800">Expense Ledger</h2>
                <form method="GET" action="{{ route('admin.billing.expenses.index') }}" class="flex flex-wrap items-center gap-2">
                    <select name="category" class="form-input text-xs py-1">
                        <option value="">All Categories</option>
                        @foreach ($categories as $k => $label)
                            <option value="{{ $k }}" @selected(($filters['category'] ?? '') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input text-xs py-1">
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input text-xs py-1">
                    <button type="submit" class="btn btn-secondary text-xs py-1 px-2.5">Filter</button>
                </form>
            </div>
            @if ($expenses->isEmpty())
                <p class="text-sm text-neutral-400 py-6 text-center">No expenses recorded yet.</p>
            @else
                <div class="table-wrapper">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Expense #</th>
                                <th class="px-3 py-2 text-left">Title / Vendor</th>
                                <th class="px-3 py-2 text-left">Category</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                                <th class="px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expenses as $exp)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $exp->expense_number }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <div class="font-medium text-neutral-900">{{ $exp->title }}</div>
                                        @if ($exp->vendor)
                                            <div class="text-neutral-400">{{ $exp->vendor }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ $categories[$exp->category] ?? $exp->category }}</td>
                                    <td class="px-3 py-2 text-xs text-neutral-600">{{ $exp->expense_date->format('M d, Y') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-xs font-semibold text-red-600">{{ \App\Models\Setting::currencySymbolFor($exp->currency) }}{{ number_format((float) $exp->amount, 2) }}</td>
                                    <td class="px-3 py-2 text-right text-xs space-x-2">
                                        @if ($exp->receipt_path)
                                            <a href="{{ asset($exp->receipt_path) }}" target="_blank" class="text-neutral-700 hover:underline">Receipt</a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.billing.expenses.destroy', $exp) }}" class="inline" onsubmit="return confirm('Delete this expense?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $expenses->links() }}</div>
            @endif
        </div>
    </div>
@endsection
