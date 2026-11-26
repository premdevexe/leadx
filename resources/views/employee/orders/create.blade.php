@extends('layouts.employee')

@section('title', 'Create Order')

@section('content')
    <h1 class="text-lg">Create Order</h1>
    <p class="mt-1 text-sm text-neutral-500">Preview order number: <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs">{{ $previewOrderNumber }}</code></p>

    <form method="POST" action="{{ route('employee.orders.store') }}" class="mt-5 card p-5">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="form-group">
                <label class="form-label">Customer name <span class="text-red-500">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                       class="form-input">
                @error('customer_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Customer phone</label>
                <div class="mt-1 flex gap-2">
                    <select name="customer_country_code" class="w-28 shrink-0 form-input">
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
                           class="flex-1 form-input">
                </div>
                @error('customer_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('customer_country_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Customer email</label>
                <input type="email" name="customer_email" value="{{ old('customer_email') }}"
                       class="form-input">
                @error('customer_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div style="grid-column: 1 / -1;">
                @include('partials.order-items-builder', [
                    'instanceId' => 'employee-create-oib',
                    'products' => $products,
                    'existingItems' => old('items', []),
                    'legacyProductId' => old('product_id'),
                    'locked' => false,
                    'canBypassFloor' => false,
                    'renderClosingControl' => true,
                    'initialClosingValue' => old('closing_value', 0),
                ])
            </div>

            {{-- Order Information --}}
            <div style="grid-column: 1 / -1;" class="form-group">
                <label class="form-label">Order notes</label>
                <textarea name="order_notes" rows="3"
                          placeholder="Optional internal notes or customer delivery instructions..."
                          class="form-textarea w-full">{{ old('order_notes') }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary">Create order</button>
            <a href="{{ route('employee.orders') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
