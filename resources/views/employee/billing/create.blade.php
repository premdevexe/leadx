@extends('layouts.employee')

@section('title', 'Create Invoice')

@php
    $cur = \App\Models\Setting::currencySymbolFor($billing['default_currency'] ?? \App\Models\Setting::currency());
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Create Invoice</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Add one or more products/services; totals are validated server-side.</p>
        </div>
        <a href="{{ route('employee.billing.index') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; Back</a>
    </div>

    <form method="POST" action="{{ route('employee.billing.store') }}" class="mt-5 space-y-5">
        @csrf
        @php
            $prefilledItems = [];
            if (isset($selectedOrder) && $selectedOrder) {
                if ($selectedOrder->items->isNotEmpty()) {
                    $prefilledItems = $selectedOrder->items->map(fn ($item) => [
                        'product_id' => $item->product_id ?? '',
                        'description' => $item->product_name_snapshot,
                        'duration' => \App\Models\SalesOrder::DURATIONS[$item->product_duration_snapshot] ?? ($item->product_duration_snapshot ?? ''),
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price_snapshot,
                    ])->values()->all();
                } else {
                    $prefilledItems = [[
                        'product_id' => $selectedOrder->product_id ?? '',
                        'description' => $selectedOrder->product_name_snapshot ?: ($selectedOrder->product_name ?? ''),
                        'duration' => \App\Models\SalesOrder::DURATIONS[$selectedOrder->product_duration_snapshot ?? ''] ?? ($selectedOrder->duration ?? ''),
                        'quantity' => 1,
                        'unit_price' => (float) ($selectedOrder->product_price_snapshot ?? $selectedOrder->product_value ?? 0),
                    ]];
                }
            }
            $oldItems = old('items', ! empty($prefilledItems) ? $prefilledItems : [[
                'product_id' => '',
                'description' => '',
                'duration' => '',
                'quantity' => 1,
                'unit_price' => 0,
            ]]);
            $prefillDiscount = isset($selectedOrder) && $selectedOrder
                ? max(0, (float) $selectedOrder->product_value - (float) $selectedOrder->closing_value)
                : 0;
        @endphp
        <div class="rounded-xl border border-neutral-200 bg-white p-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Customer *</label>
                <select name="customer_id" class="form-input" required>
                    <option value="">Select customer...</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected(old('customer_id', $selectedCustomerId ?? null) == $c->id)>{{ $c->name }} {{ $c->phone ? '('.$c->phone.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Linked Order (Optional)</label>
                <select name="order_id" class="form-input">
                    <option value="">None</option>
                    @foreach ($orders as $o)
                        <option value="{{ $o->id }}" @selected(old('order_id', $selectedOrder?->id) == $o->id)>{{ $o->order_number }} — {{ $o->customer_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Issue Date *</label>
                <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date', now()->addDays((int) ($billing['default_due_days'] ?? 14))->toDateString()) }}" class="form-input">
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-neutral-800">Products &amp; Services</h2>
                <button type="button" id="emp-add-item-btn" class="btn btn-secondary text-xs">+ Add Line Item</button>
            </div>

            <div class="table-wrapper">
                <table class="data-table w-full" id="emp-items-table">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left" style="width: 24%;">Product (Optional)</th>
                            <th class="px-2 py-2 text-left" style="width: 30%;">Description *</th>
                            <th class="px-2 py-2 text-left" style="width: 14%;">Duration</th>
                            <th class="px-2 py-2 text-right" style="width: 10%;">Qty *</th>
                            <th class="px-2 py-2 text-right" style="width: 14%;">Unit Price *</th>
                            <th class="px-2 py-2 text-right" style="width: 8%;">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="emp-items-body">
                        @php
                            $oldItems = old('items', [[
                                'product_id' => '',
                                'description' => '',
                                'duration' => '',
                                'quantity' => 1,
                                'unit_price' => 0,
                            ]]);
                        @endphp
                        @foreach ($oldItems as $idx => $item)
                            <tr class="emp-item-row">
                                <td class="p-2">
                                    <select name="items[{{ $idx }}][product_id]" class="form-input text-xs emp-product-select">
                                        <option value="">Custom Item</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ $p->price }}" data-duration="{{ $p->duration }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                {{ $p->name }} ({{ $cur }}{{ number_format((float) $p->price, 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2"><input type="text" name="items[{{ $idx }}][description]" value="{{ $item['description'] ?? '' }}" class="form-input text-xs emp-desc-input" placeholder="Description *" required></td>
                                <td class="p-2"><input type="text" name="items[{{ $idx }}][duration]" value="{{ $item['duration'] ?? '' }}" class="form-input text-xs emp-duration-input" placeholder="e.g. 1 Year"></td>
                                <td class="p-2"><input type="number" step="0.01" min="0.01" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" class="form-input text-xs text-right emp-qty-input" required></td>
                                <td class="p-2"><input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" class="form-input text-xs text-right emp-price-input" required></td>
                                <td class="p-2 text-right"><button type="button" class="text-red-600 text-xs emp-remove-row-btn">&times;</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div class="space-y-3">
                    <div>
                        <label class="form-label text-xs">Notes</label>
                        <textarea name="notes" rows="2" class="form-input text-xs">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="rounded-lg bg-neutral-50 border border-neutral-200 p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Discount Type</label>
                            <select name="discount_type" id="emp-discount-type" class="form-input text-xs">
                                <option value="fixed" @selected(old('discount_type', 'fixed') === 'fixed')>Fixed Amount</option>
                                <option value="percent" @selected(old('discount_type') === 'percent')>Percentage (%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Discount Value</label>
                            <input type="number" step="0.01" min="0" name="discount_value" id="emp-discount-value" value="{{ old('discount_value', 0) }}" class="form-input text-xs text-right">
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-xs">Tax Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="tax_rate" id="emp-tax-rate" value="{{ old('tax_rate', $billing['default_tax_rate'] ?? 0) }}" class="form-input text-xs text-right">
                    </div>
                    <div class="border-t border-neutral-200 pt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-neutral-500">Subtotal</span><span id="emp-preview-subtotal" class="font-medium tabular-nums">0.00</span></div>
                        <div class="flex justify-between"><span class="text-neutral-500">Discount</span><span id="emp-preview-discount" class="text-red-600 tabular-nums">-0.00</span></div>
                        <div class="flex justify-between"><span class="text-neutral-500">Tax</span><span id="emp-preview-tax" class="tabular-nums">0.00</span></div>
                        <div class="flex justify-between border-t border-neutral-300 pt-2 text-base font-bold"><span>Grand Total</span><span id="emp-preview-total" class="tabular-nums">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('employee.billing.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Invoice</button>
        </div>
    </form>

    <script>
        (function () {
            const tbody = document.getElementById('emp-items-body');
            const addBtn = document.getElementById('emp-add-item-btn');
            let rowIdx = tbody.querySelectorAll('.emp-item-row').length;

            function recalc() {
                let sub = 0;
                tbody.querySelectorAll('.emp-item-row').forEach(row => {
                    const q = parseFloat(row.querySelector('.emp-qty-input')?.value || 0);
                    const p = parseFloat(row.querySelector('.emp-price-input')?.value || 0);
                    sub += Math.max(0, q) * Math.max(0, p);
                });
                const dType = document.getElementById('emp-discount-type').value;
                const dVal = Math.max(0, parseFloat(document.getElementById('emp-discount-value').value || 0));
                const disc = dType === 'percent' ? sub * Math.min(100, dVal) / 100 : Math.min(sub, dVal);
                const taxRate = Math.max(0, Math.min(100, parseFloat(document.getElementById('emp-tax-rate').value || 0)));
                const tax = Math.max(0, sub - disc) * taxRate / 100;
                const grand = Math.max(0, sub - disc) + tax;

                document.getElementById('emp-preview-subtotal').textContent = sub.toFixed(2);
                document.getElementById('emp-preview-discount').textContent = '-' + disc.toFixed(2);
                document.getElementById('emp-preview-tax').textContent = tax.toFixed(2);
                document.getElementById('emp-preview-total').textContent = grand.toFixed(2);
            }

            function bindRow(row) {
                row.querySelector('.emp-product-select')?.addEventListener('change', function () {
                    const opt = this.options[this.selectedIndex];
                    if (opt && opt.value) {
                        row.querySelector('.emp-desc-input').value = opt.dataset.name || '';
                        row.querySelector('.emp-price-input').value = opt.dataset.price || 0;
                        row.querySelector('.emp-duration-input').value = opt.dataset.duration || '';
                        recalc();
                    }
                });
                row.querySelectorAll('.emp-qty-input, .emp-price-input').forEach(el => el.addEventListener('input', recalc));
                row.querySelector('.emp-remove-row-btn')?.addEventListener('click', () => {
                    if (tbody.querySelectorAll('.emp-item-row').length > 1) {
                        row.remove();
                        recalc();
                    }
                });
            }

            tbody.querySelectorAll('.emp-item-row').forEach(bindRow);
            addBtn.addEventListener('click', () => {
                const template = tbody.querySelector('.emp-item-row').cloneNode(true);
                template.querySelectorAll('input, select').forEach(el => {
                    if (el.name) el.name = el.name.replace(/items\[\d+\]/, 'items[' + rowIdx + ']');
                    if (el.tagName === 'SELECT') el.selectedIndex = 0;
                    else if (el.classList.contains('emp-qty-input')) el.value = 1;
                    else if (el.classList.contains('emp-price-input')) el.value = 0;
                    else el.value = '';
                });
                rowIdx++;
                tbody.appendChild(template);
                bindRow(template);
                recalc();
            });

            ['emp-discount-type', 'emp-discount-value', 'emp-tax-rate'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', recalc);
                document.getElementById(id)?.addEventListener('change', recalc);
            });
            recalc();
        })();
    </script>
@endsection
