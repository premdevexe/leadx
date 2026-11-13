@extends('layouts.admin-dashboard')

@section('title', 'Sales Analytics & Reports')

@section('content')
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Sales Analytics & Reports</h1>
            <p class="text-xs text-neutral-500">Authoritative multi-dimensional sales, revenue, and product performance.</p>
        </div>
        <div class="text-xs text-neutral-400">
            Updated {{ now()->format('M d, Y H:i') }}
        </div>
    </div>

    {{-- Interactive Filter Toolbar --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="block text-xs font-medium text-neutral-500">Date Range</label>
                <select name="range" id="filter-range" class="mt-1 w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                    <option value="">All Time</option>
                    <option value="today" @selected(($filters['range'] ?? '') === 'today')>Today</option>
                    <option value="last_7_days" @selected(($filters['range'] ?? '') === 'last_7_days')>Last 7 Days</option>
                    <option value="last_30_days" @selected(($filters['range'] ?? '') === 'last_30_days')>Last 30 Days</option>
                    <option value="this_month" @selected(($filters['range'] ?? '') === 'this_month')>This Month</option>
                    <option value="previous_month" @selected(($filters['range'] ?? '') === 'previous_month')>Previous Month</option>
                    <option value="custom" @selected(($filters['range'] ?? '') === 'custom' || !empty($filters['start_date']))>Custom Range</option>
                </select>
            </div>

            <div id="custom-start-col" class="{{ ($filters['range'] ?? '') === 'custom' || !empty($filters['start_date']) ? '' : 'hidden' }}">
                <label class="block text-xs font-medium text-neutral-500">Start Date</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1 text-xs focus:border-neutral-500 focus:outline-none">
            </div>

            <div id="custom-end-col" class="{{ ($filters['range'] ?? '') === 'custom' || !empty($filters['start_date']) ? '' : 'hidden' }}">
                <label class="block text-xs font-medium text-neutral-500">End Date</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1 text-xs focus:border-neutral-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Product</label>
                <select name="product_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                    <option value="">All Products</option>
                    @foreach ($catalogProducts as $cp)
                        <option value="{{ $cp->id }}" @selected((string)($filters['product_id'] ?? '') === (string)$cp->id)>{{ $cp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Team</label>
                <select name="team_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                    <option value="">All Teams</option>
                    @foreach ($allTeams as $tm)
                        <option value="{{ $tm->id }}" @selected((string)($filters['team_id'] ?? '') === (string)$tm->id)>{{ $tm->team_code }} - {{ $tm->team_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Employee</label>
                <select name="employee_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                    <option value="">All Employees</option>
                    @foreach ($allEmployees as $em)
                        <option value="{{ $em->id }}" @selected((string)($filters['employee_id'] ?? '') === (string)$em->id)>{{ $em->username }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Payment Status</label>
                <select name="payment_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs focus:border-neutral-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    <option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>Paid</option>
                    <option value="partially_paid" @selected(($filters['payment_status'] ?? '') === 'partially_paid')>Partially Paid</option>
                    <option value="pending" @selected(($filters['payment_status'] ?? '') === 'pending')>Pending</option>
                    <option value="failed" @selected(($filters['payment_status'] ?? '') === 'failed')>Failed</option>
                    <option value="refunded" @selected(($filters['payment_status'] ?? '') === 'refunded')>Refunded</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-neutral-100 pt-3">
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-neutral-900 px-3.5 py-1.5 text-xs font-medium text-white hover:bg-neutral-800 transition">
                    Apply Filters
                </button>
                <a href="{{ route('admin.reports.index', ['tab' => $tab]) }}" class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50 transition">
                    Reset
                </a>
            </div>
            <div class="text-xs text-neutral-400">
                Found {{ $overview['total_orders'] }} matching order(s)
            </div>
        </div>
    </form>

    {{-- Navigation Tabs --}}
    <div class="mt-6 flex border-b border-neutral-200 text-sm font-medium">
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'overview']) }}"
           class="border-b-2 px-4 py-2.5 transition-colors {{ $tab === 'overview' ? 'border-neutral-900 text-neutral-900 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}">
            Overview & Revenue
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'products']) }}"
           class="border-b-2 px-4 py-2.5 transition-colors {{ $tab === 'products' ? 'border-neutral-900 text-neutral-900 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}">
            Product-wise
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'employees']) }}"
           class="border-b-2 px-4 py-2.5 transition-colors {{ $tab === 'employees' ? 'border-neutral-900 text-neutral-900 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}">
            Employee Performance
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'teams']) }}"
           class="border-b-2 px-4 py-2.5 transition-colors {{ $tab === 'teams' ? 'border-neutral-900 text-neutral-900 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}">
            Team Analytics
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'orders']) }}"
           class="border-b-2 px-4 py-2.5 transition-colors {{ $tab === 'orders' ? 'border-neutral-900 text-neutral-900 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}">
            Orders Ledger
        </a>
    </div>

    {{-- Tab 1: Overview & Revenue --}}
    @if ($tab === 'overview')
        <div class="mt-5 space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Verified Revenue</div>
                    <div class="mt-2 text-2xl font-bold tabular-nums text-emerald-600">${{ $overview['verified_revenue'] }}</div>
                    <div class="mt-1 text-xs text-neutral-400">Actual collected & cleared</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Pending Collections</div>
                    <div class="mt-2 text-2xl font-bold tabular-nums text-amber-600">${{ $overview['pending_collections'] }}</div>
                    <div class="mt-1 text-xs text-neutral-400">Outstanding ledger balance</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Total Closing Value</div>
                    <div class="mt-2 text-2xl font-bold tabular-nums text-neutral-900">${{ $overview['total_closing_value'] }}</div>
                    <div class="mt-1 text-xs text-neutral-400">Agreed contract values</div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Discounts & Concessions</div>
                    <div class="mt-2 text-2xl font-bold tabular-nums text-neutral-700">${{ $overview['total_discounts'] }}</div>
                    <div class="mt-1 text-xs text-neutral-400">Catalog list vs closing</div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-neutral-700">Financial Ledger Breakdown</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                    <div class="rounded-lg bg-neutral-50 p-3">
                        <dt class="text-xs text-neutral-500">Gross Product Value</dt>
                        <dd class="mt-1 text-lg font-semibold text-neutral-900">${{ $revenue['product_value'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-neutral-50 p-3">
                        <dt class="text-xs text-neutral-500">Total Closing Value</dt>
                        <dd class="mt-1 text-lg font-semibold text-neutral-900">${{ $revenue['closing_value'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-neutral-50 p-3">
                        <dt class="text-xs text-neutral-500">Total Discounts</dt>
                        <dd class="mt-1 text-lg font-semibold text-neutral-700">${{ $revenue['discounts'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-emerald-50/50 p-3">
                        <dt class="text-xs text-emerald-700">Collected Amount Received</dt>
                        <dd class="mt-1 text-lg font-bold text-emerald-700">${{ $revenue['amount_received'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-amber-50/50 p-3">
                        <dt class="text-xs text-amber-700">Pending Uncollected Balance</dt>
                        <dd class="mt-1 text-lg font-bold text-amber-700">${{ $revenue['pending_amount'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-red-50/50 p-3">
                        <dt class="text-xs text-red-700">Deducted Refunds</dt>
                        <dd class="mt-1 text-lg font-semibold text-red-700">${{ $revenue['refunds'] }}</dd>
                    </div>
                </dl>

                @if (!empty($revenue['by_payment_method']))
                    <div class="mt-6 border-t border-neutral-100 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Collections by Payment Method</h4>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                            @foreach ($revenue['by_payment_method'] as $method => $amount)
                                <div class="rounded-lg border border-neutral-200 p-3">
                                    <div class="text-xs text-neutral-500 capitalize">{{ str_replace('_', ' ', $method) }}</div>
                                    <div class="mt-1 text-base font-semibold text-neutral-900">${{ $amount }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Tab 2: Products --}}
    @if ($tab === 'products')
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50">
                        <th class="px-4 py-3">Product Name</th>
                        <th class="px-4 py-3 text-right">Orders</th>
                        <th class="px-4 py-3 text-right">Collected Revenue</th>
                        <th class="px-4 py-3 text-right">Active Subs</th>
                        <th class="px-4 py-3 text-right">Active Customers</th>
                        <th class="px-4 py-3 text-right">Pending Collection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $row)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $row['product'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $row['orders'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">${{ $row['collected_revenue'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['active_subscriptions'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['active_customers'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">${{ $row['pending_collection'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-neutral-400">No products found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Tab 3: Employees --}}
    @if ($tab === 'employees')
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50">
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Team</th>
                        <th class="px-4 py-3 text-right">Orders</th>
                        <th class="px-4 py-3 text-right">Closing Value</th>
                        <th class="px-4 py-3 text-right">Verified Revenue</th>
                        <th class="px-4 py-3 text-right">Pending Collections</th>
                        <th class="px-4 py-3 text-right">Active Subs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $row)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50">
                            <td class="px-4 py-3">
                                <span class="font-medium text-neutral-900">{{ $row['employee_name'] }}</span>
                                <span class="text-xs text-neutral-400">({{ $row['username'] }})</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $row['team_code'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $row['total_orders'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">${{ $row['closing_value'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">${{ $row['verified_revenue'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">${{ $row['pending_collections'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['active_subscriptions'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-neutral-400">No employees found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Tab 4: Teams --}}
    @if ($tab === 'teams')
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50">
                        <th class="px-4 py-3">Team</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3 text-right">Members</th>
                        <th class="px-4 py-3 text-right">Orders</th>
                        <th class="px-4 py-3 text-right">Closing Value</th>
                        <th class="px-4 py-3 text-right">Verified Revenue</th>
                        <th class="px-4 py-3 text-right">Pending Balance</th>
                        <th class="px-4 py-3 text-right">Active Subs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teams as $row)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50">
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ $row['team_name'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $row['team_code'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $row['members_count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600">{{ $row['orders'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">${{ $row['closing_value'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">${{ $row['revenue'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">${{ $row['pending_collections'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $row['active_subscriptions'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-neutral-400">No teams found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Tab 5: Orders Ledger --}}
    @if ($tab === 'orders')
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500 bg-neutral-50">
                        <th class="px-4 py-3">Order #</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Team</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Closing</th>
                        <th class="px-4 py-3 text-right">Verified Paid</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Payment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50">
                            <td class="px-4 py-3 font-mono text-xs">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-neutral-900 hover:text-neutral-500 underline">
                                    {{ $order->order_number ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $order->customer_name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $order->team_code_snapshot }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $order->product_name ?? $order->product_name_snapshot ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">${{ number_format((float)$order->closing_value, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">${{ $order->verifiedPaymentTotal() }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600">
                                    {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $order->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($order->payment_status === 'partially_paid' ? 'bg-blue-50 text-blue-700' : 'bg-neutral-100 text-neutral-600') }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-neutral-400">No orders match the selected criteria</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        @endif
    @endif

    <script>
        (function () {
            const rangeSelect = document.getElementById('filter-range');
            const startCol = document.getElementById('custom-start-col');
            const endCol = document.getElementById('custom-end-col');

            if (!rangeSelect) return;

            rangeSelect.addEventListener('change', function () {
                if (rangeSelect.value === 'custom') {
                    startCol.classList.remove('hidden');
                    endCol.classList.remove('hidden');
                } else {
                    startCol.classList.add('hidden');
                    endCol.classList.add('hidden');
                }
            });
        })();
    </script>
@endsection
