@extends('layouts.admin-dashboard')

@section('title', 'Recurring Billing & Renewals')

@php
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Recurring Billing &amp; Renewals</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Track monthly, quarterly, half-yearly, and annual subscriptions with duplicate-safe renewal invoicing.</p>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.billing.recurring.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ empty($filters['filter']) ? 'bg-neutral-900 text-white' : 'bg-white border border-neutral-200 text-neutral-700' }}">All Subscriptions</a>
        <a href="{{ route('admin.billing.recurring.index', ['filter' => 'due']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ ($filters['filter'] ?? '') === 'due' ? 'bg-neutral-900 text-white' : 'bg-white border border-neutral-200 text-neutral-700' }}">Due for Renewal</a>
        <a href="{{ route('admin.billing.recurring.index', ['filter' => 'expiring']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ ($filters['filter'] ?? '') === 'expiring' ? 'bg-neutral-900 text-white' : 'bg-white border border-neutral-200 text-neutral-700' }}">Expiring in 14 Days</a>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 bg-white overflow-hidden">
        @if ($subscriptions->isEmpty())
            <p class="p-8 text-center text-sm text-neutral-400">No subscriptions found.</p>
        @else
            <div class="table-wrapper">
                <table class="data-table w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Plan / Product</th>
                            <th class="px-4 py-3 text-left">Cycle</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Renewal Date</th>
                            <th class="px-4 py-3 text-left">Expires</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subscriptions as $sub)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ $sub->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-neutral-700">{{ $sub->product?->name ?? $sub->plan_snapshot ?? 'Subscription' }}</td>
                                <td class="px-4 py-3 text-xs">{{ \App\Models\Invoice::BILLING_CYCLES[$sub->billing_cycle] ?? ucfirst($sub->billing_cycle ?? 'yearly') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-xs font-semibold">{{ $cur }}{{ number_format((float) ($sub->price_snapshot ?? $sub->amount_paid), 2) }}</td>
                                <td class="px-4 py-3 text-xs text-neutral-600">{{ $sub->renewal_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-neutral-600">{{ $sub->expires_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs"><span class="badge {{ $sub->status === 'active' ? 'badge-success' : 'badge-neutral' }}">{{ ucfirst($sub->status) }}</span></td>
                                <td class="px-4 py-3 text-right text-xs">
                                    @if ($sub->customer_id)
                                        <form method="POST" action="{{ route('admin.billing.recurring.generate', $sub) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary text-xs py-1 px-2.5">Generate Renewal Invoice</button>
                                        </form>
                                    @else
                                        <span class="text-neutral-400">No customer</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-neutral-100">{{ $subscriptions->links() }}</div>
        @endif
    </div>
@endsection
