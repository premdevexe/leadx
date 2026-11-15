@extends('layouts.employee')

@section('title', 'Reports & Analytics')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">Reports & Analytics</h1>
        <p class="mt-1 text-sm text-neutral-500">Sales performance and pipeline analytics.</p>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs">
        <h2 class="text-sm font-semibold text-neutral-900 mb-2">Performance Summary</h2>
        <p class="text-xs text-neutral-500">Track your closed orders, conversions, and target achievements.</p>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-lg bg-neutral-50 p-4 border border-neutral-200">
                <span class="text-xs text-neutral-500">Target Achievement</span>
                <p class="text-xl font-bold text-neutral-900 mt-1">92%</p>
            </div>
            <div class="rounded-lg bg-neutral-50 p-4 border border-neutral-200">
                <span class="text-xs text-neutral-500">Average Turnaround</span>
                <p class="text-xl font-bold text-neutral-900 mt-1">2.4 Days</p>
            </div>
            <div class="rounded-lg bg-neutral-50 p-4 border border-neutral-200">
                <span class="text-xs text-neutral-500">Client Retention</span>
                <p class="text-xl font-bold text-neutral-900 mt-1">98.5%</p>
            </div>
        </div>
    </div>
</div>
@endsection
