@extends('layouts.employee')

@section('title', 'Edit Issue')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-neutral-900">Edit Customer Issue</h1>
            <p class="mt-1 text-sm text-neutral-500">Update issue details or change its status.</p>
        </div>
        <a href="{{ route('employee.customer-issues.show', $issue) }}"
           class="inline-flex items-center justify-center rounded-lg border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-1.5 h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Back to Issue
        </a>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800">
            <strong class="font-semibold">Please fix the following errors:</strong>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('error'))
        <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('employee.customer-issues.update', $issue) }}" class="mt-5 space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-xs">
        @csrf
        @method('PUT')

        <div>
            <label for="customer_id" class="mb-1 block text-sm font-medium text-neutral-700">CRM Customer <span class="text-red-500">*</span></label>
            <select id="customer_id" name="customer_id"
                    class="w-full rounded-lg border @error('customer_id') border-red-300 @else border-neutral-300 @enderror px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 @error('customer_id') bg-red-50 @enderror">
                <option value="">— Select Customer —</option>
                @foreach (\App\Models\Customer::whereIn('id', $authorizedCustomerIds)->orderBy('name')->get() as $customer)
                    <option value="{{ $customer->id }}" {{ old('customer_id', $issue->customer_id) == $customer->id ? 'selected' : '' }}>
                        {{ $customer->name }} ({{ $customer->phone ?? 'no phone' }})
                    </option>
                @endforeach
            </select>
            @error('customer_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="issue_title" class="mb-1 block text-sm font-medium text-neutral-700">Issue Title <span class="text-red-500">*</span></label>
            <input type="text" id="issue_title" name="issue_title" value="{{ old('issue_title', $issue->issue_title) }}" required
                   placeholder="e.g. API Key not working, Login problem"
                   class="w-full rounded-lg border @error('issue_title') border-red-300 @else border-neutral-300 @enderror px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 @error('issue_title') bg-red-50 @enderror">
            @error('issue_title')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="issue_description" class="mb-1 block text-sm font-medium text-neutral-700">Issue Description <span class="text-red-500">*</span></label>
            <textarea id="issue_description" name="issue_description" rows="5" required
                      placeholder="Describe the problem in detail..."
                      class="w-full rounded-lg border @error('issue_description') border-red-300 @else border-neutral-300 @enderror px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900 @error('issue_description') bg-red-50 @enderror">{{ old('issue_description', $issue->issue_description) }}</textarea>
            @error('issue_description')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="issue_date" class="mb-1 block text-sm font-medium text-neutral-700">Issue Date</label>
                <input type="date" id="issue_date" name="issue_date" value="{{ old('issue_date', $issue->issue_date?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                @error('issue_date')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-neutral-700">Status</label>
                <select id="status" name="status"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                    <option value="still_facing" {{ old('status', $issue->status) === 'still_facing' ? 'selected' : '' }}>Still Facing</option>
                    <option value="fixed" {{ old('status', $issue->status) === 'fixed' ? 'selected' : '' }}>Fixed</option>
                </select>
                @error('status')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="update_note" class="mb-1 block text-sm font-medium text-neutral-700">Update Note (optional)</label>
            <p class="mb-2 text-xs text-neutral-500">Add a note to explain this change.</p>
            <textarea id="update_note" name="update_note" rows="3"
                      placeholder="e.g. Customer confirmed issue is resolved."
                      class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">{{ old('update_note') }}</textarea>
            @error('update_note')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('employee.customer-issues.show', $issue) }}"
               class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50">
                Cancel
            </a>
            <button type="submit"
                    class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-neutral-800">
                Update Issue
            </button>
        </div>
    </form>
@endsection
