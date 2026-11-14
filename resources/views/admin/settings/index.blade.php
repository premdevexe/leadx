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

        {{-- Site identity --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-700">Site</h2>
            <p class="mt-1 text-xs text-neutral-500">
                The site name and currency shown across the admin panel. The currency applies to newly recorded payments.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="site_name" class="text-sm font-medium text-neutral-800">Site name</label>
                    <input id="site_name" name="site_name" type="text" value="{{ $siteName }}"
                           maxlength="100"
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                    @error('site_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency" class="text-sm font-medium text-neutral-800">Currency</label>
                    <select id="currency" name="currency"
                            class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @foreach ($currencies as $code => $symbol)
                            <option value="{{ $code }}" @selected($code === $currency)>{{ $symbol }} — {{ $code }}</option>
                        @endforeach
                    </select>
                    @error('currency') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

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
