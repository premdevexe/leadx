@extends('layouts.admin-dashboard')

@section('title', 'Products')

@php
    $f = $filters ?? ['search' => null, 'status' => null];
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Products</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Manage catalog products, pricing, subscription durations, and activation status.</p>
        </div>
        <a href="{{ route('admin.products.create') }}"
           class="btn btn-primary px-3 py-1.5 text-sm font-medium">
            New product
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 alert alert-danger text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 alert alert-danger text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Search and Status Filters --}}
    <form method="GET" action="{{ route('admin.products') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="form-label">Search product name</label>
                <input type="text" name="search" value="{{ $f['search'] ?? '' }}"
                       placeholder="e.g. Starter Plan, Pro…"
                       class="form-input mt-1 w-full">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select mt-1 w-full">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Product::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($f['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                    Filter
                </button>
                @if (!empty($f['search']) || !empty($f['status']))
                    <a href="{{ route('admin.products') }}" class="btn btn-secondary px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
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
        <div class="mt-5 table-wrapper">
            <table class="data-table w-full" style="min-width:640px">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3 text-right">Base Price</th>
                        <th class="px-4 py-3 text-right">Floor Price</th>
                        <th class="px-4 py-3">API Key Req.</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Orders</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-medium text-neutral-900">{{ $product->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ \App\Models\SalesOrder::DURATIONS[$product->duration] ?? $product->duration }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900">
                                {{ $cur }}{{ number_format((float) $product->price, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-600 font-medium">
                                {{ $cur }}{{ number_format((float) ($product->minimum_closing_price ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($product->api_key_required)
                                    <span class="badge badge-purple" style="font-size:11px;padding:1px 8px">Yes</span>
                                @else
                                    <span class="text-xs text-neutral-400">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-1.5 text-xs font-medium {{ $product->status === 'active' ? 'text-emerald-700' : 'text-neutral-500' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $product->status === 'active' ? 'bg-emerald-500' : 'bg-neutral-300' }}"></span>
                                    {{ ucfirst($product->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-neutral-500">
                                {{ $product->orders_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('admin.products.toggle-status', $product) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs {{ $product->status === 'active' ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}"
                                            title="Toggle active / inactive status">
                                        {{ $product->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.products.edit', $product) }}" class="ml-3 text-sm text-neutral-600 hover:text-neutral-900">Edit</a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                                      onsubmit="return confirm('{{ ($product->orders_count ?? 0) > 0 ? "Product '{$product->name}' is linked to orders. It will be safely archived/deactivated. Continue?" : "Delete product '{$product->name}' permanently?" }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ml-3 text-sm text-red-600 hover:text-red-800">
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
            <div class="mt-4 overflow-x-auto">{{ $products->links() }}</div>
        @endif
    @endif
@endsection
