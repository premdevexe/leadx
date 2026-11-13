@extends('layouts.admin-dashboard')

@section('title', 'Products')

@php
    $f = $filters ?? ['search' => null, 'status' => null];
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Products</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Manage catalog products, pricing, subscription durations, and activation status.</p>
        </div>
        <a href="{{ route('admin.products.create') }}"
           class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
            New product
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    {{-- Search and Status Filters --}}
    <form method="GET" action="{{ route('admin.products') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-neutral-500">Search product name</label>
                <input type="text" name="search" value="{{ $f['search'] ?? '' }}"
                       placeholder="e.g. Starter Plan, Pro…"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-neutral-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Product::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($f['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
                    Filter
                </button>
                @if (!empty($f['search']) || !empty($f['status']))
                    <a href="{{ route('admin.products') }}" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
                        Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if ($products->isEmpty())
        <div class="mt-5 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            @if (!empty($f['search']) || !empty($f['status']))
                No products match the selected filters. <a href="{{ route('admin.products') }}" class="text-neutral-900 underline">Reset filters</a>
            @else
                No products yet. <a href="{{ route('admin.products.create') }}" class="text-neutral-900 underline">Create your first product</a>
            @endif
        </div>
    @else
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3 text-right">Price</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Orders</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/50">
                            <td class="px-4 py-3">
                                <span class="font-medium text-neutral-900">{{ $product->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ \App\Models\SalesOrder::DURATIONS[$product->duration] ?? $product->duration }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">
                                {{ number_format((float) $product->price, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $product->status === 'active' ? 'text-emerald-700' : 'text-neutral-500' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $product->status === 'active' ? 'bg-emerald-500' : 'bg-neutral-300' }}"></span>
                                    {{ ucfirst($product->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">
                                {{ $product->orders_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.products.toggle-status', $product) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs {{ $product->status === 'active' ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}"
                                            title="Toggle active / inactive status">
                                        {{ $product->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.products.edit', $product) }}" class="ml-3 text-sm text-neutral-600 transition-colors hover:text-neutral-900">Edit</a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                                      onsubmit="return confirm('{{ ($product->orders_count ?? 0) > 0 ? "Product \'{$product->name}\' is linked to orders. It will be safely archived/deactivated. Continue?" : "Delete product \'{$product->name}\' permanently?" }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ml-3 text-sm text-red-600 transition-colors hover:text-red-800">
                                        {{ ($product->orders_count ?? 0) > 0 ? 'Archive' : 'Delete' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="mt-4">{{ $products->links() }}</div>
        @endif
    @endif
@endsection
