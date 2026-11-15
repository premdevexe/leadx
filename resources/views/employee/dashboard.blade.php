@extends('layouts.employee')

@section('title', 'Employee Dashboard')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Employee Dashboard</h1>
    <p class="mt-1 text-sm text-neutral-500">{{ $employee->name }} ({{ $employee->username }})</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">My Total Orders</p>
            <p class="mt-1 text-2xl font-semibold">{{ $totalOrders }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">My Closing Value</p>
            <p class="mt-1 text-2xl font-semibold">{{ $closingValue }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">My Verified Revenue</p>
            <p class="mt-1 text-2xl font-semibold">{{ $verifiedRevenue }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">My Pending Collections</p>
            <p class="mt-1 text-2xl font-semibold">{{ $pendingAmount }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">Active Subscriptions</p>
            <p class="mt-1 text-2xl font-semibold">{{ $activeSubscriptions }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">Expiring (30 days)</p>
            <p class="mt-1 text-2xl font-semibold">{{ $expiringSubscriptions }}</p>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <a href="{{ route('employee.orders.create') }}" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
            Create Order
        </a>
        <a href="{{ route('employee.orders') }}" class="rounded-lg border border-neutral-200 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50">
            View Orders
        </a>
    </div>
@endsection
