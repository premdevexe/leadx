@extends('layouts.admin-dashboard')

@section('title', 'Edit Product')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Edit Product</h1>
    <p class="mt-1 text-sm text-neutral-500">{{ $product->name }}</p>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @method('PUT')
        @include('admin.products._form')
    </form>
@endsection
