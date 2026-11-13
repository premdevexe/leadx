@extends('layouts.admin-dashboard')

@section('title', 'New Product')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">New Product</h1>

    <form method="POST" action="{{ route('admin.products.store') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf
        @include('admin.products._form')
    </form>
@endsection
