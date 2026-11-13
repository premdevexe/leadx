@extends('layouts.admin-dashboard')

@section('title', 'Customer: '.$customer->name)

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ $customer->name }}</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Customer profile · Team {{ $customer->team?->team_code ?? '—' }} · Member since {{ $customer->created_at->format('M d, Y') }}
            </p>
        </div>
        <a href="{{ route('admin.customers') }}" class="text-sm text-neutral-600 hover:text-neutral-900">← All customers</a>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
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
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
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
                    <dd class="tabular-nums font-semibold text-emerald-700">{{ number_format((float) $customer->total_purchased, 2) }}</dd>
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
                <div class="mt-3 overflow-hidden rounded-lg border border-neutral-200">
                    <table class="w-full text-xs">
                        <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Product / Plan</th>
                                <th class="px-3 py-2 text-right">Price</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Started</th>
                                <th class="px-3 py-2 text-left">Expires</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($customer->subscriptions as $sub)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-neutral-900">
                                        {{ $sub->product?->name ?? $sub->plan_snapshot ?? 'Standard Product' }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ number_format((float) ($sub->price_snapshot ?? $sub->amount_paid), 2) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $sub->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                                            {{ ucfirst($sub->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-neutral-500">{{ $sub->started_at ? $sub->started_at->toDateString() : '—' }}</td>
                                    <td class="px-3 py-2 font-medium {{ $sub->isActive() ? 'text-neutral-900' : 'text-neutral-400' }}">
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
                <div class="mt-3 overflow-hidden rounded-lg border border-neutral-200">
                    <table class="w-full text-xs">
                        <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Order #</th>
                                <th class="px-3 py-2 text-left">Product Snapshot</th>
                                <th class="px-3 py-2 text-right">Closing Value</th>
                                <th class="px-3 py-2 text-right">Received</th>
                                <th class="px-3 py-2 text-left">Payment</th>
                                <th class="px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
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
                                        {{ number_format((float) $order->closing_value, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-emerald-700">
                                        {{ number_format((float) $order->amount_received, 2) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="rounded px-1.5 py-0.5 text-[10px] {{ $order->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600' }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('admin.orders.show', $order) }}" class="text-[11px] text-neutral-600 hover:text-neutral-900">
                                            View Order →
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
@endsection
