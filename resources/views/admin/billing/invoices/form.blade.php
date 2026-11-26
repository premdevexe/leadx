@extends('layouts.admin-dashboard')

@section('title', $invoice ? 'Edit Invoice ' . $invoice->invoice_number : 'Create Invoice')

@php
    $currencyCode = $invoice->currency ?? ($billing['default_currency'] ?? \App\Models\Setting::currency());
    $cur = \App\Models\Setting::currencySymbolFor($currencyCode);
    $existingItems = $invoice ? $invoice->items->map(fn($i) => [
        'product_id' => $i->product_id,
        'description' => $i->description,
        'duration' => $i->duration,
        'quantity' => (float) $i->quantity,
        'unit_price' => (float) $i->unit_price,
    ])->values()->all() : (
        $selectedOrder ? ($selectedOrder->items->isNotEmpty()
            ? $selectedOrder->items->map(function ($i) {
                $dur = $i->product_duration_snapshot
                    ? (\App\Models\SalesOrder::DURATIONS[$i->product_duration_snapshot] ?? $i->product_duration_snapshot)
                    : null;

                return [
                    'product_id' => $i->product_id,
                    'description' => trim(($i->product_name_snapshot ?? 'Order Item').($dur ? ' ('.$dur.')' : '')),
                    'duration' => $dur ?? $i->product_duration_snapshot,
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                ];
            })->values()->all()
            : [[
                'product_id' => $selectedOrder->product_id,
                'description' => $selectedOrder->product_name_snapshot ?? $selectedOrder->product_name ?? 'Order Item',
                'duration' => $selectedOrder->duration,
                'quantity' => 1,
                'unit_price' => (float) $selectedOrder->closing_value,
            ]]
        ) : [[
            'product_id' => '',
            'description' => '',
            'duration' => '',
            'quantity' => 1,
            'unit_price' => 0,
        ]]
    );
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ $invoice ? 'Edit Invoice ' . $invoice->invoice_number : 'Create New Invoice' }}</h1>
            <p class="mt-0.5 text-sm text-neutral-500">All subtotal, discount, tax, and grand total calculations are verified server-side.</p>
        </div>
        <a href="{{ route('admin.billing.invoices.index') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; Back to Invoices</a>
    </div>

    <form method="POST" action="{{ $invoice ? route('admin.billing.invoices.update', $invoice) : route('admin.billing.invoices.store') }}" class="mt-5 space-y-5">
        @csrf
        @if ($invoice)
            @method('PUT')
        @endif

        <div class="rounded-xl border border-neutral-200 bg-white p-5 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="form-label">Customer *</label>
                <select name="customer_id" class="form-input" required>
                    <option value="">Select customer...</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected(old('customer_id', $selectedCustomerId) == $c->id)>
                            {{ $c->name }} {{ $c->phone ? '('.$c->phone.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Link to Order (Optional)</label>
                <select name="order_id" class="form-input">
                    <option value="">Standalone Invoice</option>
                    @foreach ($orders as $o)
                        <option value="{{ $o->id }}" @selected(old('order_id', $selectedOrder?->id) == $o->id)>
                            {{ $o->order_number }} — {{ $o->customer_name }} ({{ $cur }}{{ number_format((float) $o->closing_value, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-input">
                    <option value="draft" @selected(old('status', $invoice?->status ?? 'draft') === 'draft')>Save as Draft</option>
                    <option value="sent" @selected(old('status', $invoice?->status) === 'sent')>Publish (Sent)</option>
                </select>
            </div>
            <div>
                <label class="form-label">Issue Date *</label>
                <input type="date" name="issue_date" value="{{ old('issue_date', $invoice?->issue_date?->toDateString() ?? now()->toDateString()) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date', $invoice?->due_date?->toDateString() ?? now()->addDays((int) ($billing['default_due_days'] ?? 14))->toDateString()) }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Currency</label>
                <select name="currency" class="form-input">
                    @foreach (\App\Models\Setting::CURRENCIES as $code => $sym)
                        <option value="{{ $code }}" @selected(old('currency', $currencyCode) === $code)>{{ $code }} ({{ $sym }})</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="form-label">Customer Billing Address</label>
                <textarea name="customer_address" rows="2" class="form-input" placeholder="Optional street, city, state, postal code...">{{ old('customer_address', $invoice?->customer_address) }}</textarea>
            </div>
            <div class="sm:col-span-3">
                <label class="form-label mb-2">Choose Invoice Template Design</label>
                @php $selectedTemplate = old('template', $invoice?->template ?? ($billing['default_template'] ?? 'classic_navy')); @endphp
                @include('admin.billing.partials.template-selector', ['inputName' => 'template', 'selected' => $selectedTemplate])
            </div>
        </div>

        {{-- Line Items --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-neutral-800">Products &amp; Services</h2>
                <button type="button" id="add-item-btn" class="btn btn-secondary text-xs">+ Add Line Item</button>
            </div>

            <div class="table-wrapper">
                <table class="data-table w-full" id="items-table">
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
                    <tbody id="items-body">
                        @foreach ($existingItems as $idx => $item)
                            <tr class="item-row">
                                <td class="p-2">
                                    <select name="items[{{ $idx }}][product_id]" class="form-input text-xs product-select">
                                        <option value="">Custom Item</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ $p->price }}" data-duration="{{ $p->duration }}" @selected($item['product_id'] == $p->id)>
                                                {{ $p->name }} ({{ $cur }}{{ number_format((float) $p->price, 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-2"><input type="text" name="items[{{ $idx }}][description]" value="{{ $item['description'] }}" class="form-input text-xs desc-input" required></td>
                                <td class="p-2"><input type="text" name="items[{{ $idx }}][duration]" value="{{ $item['duration'] }}" class="form-input text-xs duration-input" placeholder="e.g. 1 Year"></td>
                                <td class="p-2"><input type="number" step="0.01" min="0.01" name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] }}" class="form-input text-xs text-right qty-input" required></td>
                                <td class="p-2"><input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_price]" value="{{ $item['unit_price'] }}" class="form-input text-xs text-right price-input" required></td>
                                <td class="p-2 text-right"><button type="button" class="text-red-600 text-xs remove-row-btn">&times;</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Discount, Tax & Live Summary --}}
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Payment Terms</label>
                        <input type="text" name="payment_terms" value="{{ old('payment_terms', $invoice?->payment_terms ?? ($billing['default_payment_terms'] ?? '')) }}" class="form-input text-xs">
                    </div>
                    <div>
                        <label class="form-label">Customer Notes (Visible on PDF)</label>
                        <textarea name="notes" rows="2" class="form-input text-xs">{{ old('notes', $invoice?->notes) }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">Internal Notes (Admin Only)</label>
                        <textarea name="internal_notes" rows="2" class="form-input text-xs">{{ old('internal_notes', $invoice?->internal_notes) }}</textarea>
                    </div>
                </div>

                <div class="rounded-lg bg-neutral-50 border border-neutral-200 p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">Discount Type</label>
                            <select name="discount_type" id="discount-type" class="form-input text-xs">
                                <option value="fixed" @selected(old('discount_type', $invoice?->discount_type ?? 'fixed') === 'fixed')>Fixed Amount</option>
                                <option value="percent" @selected(old('discount_type', $invoice?->discount_type) === 'percent')>Percentage (%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Discount Value</label>
                            <input type="number" step="0.01" min="0" name="discount_value" id="discount-value" value="{{ old('discount_value', $invoice?->discount_value ?? ($selectedOrder?->items->isNotEmpty() ? $selectedOrder->discount_amount : 0)) }}" class="form-input text-xs text-right">
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-xs">Tax Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="tax_rate" id="tax-rate" value="{{ old('tax_rate', $invoice?->tax_rate ?? ($billing['default_tax_rate'] ?? 0)) }}" class="form-input text-xs text-right">
                    </div>
                    <div class="border-t border-neutral-200 pt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-neutral-500">Subtotal</span><span id="preview-subtotal" class="font-medium tabular-nums">0.00</span></div>
                        <div class="flex justify-between"><span class="text-neutral-500">Discount</span><span id="preview-discount" class="text-red-600 tabular-nums">-0.00</span></div>
                        <div class="flex justify-between"><span class="text-neutral-500">Tax</span><span id="preview-tax" class="tabular-nums">0.00</span></div>
                        <div class="flex justify-between border-t border-neutral-300 pt-2 text-base font-bold"><span>Grand Total</span><span id="preview-total" class="tabular-nums">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.billing.invoices.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $invoice ? 'Update Invoice' : 'Save Invoice' }}</button>
        </div>
    </form>

    <script>
        (function () {
            const tbody = document.getElementById('items-body');
            const addBtn = document.getElementById('add-item-btn');
            let rowIdx = tbody.querySelectorAll('.item-row').length;

            function recalc() {
                let sub = 0;
                tbody.querySelectorAll('.item-row').forEach(row => {
                    const q = parseFloat(row.querySelector('.qty-input')?.value || 0);
                    const p = parseFloat(row.querySelector('.price-input')?.value || 0);
                    sub += Math.max(0, q) * Math.max(0, p);
                });
                const dType = document.getElementById('discount-type').value;
                const dVal = Math.max(0, parseFloat(document.getElementById('discount-value').value || 0));
                const disc = dType === 'percent' ? sub * Math.min(100, dVal) / 100 : Math.min(sub, dVal);
                const taxRate = Math.max(0, Math.min(100, parseFloat(document.getElementById('tax-rate').value || 0)));
                const tax = Math.max(0, sub - disc) * taxRate / 100;
                const grand = Math.max(0, sub - disc) + tax;

                document.getElementById('preview-subtotal').textContent = sub.toFixed(2);
                document.getElementById('preview-discount').textContent = '-' + disc.toFixed(2);
                document.getElementById('preview-tax').textContent = tax.toFixed(2);
                document.getElementById('preview-total').textContent = grand.toFixed(2);
            }

            function bindRow(row) {
                row.querySelector('.product-select')?.addEventListener('change', function () {
                    const opt = this.options[this.selectedIndex];
                    if (opt && opt.value) {
                        row.querySelector('.desc-input').value = opt.dataset.name || '';
                        row.querySelector('.price-input').value = opt.dataset.price || 0;
                        row.querySelector('.duration-input').value = opt.dataset.duration || '';
                        recalc();
                    }
                });
                row.querySelectorAll('.qty-input, .price-input').forEach(el => el.addEventListener('input', recalc));
                row.querySelector('.remove-row-btn')?.addEventListener('click', () => {
                    if (tbody.querySelectorAll('.item-row').length > 1) {
                        row.remove();
                        recalc();
                    }
                });
            }

            tbody.querySelectorAll('.item-row').forEach(bindRow);
            addBtn.addEventListener('click', () => {
                const template = tbody.querySelector('.item-row').cloneNode(true);
                template.querySelectorAll('input, select').forEach(el => {
                    if (el.name) el.name = el.name.replace(/items\[\d+\]/, 'items[' + rowIdx + ']');
                    if (el.tagName === 'SELECT') el.selectedIndex = 0;
                    else if (el.classList.contains('qty-input')) el.value = 1;
                    else if (el.classList.contains('price-input')) el.value = 0;
                    else el.value = '';
                });
                rowIdx++;
                tbody.appendChild(template);
                bindRow(template);
                recalc();
            });

            ['discount-type', 'discount-value', 'tax-rate'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', recalc);
                document.getElementById(id)?.addEventListener('change', recalc);
            });
            recalc();
        })();
    </script>
@endsection
