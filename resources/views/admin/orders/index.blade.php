@extends('layouts.admin-dashboard')

@section('title', 'Orders')

@php
    $orderStatuses = \App\Models\SalesOrder::STATUSES;
    $paymentStatuses = \App\Models\SalesOrder::PAYMENT_STATUSES;

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
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $order->closing_value, 2) }}</td>
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-400">
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
