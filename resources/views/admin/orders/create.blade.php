@extends('layouts.admin-dashboard')

@section('title', 'New Order')

@php
    $orderStatuses = \App\Models\SalesOrder::STATUSES;
    $paymentStatuses = \App\Models\SalesOrder::PAYMENT_STATUSES;
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
                <label class="block text-xs font-medium text-neutral-500">Team <span class="text-red-500">*</span></label>
                <select name="team_id" required class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">Select a team…</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected(old('team_id') === (string) $team->id)>{{ $team->team_code }} — {{ $team->team_name }}</option>
                    @endforeach
                </select>
                @error('team_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Assigned employee</label>
                <select name="assigned_employee_id" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">Auto (first team member)</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('assigned_employee_id') === (string) $employee->id)>
                            {{ $employee->username }}{{ $employee->team_id ? ' (team #'.$employee->team_id.')' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_employee_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer name <span class="text-red-500">*</span></label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                @error('customer_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Customer phone</label>
                <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+15551234567"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                <p class="mt-1 text-xs text-neutral-400">Needed later for WhatsApp sharing.</p>
                @error('customer_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
                            {{ $product->name }} ({{ number_format((float) $product->price, 2) }} — {{ \App\Models\SalesOrder::DURATIONS[$product->duration] ?? '1 year' }})
                        </option>
                    @endforeach
                </select>
                @error('product_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-neutral-400">Selecting a product auto-fills the name, value and duration below.</p>
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
                <label class="block text-xs font-medium text-neutral-500">Product value</label>
                <input type="number" step="0.01" min="0" name="product_value" value="{{ old('product_value', 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Closing value</label>
                <input type="number" step="0.01" min="0" id="closing-value" name="closing_value" value="{{ old('closing_value', 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                <p class="mt-1 text-xs text-neutral-400">The final deal price. Leave blank to close at full product value.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Discount amount</label>
                <input type="text" readonly id="discount-amount" name="discount_amount" value="0.00"
                       class="mt-1 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-500 cursor-not-allowed tabular-nums">
                <p class="mt-1 text-xs text-neutral-400">Auto-calculated: Product value − Closing value. Read-only.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Amount received</label>
                <input type="number" step="0.01" min="0" name="amount_received" value="{{ old('amount_received', 0) }}"
                       class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Payment method</label>
                <select name="payment_method" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    <option value="">—</option>
                    @foreach ($paymentMethods ?? \App\Models\Setting::PAYMENT_METHODS as $code => $label)
                        @if (in_array($code, $enabledPaymentMethods ?? array_keys(\App\Models\Setting::PAYMENT_METHODS), true))
                            <option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-neutral-400">Only methods enabled in Settings are shown.</p>
                @error('payment_method') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Payment status</label>
                <select name="payment_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status }}" @selected(old('payment_status', 'pending') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-neutral-500">Order status</label>
                <select name="order_status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status }}" @selected(old('order_status', 'pending') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
                Create order
            </button>
            <a href="{{ route('admin.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const select = document.getElementById('product-select');
            if (!select) return;
            const nameInput = document.getElementById('product-name');
            const priceInput = document.querySelector('[name="product_value"]');
            const closingInput = document.getElementById('closing-value');
            const discountInput = document.getElementById('discount-amount');

            // Discount is read-only and derives from the closing price:
            // discount = product value − closing value. Closing value is the
            // rep's input; touching either side re-computes the discount.
            function updateDiscount() {
                if (!discountInput || !closingInput) return;
                const price = parseFloat(priceInput ? priceInput.value : '') || 0;
                const closing = parseFloat(closingInput.value) || 0;
                discountInput.value = Math.max(0, price - closing).toFixed(2);
            }

            select.addEventListener('change', function () {
                const opt = select.options[select.selectedIndex];
                if (opt.value === '') return;

                // "Name (price — duration)" → name only.
                nameInput.value = opt.textContent.split(' (')[0];

                if (priceInput) priceInput.value = opt.dataset.price;

                const durationSelect = document.querySelector('select[name="duration"]');
                if (durationSelect && opt.dataset.duration) durationSelect.value = opt.dataset.duration;

                // A catalog product defaults to closing at full price (no
                // discount) unless the rep already typed a closing value.
                if (closingInput.value === '') {
                    closingInput.value = opt.dataset.price;
                }

                updateDiscount();
            });

            if (closingInput) closingInput.addEventListener('input', updateDiscount);
            if (priceInput) priceInput.addEventListener('input', updateDiscount);

            updateDiscount();
        })();
    </script>
@endsection
