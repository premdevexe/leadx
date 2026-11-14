@extends('layouts.admin-dashboard')

@section('title', 'Orders')

@php
    $orderStatuses = \App\Models\SalesOrder::STATUSES;
    $paymentStatuses = \App\Models\SalesOrder::PAYMENT_STATUSES;
    $currencySymbol = \App\Models\Setting::currencySymbol();

    $statusColors = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
        'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'expired' => 'bg-neutral-100 text-neutral-600 border-neutral-200',
        'cancelled' => 'bg-neutral-100 text-neutral-400 border-neutral-200',
        'suspended' => 'bg-orange-50 text-orange-700 border-orange-200',
    ];

    $paymentColors = [
        'pending' => 'bg-amber-50 text-amber-700',
        'partially_paid' => 'bg-blue-50 text-blue-700',
        'paid' => 'bg-emerald-50 text-emerald-700',
        'failed' => 'bg-red-50 text-red-700',
        'refunded' => 'bg-neutral-100 text-neutral-500',
    ];

    $f = $filters;
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Orders</h1>
        <a href="{{ route('admin.orders.create') }}"
           class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
            New order
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.orders') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="block text-xs font-medium text-neutral-500">Order number</label>
                <input type="text" name="order_number" value="{{ old('order_number', $f['order_number']) }}"
                       placeholder="GEM-2026" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-500">Team</label>
                <select name="team_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">All teams</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected($f['team_id'] === $team->id)>{{ $team->team_code }} — {{ $team->team_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-500">Employee</label>
                <select name="employee_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($f['employee_id'] === $employee->id)>{{ $employee->username }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-500">Order status</label>
                <select name="order_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">Any</option>
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status }}" @selected($f['order_status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-500">Payment</label>
                <select name="payment_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">Any</option>
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status }}" @selected($f['payment_status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-700">Filter</button>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-500 hover:text-neutral-800">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                    <th class="px-4 py-3">Order #</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Team</th>
                    <th class="px-4 py-3">Employee</th>
                    <th class="px-4 py-3 text-right">Closing</th>
                    <th class="px-4 py-3">Order</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-neutral-900 hover:text-neutral-500">
                                {{ $order->order_number ?? '— (unnumbered)' }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $order->customer_name }}</td>
                        <td class="px-4 py-3">
                            @if ($order->team)
                                <span class="font-mono text-xs text-neutral-600">{{ $order->team_code_snapshot }}</span>
                                <span class="text-neutral-400">· {{ $order->team->team_name }}</span>
                            @else
                                <span class="text-neutral-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-600">{{ $order->assignedEmployee?->username ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $currencySymbol }}{{ number_format((float) $order->closing_value, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full border px-2 py-0.5 text-xs {{ $statusColors[$order->order_status] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200' }}">
                                {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $paymentColors[$order->payment_status] ?? 'bg-neutral-100 text-neutral-600' }}">
                                {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-xs font-medium text-neutral-600 hover:text-neutral-900 underline">View</a>
                                <span class="text-neutral-300">·</span>
                                <a href="{{ route('admin.orders.edit', $order) }}" class="text-xs font-medium text-neutral-900 hover:text-neutral-600 underline">Edit</a>
                                @if (! empty($shareLinks[$order->id]))
                                    <a href="{{ $shareLinks[$order->id] }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                       title="Share this order to WhatsApp (opens prefilled chat on your device)">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.035-.458.115-.609.134-.134.298-.347.448-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.521-.075-.149-.669-1.588-.916-2.181-.242-.579-.486-.5-.669-.51l-.57-.01c-.199 0-.52.074-.792.372-.272.297-1.04 1.015-1.04 2.459 0 1.444 1.039 2.834 1.188 3.033.149.198 2.085 3.203 5.075 4.478.709.3 1.256.48 1.685.61.709.224 1.353.19 1.862.115.57-.085 1.758-.719 2.009-1.415.25-.695.25-1.287.175-1.411-.074-.124-.272-.199-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.89a9.825 9.825 0 0 1 2.893 6.994c-.001 5.45-4.436 9.885-9.886 9.885m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0 0 20.885 3.488Z"/>
                                        </svg>
                                        Share
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-neutral-400">
                            No orders found{{ $orders->total() > 0 ? '' : '' }}. <a href="{{ route('admin.orders.create') }}" class="text-neutral-600 underline">Create one</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($orders->hasPages())
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
