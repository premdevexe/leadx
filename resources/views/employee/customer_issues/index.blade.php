@extends('layouts.employee')

@section('title', 'CRM Customer Issues')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">CRM Customer Issues</h1>
            <p class="mt-1 text-sm text-neutral-500">Track and manage issues reported by customers.</p>
        </div>
        <a href="{{ route('employee.customer-issues.create') }}"
           class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1.5 h-4 w-4 inline">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Report Issue
        </a>
    </div>

    {{-- Summary Cards --}}
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs font-medium text-neutral-500">Total Issues</p>
            <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $totalIssues }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-neutral-500">Fixed Issues</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $fixedIssues }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-neutral-500">Still Facing</p>
            <p class="mt-1 text-2xl font-semibold text-amber-700">{{ $stillFacingIssues }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-neutral-500">Customers Affected</p>
            <p class="mt-1 text-2xl font-semibold text-neutral-900">{{ $customersAffected }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mt-5 card p-4">
        <form method="GET" action="{{ route('employee.customer-issues.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1 form-group">
                <label class="form-label">Search Customer</label>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Search by customer name..."
                       class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="still_facing" {{ $statusFilter === 'still_facing' ? 'selected' : '' }}>Still Facing</option>
                    <option value="fixed" {{ $statusFilter === 'fixed' ? 'selected' : '' }}>Fixed</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                       class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                       class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('employee.customer-issues.index') }}" class="btn btn-secondary">Reset</a>
        </form>
    </div>

    {{-- Issues Table --}}
    <div class="mt-5 overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500 font-semibold">
                <tr>
                    <th class="px-4 py-2.5">Customer</th>
                    <th class="px-4 py-2.5">Issue</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">Date</th>
                    <th class="px-4 py-2.5">Last Updated</th>
                    <th class="px-4 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 text-xs">
                @forelse ($issues as $issue)
                    @php
                        $latestHistory = $issue->histories->first();
                    @endphp
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 font-medium text-neutral-900">
                            {{ $issue->customer?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $issue->issue_title }}</div>
                            <div class="mt-0.5 max-w-xs truncate text-neutral-500">{{ $issue->issue_description }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($issue->status === \App\Models\CrmCustomerIssue::STATUS_STILL_FACING)
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold badge-warning">Still Facing</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold badge-success">Fixed</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $issue->issue_date?->format('Y-m-d') ?? ($issue->created_at?->format('Y-m-d') ?? '—') }}</td>
                        <td class="px-4 py-3">{{ $issue->updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employee.customer-issues.show', $issue) }}"
                               class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-neutral-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-neutral-300">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442C14.18 12.45 13.532 14.36 13.532 14.36c-.213.179-.42.325-.67.442l-1.555.94a2.25 2.25 0 0 1-2.242-2.242l.94-1.555c.177-.25.382-.467.442-.67.186-1.086-.366-2.176-.366-2.176s-1.91-1.026-1.91-3.712c0-1.025.656-1.625 1.555-1.906C9.55 7.176 9.55 7.176 9.879 7.519z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                                </svg>
                                <p class="text-sm font-medium">No issues found.</p>
                                <p class="text-xs">Report a customer issue to get started.</p>
                                <a href="{{ route('employee.customer-issues.create') }}"
                                   class="mt-2 inline-flex items-center btn btn-primary">
                                    Report First Issue
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $issues->links() }}
    </div>
@endsection
