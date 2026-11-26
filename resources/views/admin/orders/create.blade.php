@extends('layouts.admin-dashboard')

@section('title', 'New Order')

@php
    $orderStatuses = \App\Models\SalesOrder::STATUSES;
    $paymentStatuses = \App\Models\SalesOrder::PAYMENT_STATUSES;
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">New Order</h1>
    <p class="mt-1 text-sm text-neutral-500">
        The order number (e.g. <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs">{{ old('team_id') ? '—' : 'GEM' }}-{{ now()->format('Y') }}-000001</code>)
        is generated automatically from the team's code and the current year.
    </p>

    <form method="POST" action="{{ route('admin.orders.store') }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Team <span class="text-red-600">*</span></label>
                <select name="team_id" required class="form-select mt-1 w-full">
                    <option value="">Select a team…</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected(old('team_id') === (string) $team->id)>{{ $team->team_code }} — {{ $team->team_name }}</option>
                    @endforeach
                </select>
                @error('team_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Assigned employee</label>
                <select name="assigned_employee_id" class="form-select mt-1 w-full">
                    <option value="">Auto (first team member)</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('assigned_employee_id') === (string) $employee->id)>
                            {{ $employee->username }}{{ $employee->team_id ? ' (team #'.$employee->team_id.')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_employee_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Customer name <span class="text-red-600">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                       class="form-input mt-1 w-full">
                @error('customer_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Customer phone <span class="text-red-600">*</span></label>
                <div class="mt-1 flex gap-2">
                    <select name="customer_country_code" class="w-28 shrink-0 form-select">
                        @php
                            $countryCodes = [
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
                        @foreach ($countryCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('customer_country_code') === $code || (! old('customer_country_code') && $code === '+91'))>{{ $code }}</option>
                        @endforeach
                    </select>
                    <input type="tel" name="customer_phone_local" value="{{ old('customer_phone_local') }}" placeholder="9876543210" required
                           class="form-input flex-1">
                </div>
                <p class="form-hint">Needed later for WhatsApp sharing. Enter number without country code prefix.</p>
                @error('customer_phone') <p class="form-error">{{ $message }}</p> @enderror
                @error('customer_country_code') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                @include('partials.order-items-builder', [
                    'instanceId' => 'admin-create-oib',
                    'products' => $products,
                    'existingItems' => old('items', []),
                    'legacyProductId' => old('product_id'),
                    'locked' => false,
                    'canBypassFloor' => true,
                    'currencySymbol' => $cur,
                ])
            </div>

            <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2" data-oib-manual-fallback>
                <div>
                    <label class="form-label">Product name (Manual / Custom)</label>
                    <input type="text" id="product-name" name="product_name" value="{{ old('product_name') }}"
                           class="form-input mt-1 w-full">
                </div>

                <div>
                    <label class="form-label">Subscription duration</label>
                    <select name="duration" class="form-select mt-1 w-full">
                        @foreach (\App\Models\SalesOrder::DURATIONS as $code => $label)
                            <option value="{{ $code }}" @selected(old('duration', '1y') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Product value ({{ $cur }})</label>
                    <input type="number" step="0.01" min="0" name="product_value" value="{{ old('product_value', 0) }}"
                           class="form-input mt-1 w-full">
                </div>
            </div>

            <div>
                <label class="form-label">Closing value ({{ $cur }})</label>
                <input type="number" step="0.01" min="0" id="closing-value" name="closing_value" value="{{ old('closing_value', 0) }}"
                       class="form-input mt-1 w-full">
                <p class="form-hint">The final deal price. Leave blank to close at full product value.</p>
            </div>

            <div>
                <label class="form-label">Discount amount ({{ $cur }})</label>
                <input type="text" readonly id="discount-amount" name="discount_amount" value="0.00"
                       class="form-input mt-1 w-full tabular-nums bg-neutral-50 text-neutral-500 cursor-not-allowed">
                <p class="form-hint">Auto-calculated: Product value − Closing value. Read-only.</p>
            </div>

            <div>
                <label class="form-label">Amount received ({{ $cur }})</label>
                <input type="number" step="0.01" min="0" name="amount_received" value="{{ old('amount_received', 0) }}"
                       class="form-input mt-1 w-full">
            </div>

            <div>
                <label class="form-label">Payment method</label>
                <select name="payment_method" class="form-select mt-1 w-full">
                    <option value="">—</option>
                    @foreach ($paymentMethods ?? \App\Models\Setting::PAYMENT_METHODS as $code => $label)
                        @if (in_array($code, $enabledPaymentMethods ?? array_keys(\App\Models\Setting::PAYMENT_METHODS), true))
                            <option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
                <p class="form-hint">Only methods enabled in Settings are shown.</p>
                @error('payment_method') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Payment status</label>
                <select name="payment_status" class="form-select mt-1 w-full">
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status }}" @selected(old('payment_status', 'pending') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Order status</label>
                <select name="order_status" class="form-select mt-1 w-full">
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status }}" @selected(old('order_status', 'pending') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium">
                Create order
            </button>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const priceInput = document.querySelector('[name="product_value"]');
            const closingInput = document.getElementById('closing-value');
            const discountInput = document.getElementById('discount-amount');

            function updateDiscount() {
                if (!discountInput || !closingInput) return;
                const price = parseFloat(priceInput ? priceInput.value : '') || 0;
                const closing = parseFloat(closingInput.value) || 0;
                discountInput.value = Math.max(0, price - closing).toFixed(2);
            }

            if (closingInput) closingInput.addEventListener('input', updateDiscount);
            if (priceInput) priceInput.addEventListener('input', updateDiscount);
            document.addEventListener('order-items:changed', updateDiscount);

            updateDiscount();
        })();
    </script>
@endsection
