@extends('layouts.admin-dashboard')

@section('title', 'Dashboard')

@php
    $cur = \App\Models\Setting::currencySymbol();
    $cardMap = collect($cardPreferences ?? [])->keyBy('key');
    $isCardVisible = fn(string $k) => (bool) ($cardMap->get($k)['visible'] ?? true);
@endphp

@section('content')
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
            <p class="text-xs text-neutral-500">Live sales performance, collected revenue, and order intelligence.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="document.getElementById('customize-admin-modal').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                Customize Layout
            </button>
            <div class="text-xs text-neutral-400">
                Updated {{ now()->format('M d, Y H:i') }}
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Financial Metrics Cards --}}
    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @if ($isCardVisible('verified_revenue'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Total Verified Revenue</div>
                <div class="mt-2 text-xl sm:text-2xl font-bold tabular-nums text-emerald-600 break-words">{{ $cur }}{{ $stats['verified_revenue'] }}</div>
                <div class="mt-1 text-xs text-neutral-400">Cleared & verified collections</div>
            </div>
        @endif

        @if ($isCardVisible('pending_collections'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Pending Collections</div>
                <div class="mt-2 text-xl sm:text-2xl font-bold tabular-nums text-amber-600 break-words">{{ $cur }}{{ $stats['pending_collections'] }}</div>
                <div class="mt-1 text-xs text-neutral-400">Outstanding closing balance</div>
            </div>
        @endif

        @if ($isCardVisible('total_closing_value'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Total Closing Value</div>
                <div class="mt-2 text-xl sm:text-2xl font-bold tabular-nums text-neutral-900 break-words">{{ $cur }}{{ $stats['total_closing_value'] }}</div>
                <div class="mt-1 text-xs text-neutral-400">Agreed order deal values</div>
            </div>
        @endif

        @if ($isCardVisible('total_discounts'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Total Discounts</div>
                <div class="mt-2 text-xl sm:text-2xl font-bold tabular-nums text-neutral-700 break-words">{{ $cur }}{{ $stats['total_discounts'] }}</div>
                <div class="mt-1 text-xs text-neutral-400">Catalog price concessions</div>
            </div>
        @endif
    </div>

    {{-- Operational Counts Grid --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3 xl:grid-cols-6">
        @if ($isCardVisible('orders_total'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xl sm:text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['orders_total'] }}</div>
                <div class="mt-1 text-xs sm:text-sm text-neutral-500">Total orders</div>
            </div>
        @endif
        @if ($isCardVisible('paying_customers'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xl sm:text-2xl font-semibold tabular-nums tracking-tight text-neutral-900">{{ $stats['paying_customers'] }}</div>
                <div class="mt-1 text-xs sm:text-sm text-neutral-500">Paying customers</div>
            </div>
        @endif
        @if ($isCardVisible('active_subscriptions'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xl sm:text-2xl font-semibold tabular-nums tracking-tight text-blue-600">{{ $stats['active_subscriptions'] }}</div>
                <div class="mt-1 text-xs sm:text-sm text-neutral-500">Active subscriptions</div>
            </div>
        @endif
        @if ($isCardVisible('expired_subscriptions'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xl sm:text-2xl font-semibold tabular-nums tracking-tight text-neutral-500">{{ $stats['expired_subscriptions'] }}</div>
                <div class="mt-1 text-xs sm:text-sm text-neutral-500">Expired subscriptions</div>
            </div>
        @endif
        @if ($isCardVisible('users_total'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xl sm:text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['users_total'] }}</div>
                <div class="mt-1 text-xs sm:text-sm text-neutral-500">Employees</div>
            </div>
        @endif
        @if ($isCardVisible('teams_total'))
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="text-xl sm:text-2xl font-semibold tabular-nums tracking-tight">{{ $stats['teams_total'] }}</div>
                <div class="mt-1 text-xs sm:text-sm text-neutral-500">Teams</div>
            </div>
        @endif
    </div>

    {{-- Monthly Revenue Trend --}}
    @if ($isCardVisible('monthly_trend') && !empty($monthlyReport))
        <div class="mt-8">
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-700">Monthly Revenue & Sales Trend</h2>
                    <p class="text-xs text-neutral-400">Verified receipts and active subscriptions over the last 6 months.</p>
                </div>
            </div>
            <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                <table class="w-full min-w-[500px] text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50 whitespace-nowrap">
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3 text-right">Orders</th>
                            <th class="px-4 py-3 text-right">Active Subs</th>
                            <th class="px-4 py-3 text-right">Verified Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyReport as $m)
                            <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50 whitespace-nowrap">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $m['label'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $m['orders'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $m['subscriptions'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">{{ $cur }}{{ $m['revenue'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Dynamic Product Analytics --}}
    <div class="mt-8">
        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-neutral-700">Dynamic Product Analytics</h2>
                <p class="text-xs text-neutral-400">Real-time catalog breakdown based on actual order snapshots.</p>
            </div>
            <a href="{{ route('admin.products') }}" class="text-xs font-medium text-neutral-600 hover:text-neutral-900 underline">Manage products →</a>
        </div>
        <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="w-full min-w-[620px] text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50 whitespace-nowrap">
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Orders</th>
                        <th class="px-4 py-3 text-right">Collected Revenue</th>
                        <th class="px-4 py-3 text-right">Active Subs</th>
                        <th class="px-4 py-3 text-right">Active Customers</th>
                        <th class="px-4 py-3 text-right">Pending Collection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productReport as $row)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50 whitespace-nowrap">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $row['product'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $row['orders'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">{{ $cur }}{{ $row['collected_revenue'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['active_subscriptions'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['active_customers'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ $cur }}{{ $row['pending_collection'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-400">No products configured yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Team Performance Summary --}}
    @if (!empty($teamReport))
        <div class="mt-8">
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-700">Team Performance & Collections</h2>
                    <p class="text-xs text-neutral-400">Revenue attribution and order volume across sales teams.</p>
                </div>
                <a href="{{ route('admin.teams') }}" class="text-xs font-medium text-neutral-600 hover:text-neutral-900 underline">Manage teams →</a>
            </div>
            <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                <table class="w-full min-w-[660px] text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50 whitespace-nowrap">
                            <th class="px-4 py-3">Team</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3 text-right">Members</th>
                            <th class="px-4 py-3 text-right">Orders</th>
                            <th class="px-4 py-3 text-right">Closing Value</th>
                            <th class="px-4 py-3 text-right">Verified Revenue</th>
                            <th class="px-4 py-3 text-right">Pending Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamReport as $t)
                            <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50 whitespace-nowrap">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $t['team_name'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $t['team_code'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $t['members_count'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $t['orders'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $cur }}{{ $t['closing_value'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">{{ $cur }}{{ $t['revenue'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-neutral-500">{{ $cur }}{{ $t['pending_collections'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Recent Orders --}}
    @if ($isCardVisible('recent_orders'))
        <div class="mt-8">
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-sm font-semibold text-neutral-700">Recent orders</h2>
                <a href="{{ route('admin.orders') }}" class="text-xs font-medium text-neutral-600 hover:text-neutral-900 underline">View all orders →</a>
            </div>
            <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                <table class="w-full min-w-[520px] text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50 whitespace-nowrap">
                            <th class="px-4 py-3">Order #</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Team</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50 whitespace-nowrap">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-xs text-neutral-900 hover:text-neutral-500 underline">
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
                                <td class="px-4 py-3 text-right tabular-nums font-medium">{{ $cur }}{{ number_format((float) $order->closing_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-neutral-400">No orders yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- System Users Section --}}
    @if ($isCardVisible('recent_users'))
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 text-sm font-semibold text-neutral-700">Recent employees</h2>
                <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <table class="w-full min-w-[360px] text-sm">
                        <thead>
                            <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50 whitespace-nowrap">
                                <th class="px-4 py-3">Username</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentUsers as $user)
                                <tr class="border-b border-neutral-100 last:border-0 whitespace-nowrap">
                                    <td class="px-4 py-3 font-medium">{{ $user->username ?? '—' }}</td>
                                    <td class="px-4 py-3 text-neutral-500">{{ $user->email }}</td>
                                    <td class="px-4 py-3 text-neutral-500">{{ $user->created_at->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-neutral-400">No employees yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="mb-3 text-sm font-semibold text-neutral-700">Administrators</h2>
                <div class="w-full overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <table class="w-full min-w-[360px] text-sm">
                        <thead>
                            <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50 whitespace-nowrap">
                                <th class="px-4 py-3">Username</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentAdmins as $admin)
                                <tr class="border-b border-neutral-100 last:border-0 whitespace-nowrap">
                                    <td class="px-4 py-3 font-medium">{{ $admin->username }}</td>
                                    <td class="px-4 py-3 text-neutral-500">{{ $admin->email }}</td>
                                    <td class="px-4 py-3 text-neutral-500">{{ $admin->created_at?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-neutral-400">No administrators yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Customize Modal --}}
    <div id="customize-admin-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 backdrop-blur-xs hidden p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="text-base font-semibold text-neutral-900">Customize Admin Dashboard Cards</h3>
                <button type="button" onclick="document.getElementById('customize-admin-modal').classList.add('hidden')" class="text-neutral-400 hover:text-neutral-600">
                    ✕
                </button>
            </div>

            <form action="{{ route('admin.dashboard.preferences.save') }}" method="POST" class="mt-4">
                @csrf
                <p class="text-xs text-neutral-500 mb-3">Check cards you want to display on your dashboard.</p>
                <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    @foreach ($cardPreferences ?? \App\Http\Controllers\Admin\DashboardController::DEFAULT_CARDS as $index => $c)
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
                    @endforeach
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-neutral-100 pt-4">
                    <button type="button" onclick="document.getElementById('reset-admin-form').submit()" class="text-xs font-semibold text-red-600 hover:text-red-700">
                        Reset Defaults
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="document.getElementById('customize-admin-modal').classList.add('hidden')" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 hover:bg-neutral-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                            Save Layout
                        </button>
                    </div>
                </div>
            </form>

            <form id="reset-admin-form" action="{{ route('admin.dashboard.preferences.reset') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </div>
@endsection