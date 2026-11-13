@extends('layouts.admin-dashboard')

@section('title', 'Products')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Products</h1>
        <a href="{{ route('admin.products.create') }}"
           class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
            New product
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if ($products->isEmpty())
        <div class="mt-5 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            No products yet. Create your first product
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
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-neutral-600">
                                {{ \App\Models\SalesOrder::DURATIONS[$product->duration] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $product->price, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-1.5 text-xs {{ $product->status === 'active' ? 'text-emerald-700' : 'text-neutral-500' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $product->status === 'active' ? 'bg-emerald-500' : 'bg-neutral-300' }}"></span>
                                    {{ $product->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.products.edit', $product) }}" class="text-sm text-neutral-600 transition-colors hover:text-neutral-900">Edit</a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                                      onsubmit="return confirm('Delete product {{ $product->name }}? Existing orders keep their snapshot.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ml-3 text-sm text-red-600 transition-colors hover:text-red-800">Delete</button>
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
