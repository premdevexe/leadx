@extends('layouts.admin-dashboard')

@section('title', 'Edit Order ' . ($order->order_number ?? ''))

@php
    $orderStatuses = \App\Models\SalesOrder::STATUSES;
    $paymentStatuses = \App\Models\SalesOrder::PAYMENT_STATUSES;
@endphp

@section('content')
    <div class="flex items-center justify-between">
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
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel & View Order</a>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">← All orders</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="mt-5 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
        @csrf
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-neutral-500">Team</label>
                <input type="text" disabled value="{{ $order->team_code_snapshot }} — {{ $order->team?->team_name ?? 'Team' }}"
                       class="mt-1 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-500 cursor-not-allowed">
                <p class="mt-1 text-xs text-neutral-400">Team attribution and order sequence cannot be altered.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Assigned employee</label>
                <select name="assigned_employee_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">— Unassigned —</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('assigned_employee_id', $order->assigned_employee_id) === (string) $employee->id)>
                            {{ $employee->username }}{{ $employee->name ? ' ('.$employee->name.')' : '' }}{{ $employee->team_id ? ' · Team #'.$employee->team_id : '' }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_employee_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer name <span class="text-red-500">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" required
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('customer_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer phone</label>
                <input type="text" name="customer_phone" value="{{ old('customer_phone', $order->customer_phone) }}" placeholder="+15551234567"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                <p class="mt-1 text-xs text-neutral-400">Needed for WhatsApp customer communications.</p>
                @error('customer_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-neutral-500">Customer email</label>
                <input type="email" name="customer_email" value="{{ old('customer_email', $order->customer_email) }}" placeholder="customer@example.com"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('customer_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2 border-t border-neutral-100 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Product & Pricing</h3>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Catalog Product</label>
                <select id="product-select" name="product_id"
                        class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">— none (enter custom package) —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-price="{{ $product->price }}"
                                data-duration="{{ $product->duration }}"
                                @selected((string) old('product_id', $order->product_id) === (string) $product->id)>
                            {{ $product->name }} ({{ number_format((float) $product->price, 2) }} — {{ \App\Models\SalesOrder::DURATIONS[$product->duration] ?? '1 year' }}){{ $product->status !== 'active' ? ' [Inactive]' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-neutral-400">Selecting a product auto-fills the name, value and duration below.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Product name</label>
                <input type="text" id="product-name" name="product_name" value="{{ old('product_name', $order->product_name ?? $order->product_name_snapshot) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('product_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Subscription duration <span class="text-red-500">*</span></label>
                <select id="duration-select" name="duration" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    @foreach (\App\Models\SalesOrder::DURATIONS as $code => $label)
                        <option value="{{ $code }}" @selected(old('duration', $order->duration ?? $order->product_duration_snapshot ?? '1y') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('duration') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Product value</label>
                <input type="number" step="0.01" min="0" id="product-value" name="product_value" value="{{ old('product_value', $order->product_value ?? $order->product_price_snapshot ?? 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('product_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Discount amount</label>
                <input type="number" step="0.01" min="0" id="discount-amount" name="discount_amount" value="{{ old('discount_amount', $order->discount_amount ?? 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('discount_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Closing value</label>
                <input type="number" step="0.01" min="0" id="closing-value" name="closing_value" value="{{ old('closing_value', $order->closing_value ?? 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                <p class="mt-1 text-xs text-neutral-400">Auto-calculated if blank (Product value - Discount).</p>
                @error('closing_value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Amount received</label>
                <input type="number" step="0.01" min="0" name="amount_received" value="{{ old('amount_received', $order->amount_received ?? 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('amount_received') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Payment status <span class="text-red-500">*</span></label>
                <select name="payment_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status }}" @selected(old('payment_status', $order->payment_status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                @error('payment_status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Order status <span class="text-red-500">*</span></label>
                <select name="order_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status }}" @selected(old('order_status', $order->order_status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                @error('order_status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between border-t border-neutral-100 pt-5">
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 transition">
                    Update order
                </button>
                <a href="{{ route('admin.orders.show', $order) }}" class="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 transition">
                    Cancel
                </a>
            </div>
            <a href="{{ route('admin.orders.show', $order) }}" class="text-xs text-neutral-500 hover:underline">
                View order summary →
            </a>
        </div>
    </form>

    <script>
        (function () {
            const select = document.getElementById('product-select');
            if (!select) return;
            const nameInput = document.getElementById('product-name');
            const priceInput = document.getElementById('product-value');
            const durationSelect = document.getElementById('duration-select');
            const discountInput = document.getElementById('discount-amount');
            const closingInput = document.getElementById('closing-value');

            function updateClosing() {
                const price = parseFloat(priceInput.value) || 0;
                const discount = parseFloat(discountInput.value) || 0;
                closingInput.value = Math.max(0, price - discount).toFixed(2);
            }

            select.addEventListener('change', function () {
                const opt = select.options[select.selectedIndex];
                if (!opt || opt.value === '') return;

                nameInput.value = opt.textContent.split(' (')[0].trim();
                if (priceInput && opt.dataset.price) priceInput.value = opt.dataset.price;
                if (durationSelect && opt.dataset.duration) durationSelect.value = opt.dataset.duration;

                updateClosing();
            });

            discountInput.addEventListener('input', updateClosing);
            priceInput.addEventListener('input', updateClosing);
        })();
    </script>
@endsection
