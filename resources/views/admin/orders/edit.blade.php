@extends('layouts.admin-dashboard')

@section('title', 'Edit Order ' . ($order->order_number ?? ''))

@php
    $orderStatuses = \App\Models\SalesOrder::STATUSES;
    $paymentStatuses = \App\Models\SalesOrder::PAYMENT_STATUSES;
    $cur = \App\Models\Setting::currencySymbol();
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Edit Order</h1>
            <p class="mt-1 text-sm text-neutral-500">
                Order <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-800">{{ $order->order_number ?? 'Unnumbered' }}</code>
                · Team <span class="font-medium text-neutral-700">{{ $order->team_code_snapshot }}</span>
                @if ($order->team)
                    ({{ $order->team->team_name }})
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel & View Order</a>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; All orders</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 alert alert-danger text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
        @csrf
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Team</label>
                <input type="text" disabled value="{{ $order->team_code_snapshot }} — {{ $order->team?->team_name ?? 'Team' }}"
                       class="form-input mt-1 w-full bg-neutral-50 text-neutral-500 cursor-not-allowed">
                <p class="form-hint">Team attribution and order sequence cannot be altered.</p>
            </div>

            <div>
                <label class="form-label">Assigned employee</label>
                <select name="assigned_employee_id" class="form-select mt-1 w-full">
                    <option value="">— Unassigned —</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('assigned_employee_id', $order->assigned_employee_id) === (string) $employee->id)>
                            {{ $employee->username }}{{ $employee->name ? ' ('.$employee->name.')' : '' }}{{ $employee->team_id ? ' · Team #'.$employee->team_id : '' }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_employee_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Customer name <span class="text-red-600">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" required
                       class="form-input mt-1 w-full">
                @error('customer_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Customer phone</label>
                @php
                    $existingPhone = $order->customer_phone ?? '';
                    $existingCc = $order->customer_country_code ?? '';
                    $existingLocal = $existingPhone;
                    if (! $existingCc && $existingPhone) {
                        $ccList = ['+968','+974','+965','+966','+973','+880','+234','+254','+971','+62','+63','+84','+94','+60','+65','+20','+27','+61','+44','+86','+91','+1'];
                        foreach ($ccList as $cc) {
                            if (str_starts_with($existingPhone, $cc)) {
                                $existingCc = $cc;
                                $existingLocal = substr($existingPhone, strlen($cc));
                                break;
                            }
                        }
                    } elseif ($existingCc && $existingPhone) {
                        $existingLocal = substr($existingPhone, strlen($existingCc));
                    }
                    if (! $existingCc) { $existingCc = '+91'; }
                    if ($existingLocal === $existingPhone && $existingCc) {
                        $existingLocal = substr($existingLocal, strlen($existingCc));
                    }
                @endphp
                <div class="mt-1 flex gap-2">
                    <select name="customer_country_code" class="w-28 shrink-0 form-select">
                        @php
                            $editCountryCodes = [
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
                        @foreach ($editCountryCodes as $code => $label)
                            <option value="{{ $code }}" @selected(old('customer_country_code', $existingCc) === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                    <input type="tel" name="customer_phone_local" value="{{ old('customer_phone_local', $existingLocal) }}" placeholder="9876543210"
                           class="form-input flex-1">
                </div>
                <p class="form-hint">Needed for WhatsApp customer communications.</p>
                @error('customer_phone') <p class="form-error">{{ $message }}</p> @enderror
                @error('customer_country_code') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Customer email</label>
                <input type="email" name="customer_email" value="{{ old('customer_email', $order->customer_email) }}" placeholder="customer@example.com"
                       class="form-input mt-1 w-full">
                @error('customer_email') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2 border-t border-neutral-100 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Product & Pricing</h3>
            </div>

            @php
                $editItems = old('items');
                if ($editItems === null) {
                    $editItems = $order->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'name' => $item->product_name_snapshot,
                        'unit_price' => (float) $item->unit_price_snapshot,
                        'floor' => (float) ($item->minimum_closing_price_snapshot ?? 0),
                        'duration_code' => $item->product_duration_snapshot,
                        'quantity' => $item->quantity,
                    ])->values()->all();
                }
            @endphp

            <div class="sm:col-span-2">
                @include('partials.order-items-builder', [
                    'instanceId' => 'admin-edit-oib',
                    'products' => $products,
                    'existingItems' => $editItems,
                    'legacyProductId' => old('product_id', $order->product_id),
                    'locked' => $itemsLocked ?? false,
                    'canBypassFloor' => true,
                    'currencySymbol' => $cur,
                ])
            </div>

            <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2 {{ (!empty($editItems) && ($itemsLocked ?? false)) ? 'hidden' : '' }}" data-oib-manual-fallback>
                <div>
                    <label class="form-label">Product name (Manual / Custom)</label>
                    <input type="text" id="product-name" name="product_name" value="{{ old('product_name', $order->product_name ?? $order->product_name_snapshot) }}"
                           class="form-input mt-1 w-full">
                    @error('product_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Subscription duration <span class="text-red-600">*</span></label>
                    <select id="duration-select" name="duration" class="form-select mt-1 w-full">
                        @foreach (\App\Models\SalesOrder::DURATIONS as $code => $label)
                            <option value="{{ $code }}" @selected(old('duration', $order->duration ?? $order->product_duration_snapshot ?? '1y') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('duration') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Product value ({{ $cur }})</label>
                    <input type="number" step="0.01" min="0" id="product-value" name="product_value" value="{{ old('product_value', $order->product_value ?? $order->product_price_snapshot ?? 0) }}"
                           class="form-input mt-1 w-full">
                    @error('product_value') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="form-label">Closing value ({{ $cur }})</label>
                <input type="number" step="0.01" min="0" id="closing-value" name="closing_value" value="{{ old('closing_value', $order->closing_value ?? $order->product_value ?? 0) }}"
                       class="form-input mt-1 w-full">
                <p class="form-hint">The final deal price you close at. The discount below is auto-calculated from it.</p>
                @error('closing_value') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Discount amount</label>
                <input type="text" readonly id="discount-amount" name="discount_amount" value="{{ $order->discount_amount ?? 0 }}"
                       class="form-input mt-1 w-full tabular-nums bg-neutral-50 text-neutral-500 cursor-not-allowed">
                <p class="form-hint">Auto-calculated: Product value − Closing value. Read-only.</p>
                @error('discount_amount') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Amount received</label>
                <input type="number" step="0.01" min="0" name="amount_received" value="{{ old('amount_received', $order->amount_received ?? 0) }}"
                       class="form-input mt-1 w-full">
                @error('amount_received') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Payment status <span class="text-red-600">*</span></label>
                <select name="payment_status" class="form-select mt-1 w-full">
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status }}" @selected(old('payment_status', $order->payment_status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                @error('payment_status') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Order status <span class="text-red-600">*</span></label>
                <select name="order_status" class="form-select mt-1 w-full">
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status }}" @selected(old('order_status', $order->order_status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                @error('order_status') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-neutral-100 pt-5">
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium hover:bg-neutral-800">
                    Update order
                </button>
                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-secondary px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
            <a href="{{ route('admin.orders.show', $order) }}" class="text-xs text-neutral-500 hover:underline">
                View order summary &rarr;
            </a>
        </div>
    </form>

    <script>
        (function () {
            const priceInput = document.getElementById('product-value');
            const discountInput = document.getElementById('discount-amount');
            const closingInput = document.getElementById('closing-value');
            if (!priceInput || !discountInput || !closingInput) return;

            function updateDiscount() {
                const price = parseFloat(priceInput.value) || 0;
                const closing = parseFloat(closingInput.value) || 0;
                discountInput.value = Math.max(0, price - closing).toFixed(2);
            }

            closingInput.addEventListener('input', updateDiscount);
            priceInput.addEventListener('input', updateDiscount);
            document.addEventListener('order-items:changed', updateDiscount);
            updateDiscount();
        })();
    </script>
@endsection
