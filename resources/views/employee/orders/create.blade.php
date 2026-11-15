@extends('layouts.employee')

@section('title', 'Create Order')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Create Order</h1>
    <p class="mt-1 text-sm text-neutral-500">Preview order number: <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs">{{ $previewOrderNumber }}</code></p>

    <form method="POST" action="{{ route('employee.orders.store') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer name <span class="text-red-500">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('customer_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer phone</label>
                <div class="mt-1 flex gap-2">
                    <select name="customer_country_code" class="w-28 shrink-0 rounded-lg border border-neutral-300 bg-white px-2 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                        @php
                            $empCountryCodes = [
                                '+1' => '+1 (US/CA)',
                                '+44' => '+44 (UK)',
                                '+91' => '+91 (IN)',
                                '+61' => '+61 (AU)',
                                '+971' => '+971 (UAE)',
                                '+968' => '+968 (OM)',
                                '+974' => '+974 (QA)',
                                '+965' => '+965 (KW)',
                                '+966' => '+966 (SA)',
                                '+973' => '+973 (BH)',
                                '+20' => '+20 (EG)',
                                '+234' => '+234 (NG)',
                                '+254' => '+254 (KE)',
                                '+27' => '+27 (ZA)',
                                '+60' => '+60 (MY)',
                                '+65' => '+65 (SG)',
                                '+880' => '+880 (BD)',
                                '+94' => '+94 (LK)',
                                '+62' => '+62 (ID)',
                                '+63' => '+63 (PH)',
                                '+84' => '+84 (VN)',
                                '+86' => '+86 (CN)',
                            ];
                        @endphp
                        @foreach ($empCountryCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('customer_country_code') === $code || (! old('customer_country_code') && $code === '+91'))>{{ $code }}</option>
                        @endforeach
                    </select>
                    <input type="tel" name="customer_phone_local" value="{{ old('customer_phone_local') }}" placeholder="9876543210"
                           class="flex-1 rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                </div>
                @error('customer_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('customer_country_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer email</label>
                <input type="email" name="customer_email" value="{{ old('customer_email') }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('customer_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Product</label>
                <select id="product-select" name="product_id"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">— none (enter manually) —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-price="{{ $product->price }}"
                                data-duration="{{ $product->duration }}"
                                @selected(old('product_id') === (string) $product->id)>
                            {{ $product->name }} ({{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $product->price, 2) }} — {{ \App\Models\SalesOrder::DURATIONS[$product->duration] ?? '1 year' }})
                        </option>
                    @endforeach
                </select>
                @error('product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Product name</label>
                <input type="text" id="product-name" name="product_name" value="{{ old('product_name') }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Subscription duration</label>
                <select name="duration" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    @foreach (\App\Models\SalesOrder::DURATIONS as $code => $label)
                        <option value="{{ $code }}" @selected(old('duration', '1y') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Closing value ({{ \App\Models\Setting::currencySymbol() }}) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" id="closing-value" name="closing_value" value="{{ old('closing_value', 0) }}" required
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('closing_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Order notes</label>
                <textarea name="order_notes" rows="3"
                          class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">{{ old('order_notes') }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="w-full sm:w-auto rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
                Create order
            </button>
            <a href="{{ route('employee.orders') }}" class="w-full sm:w-auto text-center text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const select = document.getElementById('product-select');
            if (!select) return;
            const nameInput = document.getElementById('product-name');
            const closingInput = document.getElementById('closing-value');

            select.addEventListener('change', function () {
                const opt = select.options[select.selectedIndex];
                if (opt.value === '') return;
                nameInput.value = opt.textContent.split(' (')[0];
                const durationSelect = document.querySelector('select[name="duration"]');
                if (durationSelect && opt.dataset.duration) durationSelect.value = opt.dataset.duration;
                if (closingInput.value === '') closingInput.value = opt.dataset.price;
            });
        })();
    </script>
@endsection
