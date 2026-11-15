@extends('layouts.employee')

@section('title', 'Issue Details')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">Issue Details</h1>
            <p class="mt-1 text-sm text-neutral-500">View and update the status of this issue.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('employee.customer-issues.index') }}"
               class="inline-flex items-center justify-center rounded-lg border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1.5 h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                All Issues
            </a>
            <a href="{{ route('employee.customer-issues.edit', $issue) }}"
               class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-800">
                Edit Issue
            </a>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Main Details --}}
        <div class="space-y-5 lg:col-span-2">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-neutral-500">Issue Information</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs text-neutral-500">Issue Title</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $issue->issue_title }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-500">Description</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-neutral-700">{{ $issue->issue_description }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-neutral-500">Customer</p>
                            <p class="mt-1 text-sm font-medium text-neutral-900">{{ $issue->customer?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-500">Current Status</p>
                            <p class="mt-1">
                                @if ($issue->status === \App\Models\CrmCustomerIssue::STATUS_STILL_FACING)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800">Still Facing</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800">Fixed</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-500">Issue Date</p>
                            <p class="mt-1 text-sm text-neutral-900">{{ $issue->issue_date?->format('Y-m-d') ?? ($issue->created_at?->format('Y-m-d') ?? '—') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-500">Last Updated</p>
                            <p class="mt-1 text-sm text-neutral-900">{{ $issue->updated_at?->format('Y-m-d H:i') ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar: Quick Status --}}
        <div class="space-y-5">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-neutral-500">Quick Actions</h2>
                <div class="mt-4 space-y-3">
                    @if ($issue->status === \App\Models\CrmCustomerIssue::STATUS_STILL_FACING)
                        <form method="POST" action="{{ route('employee.customer-issues.update', $issue) }}" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="customer_id" value="{{ $issue->customer_id }}">
                            <input type="hidden" name="issue_title" value="{{ $issue->issue_title }}">
                            <input type="hidden" name="issue_description" value="{{ $issue->issue_description }}">
                            <input type="hidden" name="issue_date" value="{{ $issue->issue_date?->format('Y-m-d') }}">
                            <input type="hidden" name="status" value="fixed">
                            <label class="mb-1 block text-xs font-medium text-neutral-600">Update Note (optional)</label>
                            <textarea name="update_note" rows="3" placeholder="Add a note about this fix..."
                                      class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900"></textarea>
                            <button type="submit"
                                    class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700">
                                Mark as Fixed
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('employee.customer-issues.update', $issue) }}" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="customer_id" value="{{ $issue->customer_id }}">
                            <input type="hidden" name="issue_title" value="{{ $issue->issue_title }}">
                            <input type="hidden" name="issue_description" value="{{ $issue->issue_description }}">
                            <input type="hidden" name="issue_date" value="{{ $issue->issue_date?->format('Y-m-d') }}">
                            <input type="hidden" name="status" value="still_facing">
                            <label class="mb-1 block text-xs font-medium text-neutral-600">Update Note (optional)</label>
                            <textarea name="update_note" rows="3" placeholder="Add a note about reopening..."
                                      class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900"></textarea>
                            <button type="submit"
                                    class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700">
                                Reopen Issue
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="mt-5 rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-200 px-5 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-neutral-500">Issue History</h2>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($issue->histories as $history)
                @php
                    $isFixed = $history->new_status === \App\Models\CrmCustomerIssue::STATUS_FIXED;
                @endphp
                <div class="flex gap-4 px-5 py-4 text-sm">
                    <div class="flex flex-col items-center">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full
                            {{ $isFixed ? 'bg-emerald-100' : 'bg-amber-100' }}">
                            @if ($isFixed)
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 text-emerald-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 text-amber-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            @endif
                        </div>
                        @if (!$loop->last)
                            <div class="mt-1 h-full w-px bg-neutral-200"></div>
                        @endif
                    </div>
                    <div class="flex-1 pb-2">
                        <div class="flex items-center gap-2">
                            @if ($isFixed)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-800">Fixed</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-800">Still Facing</span>
                            @endif
                            @if ($history->old_status)
                                @if ($history->old_status === \App\Models\CrmCustomerIssue::STATUS_STILL_FACING && $isFixed)
                                    <span class="text-xs text-neutral-500">— still facing → fixed</span>
                                @elseif ($history->old_status === \App\Models\CrmCustomerIssue::STATUS_FIXED && !$isFixed)
                                    <span class="text-xs text-neutral-500">— fixed → still facing (reopened)</span>
                                @else
                                    <span class="text-xs text-neutral-500">— status changed</span>
                                @endif
                            @else
                                <span class="text-xs text-neutral-500">— issue created</span>
                            @endif
                        </div>
                        @if ($history->update_note)
                            <p class="mt-1.5 text-sm text-neutral-600">{{ $history->update_note }}</p>
                        @endif
                        <p class="mt-1 text-xs text-neutral-400">{{ $history->created_at?->format('Y-m-d H:i') ?? '' }}</p>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-neutral-400">No history recorded.</div>
            @endforelse
        </div>
    </div>
@endsection
