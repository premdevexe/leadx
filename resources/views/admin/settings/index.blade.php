@extends('layouts.admin-dashboard')

@section('title', 'Settings')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Settings</h1>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        {{-- Payment methods --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-700">Payment methods</h2>
            <p class="mt-1 text-xs text-neutral-500">
                Enabled methods appear on the order form. Existing orders keep the method they were created with.
            </p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($paymentMethods as $code => $label)
                    <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm {{ in_array($code, $enabledMethods, true) ? 'border-neutral-400 bg-neutral-50' : '' }}">
                        <input type="checkbox" name="payment_methods[]" value="{{ $code }}"
                               @checked(in_array($code, $enabledMethods, true))
                               class="rounded border-neutral-300">
                        <span class="font-medium text-neutral-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('payment_methods') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
                Save settings
            </button>
        </div>
    </form>
@endsection
