@extends('layouts.admin-dashboard')

@section('title', 'Customers (CRM)')

@php
    $f = $filters ?? ['search' => null, 'team_id' => null];
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Customers (CRM)</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Verified paying customers promoted through completed, verified purchases.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.customers') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-neutral-500">Search customer</label>
                <input type="text" name="search" value="{{ $f['search'] ?? '' }}"
                       placeholder="Name, phone, or email…"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-500">Team</label>
                <select name="team_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">All teams</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected(($f['team_id'] ?? null) === $team->id)>
                            {{ $team->team_code }} — {{ $team->team_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
                    Filter
                </button>
                @if (!empty($f['search']) || !empty($f['team_id']))
                    <a href="{{ route('admin.customers') }}" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if ($customers->isEmpty())
        <div class="mt-5 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            @if (!empty($f['search']) || !empty($f['team_id']))
                No paying customers match the current filter. <a href="{{ route('admin.customers') }}" class="text-neutral-900 underline">Reset filters</a>
            @else
                No verified paying customers recorded yet. Customers appear here automatically once purchases are verified.
            @endif
        </div>
    @else
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Team</th>
                        <th class="px-4 py-3 text-right">Purchases</th>
                        <th class="px-4 py-3 text-right">Total Spent</th>
                        <th class="px-4 py-3 text-right">Last Purchase</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50">
                            <td class="px-4 py-3 font-medium text-neutral-900">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="hover:underline">
                                    {{ $customer->name }}
                                </a>
                                <span class="ml-1 inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">
                                    Paying
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-neutral-500">
                                {{ $customer->phone ?? '—' }}
                                @if ($customer->email)
                                    <br><span class="text-neutral-400">{{ $customer->email }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ $customer->team?->team_code ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-700">
                                {{ $customer->purchases_count }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">
                                {{ $cur }}{{ number_format((float) $customer->total_purchased, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-neutral-500">
                                {{ $customer->last_purchase_at ? $customer->last_purchase_at->format('M d, Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="text-xs font-medium text-neutral-900 hover:underline">
                                    View Details →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="mt-4">{{ $customers->links() }}</div>
        @endif
    @endif
@endsection
