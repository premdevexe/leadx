@extends('layouts.employee')

@section('title', 'Employee Dashboard')

@section('content')
<div class="space-y-6">
    {{-- Top Bar & Toggle --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900">
                {{ $viewMode === 'team_data' ? 'Team Performance Dashboard' : 'My Sales Dashboard' }}
            </h1>
            <p class="text-sm text-neutral-500">
                {{ $employee->name }} ({{ $employee->username }}) &bull; Team: <span class="font-medium text-neutral-700">{{ $employee->team?->team_name ?? 'Unassigned' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Customize Button --}}
            <button type="button" onclick="document.getElementById('customize-employee-modal').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                Customize Cards
            </button>

            {{-- Top-Level Scope Toggle: My Data | Team Data --}}
            <div class="inline-flex rounded-lg border border-neutral-200 bg-neutral-100 p-1 text-xs font-semibold shadow-xs">
                <a href="{{ route('employee.dashboard', ['view' => 'my_data']) }}"
                   class="rounded-md px-4 py-1.5 transition-colors {{ $viewMode !== 'team_data' ? 'bg-white text-neutral-900 shadow-xs' : 'text-neutral-600 hover:text-neutral-900' }}">
                    My Data
                </a>
                @if ($hasTeamAccess)
                    <a href="{{ route('employee.dashboard', ['view' => 'team_data']) }}"
                       class="rounded-md px-4 py-1.5 transition-colors {{ $viewMode === 'team_data' ? 'bg-white text-neutral-900 shadow-xs' : 'text-neutral-600 hover:text-neutral-900' }}">
                        Team Data
                    </a>
                @else
                    <button type="button" disabled title="Requires historical date window grant from Super Admin"
                            class="cursor-not-allowed rounded-md px-4 py-1.5 text-neutral-400 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        Team Data (Locked)
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    {{-- Team Data View-Only Notice --}}
    @if ($viewMode === 'team_data' && $activeGrant)
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 text-xs text-indigo-900 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                    <strong>Team Data Mode (View-Only):</strong> Displaying aggregate team metrics for historical window
                    <span class="font-semibold">{{ $activeGrant->start_date->format('M d, Y') }} &rarr; {{ $activeGrant->end_date->format('M d, Y') }}</span>.
                    Creation, editing, approvals, and deletions are strictly disabled.
                </span>
            </div>
            <a href="{{ route('employee.dashboard', ['view' => 'my_data']) }}" class="text-indigo-700 underline font-medium hover:text-indigo-900">
                Switch to My Data &rarr;
            </a>
        </div>
    @endif

    {{-- 9 Metric Cards Grid (Rendered according to saved order and visibility) --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cardPreferences as $card)
            @if ($card['visible'])
                @canCard($card['key'])
                @switch($card['key'])
                    @case('orders_count')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ $viewMode === 'team_data' ? 'Team Total Orders' : 'My Total Orders' }}</p>
                            <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $totalOrders }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Total volume recorded</p>
                        </div>
                        @break

                    @case('sales_value')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ $viewMode === 'team_data' ? 'Team Sales Value' : 'My Sales / Closing Value' }}</p>
                            <p class="mt-2 text-2xl font-bold text-neutral-900">${{ $closingValue }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Agreed deal values</p>
                        </div>
                        @break

                    @case('verified_revenue')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ $viewMode === 'team_data' ? 'Team Verified Revenue' : 'My Verified Revenue' }}</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-600">${{ $verifiedRevenue }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Cleared & verified collections</p>
                        </div>
                        @break

                    @case('customers_count')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ $viewMode === 'team_data' ? 'Team Customers' : 'My Customers' }}</p>
                            <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $customersCount }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Unique client accounts</p>
                        </div>
                        @break

                    @case('active_subscriptions')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Active Subscriptions</p>
                            <p class="mt-2 text-2xl font-bold text-blue-600">{{ $activeSubscriptions }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Current live plans</p>
                        </div>
                        @break

                    @case('pending_payments')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ $viewMode === 'team_data' ? 'Team Pending Payments' : 'My Pending Payments' }}</p>
                            <p class="mt-2 text-2xl font-bold text-amber-600">{{ $pendingPaymentsCount }} <span class="text-sm font-normal text-neutral-500">(${{ $pendingAmount }})</span></p>
                            <p class="mt-1 text-[11px] text-neutral-400">Outstanding payment collection</p>
                        </div>
                        @break

                    @case('expiring_subscriptions')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Expiring in 30 Days</p>
                            <p class="mt-2 text-2xl font-bold text-red-600">{{ $expiringSubscriptions }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Requires renewal outreach</p>
                        </div>
                        @break

                    @case('follow_ups')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">{{ $viewMode === 'team_data' ? 'Team Follow-ups' : 'My Follow-ups' }}</p>
                            <p class="mt-2 text-2xl font-bold text-purple-600">{{ $followUpsCount }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">Scheduled client tasks</p>
                        </div>
                        @break

                    @case('monthly_performance')
                        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Monthly Performance</p>
                            <p class="mt-2 text-2xl font-bold text-neutral-900">${{ $monthlyClosingValue }}</p>
                            <p class="mt-1 text-[11px] text-neutral-400">{{ $monthlyOrdersCount }} orders this month</p>
                        </div>
                        @break
                @endswitch
                @endcanCard
            @endif
        @endforeach
    </div>

    {{-- Bottom Modules: Recent Orders & Action Links --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3 mb-3">
                <h2 class="text-sm font-semibold text-neutral-900">
                    {{ $viewMode === 'team_data' ? 'Recent Team Orders' : 'My Recent Orders' }}
                </h2>
                <a href="{{ route('employee.orders', ['view' => $viewMode]) }}" class="text-xs text-neutral-700 underline hover:text-neutral-900">
                    View all &rarr;
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-neutral-600">
                    <thead class="border-b border-neutral-100 text-[11px] uppercase tracking-wider text-neutral-400">
                        <tr>
                            <th class="py-2">Order ID</th>
                            <th class="py-2">Product</th>
                            <th class="py-2">Value</th>
                            <th class="py-2">Payment Status</th>
                            <th class="py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="py-2 font-mono text-xs font-semibold text-neutral-900">{{ $order->order_number }}</td>
                                <td class="py-2">{{ $order->product?->name ?? 'Standard Plan' }}</td>
                                <td class="py-2 font-medium text-neutral-900">${{ number_format($order->closing_value, 2) }}</td>
                                <td class="py-2">
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold
                                        {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ ucfirst($order->payment_status) }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('employee.orders.show', $order) }}" class="text-neutral-700 hover:text-neutral-900 font-medium">
                                        View &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-neutral-400">No orders found in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <h2 class="text-sm font-semibold text-neutral-900 mb-2">Portal Actions</h2>
                <p class="text-xs text-neutral-500 mb-4">Quick shortcuts to your daily sales workflow.</p>
                <div class="space-y-2">
                    @if ($viewMode !== 'team_data')
                        @canEmployee('orders.create')
                            <a href="{{ route('employee.orders.create') }}" class="flex items-center justify-center w-full rounded-lg bg-neutral-900 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                                + Create New Order
                            </a>
                        @endcanEmployee
                    @else
                        <div class="rounded-lg bg-neutral-100 py-2.5 px-3 text-center text-xs text-neutral-500 italic">
                            Order Creation disabled in Team Data mode
                        </div>
                    @endif
                    <a href="{{ route('employee.orders') }}" class="flex items-center justify-center w-full rounded-lg border border-neutral-200 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                        View Orders List
                    </a>
                    <a href="{{ route('employee.customers') }}" class="flex items-center justify-center w-full rounded-lg border border-neutral-200 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                        My Customers
                    </a>
                    <a href="{{ route('employee.profile') }}" class="flex items-center justify-center w-full rounded-lg border border-neutral-200 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                        My Profile
                    </a>
                </div>
            </div>
            <div class="pt-4 border-t border-neutral-100 mt-4 text-[11px] text-neutral-400 text-center">
                LeadX Enterprise Sales Management
            </div>
        </div>
    </div>

    {{-- Customize Modal --}}
    <div id="customize-employee-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 backdrop-blur-xs hidden p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="text-base font-semibold text-neutral-900">Customize Dashboard Metric Cards</h3>
                <button type="button" onclick="document.getElementById('customize-employee-modal').classList.add('hidden')" class="text-neutral-400 hover:text-neutral-600">
                    ✕
                </button>
            </div>

            <form action="{{ route('employee.dashboard.preferences.save') }}" method="POST" class="mt-4">
                @csrf
                <p class="text-xs text-neutral-500 mb-3">Check cards you want to display on your sales dashboard.</p>
                <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    @foreach ($cardPreferences as $index => $c)
                        @canCard($c['key'])
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 bg-neutral-50/50 p-2.5">
                                <span class="text-xs font-medium text-neutral-800">{{ $c['label'] }}</span>
                                <div class="flex items-center gap-3">
                                    <input type="hidden" name="cards[{{ $index }}][key]" value="{{ $c['key'] }}">
                                    <input type="hidden" name="cards[{{ $index }}][order]" value="{{ $c['order'] ?? ($index + 1) }}">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" name="cards[{{ $index }}][visible]" value="1" {{ ($c['visible'] ?? true) ? 'checked' : '' }} class="rounded border-neutral-300 text-neutral-900 focus:ring-0">
                                        <span class="text-xs text-neutral-500">Show</span>
                                    </label>
                                </div>
                            </div>
                        @endcanCard
                    @endforeach
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-neutral-100 pt-4">
                    <button type="button" onclick="document.getElementById('reset-employee-form').submit()" class="text-xs font-semibold text-red-600 hover:text-red-700">
                        Reset Defaults
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="document.getElementById('customize-employee-modal').classList.add('hidden')" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 hover:bg-neutral-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                            Save Layout
                        </button>
                    </div>
                </div>
            </form>

            <form id="reset-employee-form" action="{{ route('employee.dashboard.preferences.reset') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </div>
</div>
@endsection
