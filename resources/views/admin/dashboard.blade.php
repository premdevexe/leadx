@extends('layouts.admin-dashboard')

@section('title', 'Dashboard')

@php
    $teamsTotal = \App\Models\Team::count();
    $recentOrders = \App\Models\SalesOrder::with('team')->latest()->take(5)->get();
@endphp

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>

    <div class="mt-5 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['users_total'] }}</div>
            <div class="mt-1 text-sm text-neutral-500">Employees</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="text-2xl font-semibold tabular-nums tracking-tight">{{ $teamsTotal }}</div>
            <div class="mt-1 text-sm text-neutral-500">Teams</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['orders_total'] }}</div>
            <div class="mt-1 text-sm text-neutral-500">Orders</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['orders_active'] }}</div>
            <div class="mt-1 text-sm text-neutral-500">Active subscriptions</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['users_last_7_days'] }}</div>
            <div class="mt-1 text-sm text-neutral-500">New in the last 7 days</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['admins_total'] }}</div>
            <div class="mt-1 text-sm text-neutral-500">Administrators</div>
        </div>
    </div>

    <div class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-neutral-500">Recent orders</h2>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">View all →</a>
        </div>
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                        <th class="px-4 py-3">Order #</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Team</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Closing</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-xs text-neutral-900 hover:text-neutral-500">
                                    {{ $order->order_number ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $order->customer_name }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $order->team?->team_code ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600">
                                    {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $order->closing_value, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-neutral-400">No orders yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold text-neutral-500">Recent employees</h2>
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentUsers as $user)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="px-4 py-3">{{ $user->username ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $user->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-neutral-400">No employees yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold text-neutral-500">Administrators</h2>
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Added</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentAdmins as $admin)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="px-4 py-3">{{ $admin->username }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $admin->email }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $admin->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-neutral-400">No administrators yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
