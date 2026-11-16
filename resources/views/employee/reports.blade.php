@extends('layouts.employee')

@section('title', 'Performance Report')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">Performance Report</h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $viewMode === 'team_data' ? 'Team-wide performance metrics for the granted period.' : 'Your personal sales performance metrics.' }}
            </p>
        </div>
        <div class="inline-flex rounded-lg border bg-neutral-100 p-1 text-xs font-semibold shadow-sm">
            <a href="{{ route('employee.reports', ['view' => 'my_data']) }}"
               class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode !== 'team_data' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-600 hover:text-neutral-900' }}">
                My Data
            </a>
            @if ($hasTeamAccess)
                <a href="{{ route('employee.reports', ['view' => 'team_data']) }}"
                   class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode === 'team_data' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-600 hover:text-neutral-900' }}">
                    Team Data
                </a>
            @else
                <button type="button" disabled class="cursor-not-allowed rounded-md px-3 py-1.5 text-neutral-400">Team Data (Locked)</button>
            @endif
        </div>
    </div>

    @if (session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if ($viewMode === 'team_data' && $activeGrant)
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900">
            <strong>Team Data Window:</strong> {{ $activeGrant->start_date->format('M d, Y') }} &rarr; {{ $activeGrant->end_date->format('M d, Y') }} (View-Only mode).
        </div>
    @endif

    {{-- Key Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">
                {{ $viewMode === 'team_data' ? 'Team Orders' : 'Total Orders' }}
            </p>
            <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $totalOrders }}</p>
            <p class="mt-1 text-[11px] text-neutral-400">{{ $currentMonthOrders }} this month</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">
                {{ $viewMode === 'team_data' ? 'Team Revenue' : 'Closing Value' }}
            </p>
            <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $currencySymbol }}{{ number_format((float) $totalClosingValue, 2) }}</p>
            <p class="mt-1 text-[11px] text-neutral-400">This month: {{ $currencySymbol }}{{ number_format((float) $monthlyClosingValue, 2) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Verified Revenue</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $currencySymbol }}{{ number_format((float) $verifiedRevenue, 2) }}</p>
            <p class="mt-1 text-[11px] text-neutral-400">Admin-verified collections</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Unique Customers</p>
            <p class="mt-2 text-2xl font-bold text-neutral-900">{{ $customersCount }}</p>
            <p class="mt-1 text-[11px] text-neutral-400">Distinct clients</p>
        </div>
    </div>

    {{-- Monthly Breakdown --}}
    <div class="card p-6">
        <h2 class="text-sm font-semibold text-neutral-900 mb-4">Monthly Breakdown (Current Year)</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-neutral-600">
                <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase text-neutral-500 font-semibold">
                    <tr>
                        <th class="px-4 py-2">Month</th>
                        <th class="px-4 py-2">Orders</th>
                        <th class="px-4 py-2">Closing Value</th>
                        <th class="px-4 py-2">Avg Order Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($monthlyBreakdown as $row)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-4 py-2 font-medium text-neutral-900">{{ $row['month'] }}</td>
                            <td class="px-4 py-2">{{ $row['orders'] }}</td>
                            <td class="px-4 py-2">{{ $currencySymbol }}{{ number_format((float) $row['closing_value'], 2) }}</td>
                            <td class="px-4 py-2">{{ $currencySymbol }}{{ number_format((float) $row['avg_order_value'], 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($monthlyBreakdown->isEmpty())
                        <tr><td colspan="4" class="px-4 py-6 text-center text-neutral-400">No data available for this period.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
