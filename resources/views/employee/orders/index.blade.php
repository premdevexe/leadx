@extends('layouts.employee')

@section('title', $viewMode === 'team_data' ? 'Team Orders' : 'My Orders')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">
                {{ $viewMode === 'team_data' ? 'Team Orders (View-Only)' : 'My Orders' }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $viewMode === 'team_data' ? 'Orders placed across your team within granted window.' : 'All orders assigned to you.' }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Toggle --}}
            <div class="inline-flex rounded-lg border bg-neutral-100 p-1 text-xs font-semibold shadow-sm">
                <a href="{{ route('employee.orders', ['view' => 'my_data']) }}"
                   class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode !== 'team_data' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-600 hover:text-neutral-900' }}">
                    My Data
                </a>
                @if ($hasTeamAccess)
                    <a href="{{ route('employee.orders', ['view' => 'team_data']) }}"
                       class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode === 'team_data' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-600 hover:text-neutral-900' }}">
                        Team Data
                    </a>
                @else
                    <button type="button" disabled class="cursor-not-allowed rounded-md px-3 py-1.5 text-neutral-400">
                        Team Data (Locked)
                    </button>
                @endif
            </div>

            @if ($viewMode !== 'team_data')
                @canEmployee('orders.create')
                    <a href="{{ route('employee.orders.create') }}" class="btn btn-primary btn-sm">+ Create Order</a>
                @endcanEmployee
            @endif
        </div>
    </div>

    @if (session('warning'))
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if ($viewMode === 'team_data' && $activeGrant)
        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900 flex items-center justify-between">
            <span>
                <strong>Team Data Window:</strong> {{ $activeGrant->start_date->format('M d, Y') }} &rarr; {{ $activeGrant->end_date->format('M d, Y') }} (View-Only mode).
            </span>
        </div>
    @endif

    <div class="mt-5 overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500 font-semibold">
                <tr>
                    <th class="px-4 py-2.5">Order</th>
                    <th class="px-4 py-2.5">Customer</th>
                    @if ($viewMode === 'team_data')
                        <th class="px-4 py-2.5">Employee</th>
                    @endif
                    <th class="px-4 py-2.5">Closing Value</th>
                    <th class="px-4 py-2.5">Payment</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 text-xs">
                @forelse ($orders as $order)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 font-mono font-medium">
                            <a href="{{ route('employee.orders.show', $order) }}" class="text-neutral-900 hover:underline">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-neutral-800">{{ $order->customer_name }}</td>
                        @if ($viewMode === 'team_data')
                            <td class="px-4 py-3 text-neutral-600">{{ $order->assignedEmployee?->name ?? 'Unassigned' }}</td>
                        @endif
                        <td class="px-4 py-3 font-medium text-neutral-900">${{ number_format((float) $order->closing_value, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold
                                {{ $order->payment_status === 'paid' ? 'badge-success' : 'badge-warning' }}">
                                {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold badge-gray">
                                {{ ucfirst($order->order_status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $viewMode === 'team_data' ? 6 : 5 }}" class="px-4 py-8 text-center text-neutral-400">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
