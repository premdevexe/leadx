@extends('layouts.employee')

@section('title', $viewMode === 'team_data' ? 'Team Customers' : 'My Customers')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">
                {{ $viewMode === 'team_data' ? 'Team Customers (View-Only)' : 'Customers (CRM)' }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $viewMode === 'team_data' ? 'Customers from orders across your team within the granted window.' : 'Customers associated with your orders.' }}
            </p>
        </div>

        <div class="inline-flex rounded-lg border bg-neutral-100 p-1 text-xs font-semibold shadow-sm">
            <a href="{{ route('employee.customers', ['view' => 'my_data']) }}"
               class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode !== 'team_data' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-600 hover:text-neutral-900' }}">
                My Data
            </a>
            @if ($hasTeamAccess)
                <a href="{{ route('employee.customers', ['view' => 'team_data']) }}"
                   class="rounded-md px-3 py-1.5 transition-colors {{ $viewMode === 'team_data' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-600 hover:text-neutral-900' }}">
                    Team Data
                </a>
            @else
                <button type="button" disabled class="cursor-not-allowed rounded-md px-3 py-1.5 text-neutral-400">Team Data (Locked)</button>
            @endif
        </div>
    </div>

    @if (session('warning'))
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if ($viewMode === 'team_data' && $activeGrant)
        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900">
            <strong>Team Data Window:</strong> {{ $activeGrant->start_date->format('M d, Y') }} &rarr; {{ $activeGrant->end_date->format('M d, Y') }} (View-Only mode).
        </div>
    @endif

    <div class="mt-5 overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500 font-semibold">
                <tr>
                    <th class="px-4 py-2.5">Name</th>
                    <th class="px-4 py-2.5">Phone</th>
                    <th class="px-4 py-2.5">Email</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 text-xs">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 font-medium text-neutral-900">{{ $customer->name }}</td>
                        <td class="px-4 py-3">{{ $customer->phone }}</td>
                        <td class="px-4 py-3">{{ $customer->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold
                                {{ $customer->status === 'active' ? 'badge-success' : 'badge-gray' }}">
                                {{ ucfirst($customer->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-neutral-400">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
