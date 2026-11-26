@php
    $instanceId = $instanceId ?? 'oib';
    $currencySymbol = $currencySymbol ?? \App\Models\Setting::currencySymbol();
    $locked = (bool) ($locked ?? false);
    $canBypassFloor = (bool) ($canBypassFloor ?? false);
    $renderClosingControl = (bool) ($renderClosingControl ?? false);
    $initialClosingValue = $initialClosingValue ?? old('closing_value', 0);
    $durationsMap = \App\Models\SalesOrder::DURATIONS;

    $catalogById = [];
    foreach ($products as $p) {
        $durCode = (string) ($p->duration ?? '1y');
        $catalogById[(int) $p->id] = [
            'id' => (int) $p->id,
            'name' => (string) $p->name,
            'price' => round((float) $p->price, 2),
            'floor' => $p->minimum_closing_price !== null ? round((float) $p->minimum_closing_price, 2) : 0.0,
            'duration_code' => $durCode,
            'duration_label' => $durationsMap[$durCode] ?? '1 year',
            'api_key_required' => (bool) ($p->api_key_required ?? false),
            'inactive' => false,
        ];
    }

    $normalizedExisting = [];
    foreach (($existingItems ?? []) as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $qty = max(1, (int) ($row['quantity'] ?? 1));
        if (isset($catalogById[$pid])) {
            $item = $catalogById[$pid];
            $item['quantity'] = $qty;
            if (isset($row['unit_price'])) {
                $item['price'] = round((float) $row['unit_price'], 2);
            }
            if (isset($row['floor'])) {
                $item['floor'] = round((float) $row['floor'], 2);
            }
            if (! empty($row['duration']) && isset($durationsMap[$row['duration']])) {
                $item['duration_code'] = (string) $row['duration'];
                $item['duration_label'] = $durationsMap[$row['duration']];
            } elseif (! empty($row['duration_code']) && isset($durationsMap[$row['duration_code']])) {
                $item['duration_code'] = (string) $row['duration_code'];
                $item['duration_label'] = $durationsMap[$row['duration_code']];
            }
        } else {
            $durCode = (string) ($row['duration_code'] ?? '1y');
            $item = [
                'id' => $pid,
                'name' => (string) ($row['name'] ?? ('Product #' . $pid)),
                'price' => round((float) ($row['unit_price'] ?? 0), 2),
                'floor' => round((float) ($row['floor'] ?? 0), 2),
                'duration_code' => $durCode,
                'duration_label' => $durationsMap[$durCode] ?? '1 year',
                'api_key_required' => (bool) ($row['api_key_required'] ?? false),
                'inactive' => true,
                'quantity' => $qty,
            ];
            $catalogById[$pid] = $item;
        }
        $normalizedExisting[] = $item;
    }

    if (empty($normalizedExisting) && ! empty($legacyProductId)) {
        $lpid = (int) $legacyProductId;
        if (isset($catalogById[$lpid])) {
            $item = $catalogById[$lpid];
            $item['quantity'] = max(1, (int) ($legacyQuantity ?? 1));
            $normalizedExisting[] = $item;
        }
    }
@endphp

<style>
    #{{ $instanceId }}-root .oib-control-h {
        height: 2.5rem;
    }
    #{{ $instanceId }}-root .oib-row-select {
        height: 2.25rem;
        min-width: 8.5rem;
    }
    @media (max-width: 767px) {
        #{{ $instanceId }}-root .oib-table thead {
            display: none;
        }
        #{{ $instanceId }}-root .oib-table,
        #{{ $instanceId }}-root .oib-table tbody,
        #{{ $instanceId }}-root .oib-table tr,
        #{{ $instanceId }}-root .oib-table td {
            display: block;
            width: 100%;
        }
        #{{ $instanceId }}-root .oib-table tr {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e5e5;
        }
        #{{ $instanceId }}-root .oib-table tr:last-child {
            border-bottom: 0;
        }
        #{{ $instanceId }}-root .oib-table td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.3rem 0;
            text-align: right !important;
        }
        #{{ $instanceId }}-root .oib-table td::before {
            content: attr(data-label);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #737373;
            text-align: left;
            flex-shrink: 0;
        }
        #{{ $instanceId }}-root .oib-table td[data-label="Product"] {
            display: block;
            text-align: left !important;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #f0f0f0;
            margin-bottom: 0.25rem;
        }
        #{{ $instanceId }}-root .oib-table td[data-label="Product"]::before {
            display: none;
        }
        #{{ $instanceId }}-root .oib-row-select {
            width: auto;
            max-width: 11rem;
        }
    }
</style>

<div id="{{ $instanceId }}-root"
     class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 sm:p-5 space-y-4"
     data-oib-root
     data-oib-locked="{{ $locked ? '1' : '0' }}"
     data-oib-bypass-floor="{{ $canBypassFloor ? '1' : '0' }}"
     data-oib-currency="{{ $currencySymbol }}">

    {{-- Section Header --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="space-y-0.5">
            <div class="flex items-center gap-2">
                <h4 class="text-sm font-semibold text-neutral-900">Products &amp; Quantities</h4>
                <span class="inline-flex items-center rounded-full bg-neutral-900/5 border border-neutral-200 px-2.5 py-0.5 text-xs font-medium text-neutral-700" data-oib-count>
                    {{ count($normalizedExisting) }} {{ count($normalizedExisting) === 1 ? 'product' : 'products' }}
                </span>
            </div>
            <p class="text-xs text-neutral-500">
                @if ($locked)
                    Line items are locked because verified payments, cancellation, or refunds exist on this order.
                @else
                    Select one or more catalog products. Unit prices, floors, and durations auto-load and are verified server-side.
                @endif
            </p>
        </div>

        @if ($locked)
            <span class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">
                Locked
            </span>
        @endif
    </div>

    {{-- Search & Catalog Picker --}}
    @if (! $locked)
        <div class="relative" data-oib-picker-wrap>
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-neutral-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text"
                           id="{{ $instanceId }}-search"
                           autocomplete="off"
                           placeholder="Search catalog products by name (e.g., CRM Pro, WhatsApp)..."
                           class="form-input oib-control-h w-full bg-white pl-9 pr-9 text-sm"
                           data-oib-search>
                    <span class="hidden absolute inset-y-0 right-3 flex items-center text-neutral-400" data-oib-search-loading>
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                        </svg>
                    </span>
                </div>
                <button type="button"
                        class="btn btn-outline oib-control-h shrink-0 bg-white px-4 text-xs font-medium whitespace-nowrap"
                        data-oib-toggle-catalog>
                    Browse Catalog
                </button>
            </div>

            <div class="mt-1.5 max-h-60 overflow-y-auto rounded-lg border border-neutral-200 bg-white shadow-sm divide-y divide-neutral-100 hidden"
                 data-oib-dropdown>
                @forelse ($products as $p)
                    @php
                        $pDur = \App\Models\SalesOrder::DURATIONS[$p->duration] ?? '1 year';
                        $pFloor = $p->minimum_closing_price !== null ? (float) $p->minimum_closing_price : 0.0;
                    @endphp
                    <button type="button"
                            class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-neutral-50 flex items-center justify-between gap-3 transition"
                            data-oib-option
                            data-product-id="{{ $p->id }}"
                            data-product-name="{{ strtolower($p->name) }}">
                        <span class="min-w-0">
                            <span class="font-medium text-sm text-neutral-900 block truncate">{{ $p->name }}</span>
                            <span class="text-xs text-neutral-500">
                                Duration: {{ $pDur }}
                                @if (! $canBypassFloor && $pFloor > 0)
                                    · Min Floor: {{ $currencySymbol }}{{ number_format($pFloor, 2) }}
                                @endif
                            </span>
                        </span>
                        <span class="shrink-0 flex items-center gap-2.5">
                            <span class="font-semibold text-sm tabular-nums text-neutral-900">{{ $currencySymbol }}{{ number_format((float) $p->price, 2) }}</span>
                            <span class="rounded-md bg-neutral-900 px-2.5 py-1 text-xs font-medium text-white" data-oib-option-badge="{{ $p->id }}">+ Add</span>
                        </span>
                    </button>
                @empty
                    <div class="px-4 py-4 text-center text-xs text-neutral-500">No active products found in catalog.</div>
                @endforelse
                <div class="px-4 py-4 text-center text-xs text-neutral-500 hidden" data-oib-no-match>
                    No catalog products match your search.
                </div>
            </div>
        </div>
    @endif

    {{-- Line Items Table (Desktop) / Card Stack (Mobile) --}}
    <div class="rounded-lg border border-neutral-200 bg-white overflow-hidden" data-oib-table-wrap>
        <table class="oib-table w-full text-left text-xs">
            <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-600 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="px-3.5 py-2.5">Product</th>
                    <th class="px-3.5 py-2.5 w-44">Subscription Duration</th>
                    <th class="px-3.5 py-2.5 text-right w-32">Unit Price</th>
                    <th class="px-3.5 py-2.5 text-center w-36">Quantity</th>
                    <th class="px-3.5 py-2.5 text-right w-32">Line Total</th>
                    @if (! $locked)
                        <th class="px-3.5 py-2.5 text-right w-20">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100" data-oib-rows>
                @if ($locked)
                    @forelse ($normalizedExisting as $row)
                        <tr>
                            <td class="px-3.5 py-3" data-label="Product">
                                <span class="text-sm font-medium text-neutral-900">{{ $row['name'] }}</span>
                            </td>
                            <td class="px-3.5 py-3 text-neutral-700" data-label="Subscription Duration">{{ $row['duration_label'] }}</td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-neutral-700" data-label="Unit Price">{{ $currencySymbol }}{{ number_format($row['price'], 2) }}</td>
                            <td class="px-3.5 py-3 text-center tabular-nums font-medium" data-label="Quantity">{{ $row['quantity'] }}</td>
                            <td class="px-3.5 py-3 text-right tabular-nums font-semibold text-neutral-900" data-label="Line Total">{{ $currencySymbol }}{{ number_format($row['price'] * $row['quantity'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-neutral-500">Manual / legacy single-product order.</td>
                        </tr>
                    @endforelse
                @endif
            </tbody>
        </table>
        <div class="px-4 py-6 text-center {{ empty($normalizedExisting) && ! $locked ? '' : 'hidden' }}" data-oib-empty>
            <p class="text-sm font-medium text-neutral-700">No catalog products selected</p>
            <p class="mt-1 text-xs text-neutral-500">Search above or click <strong>Browse Catalog</strong> to add products to this order.</p>
        </div>
    </div>

    {{-- Authoritative Pricing Summary --}}
    @if ($renderClosingControl)
        <div class="rounded-lg border border-neutral-200 bg-white p-4 space-y-3" data-oib-pricing-summary>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 pb-2.5">
                <h5 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Pricing Summary</h5>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-600" data-oib-pricing-status>
                    Add a product or enter custom pricing
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-3 items-start">
                {{-- Catalog Subtotal (Neutral) --}}
                <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
                    <span class="block text-xs text-neutral-500">Catalog Subtotal</span>
                    <strong class="mt-0.5 block text-base font-semibold tabular-nums text-neutral-800" data-oib-subtotal>{{ $currencySymbol }}0.00</strong>
                </div>

                {{-- Minimum Floor (Warning / Secondary) --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50/70 px-3.5 py-2.5">
                    <span class="block text-xs text-amber-800">Minimum Floor</span>
                    <strong class="mt-0.5 block text-base font-semibold tabular-nums text-amber-900" data-oib-floor>{{ $currencySymbol }}0.00</strong>
                </div>

                {{-- Final Closing Value (Prominent, single authoritative input) --}}
                <div class="rounded-lg border border-neutral-300 bg-white px-3.5 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <label for="closing-value" class="text-xs font-semibold text-neutral-900">
                            Final Closing Value ({{ $currencySymbol }}) <span class="text-red-500">*</span>
                        </label>
                        @if (! $locked)
                            <button type="button"
                                    class="hidden text-[11px] font-medium text-neutral-600 underline hover:text-neutral-900"
                                    data-oib-reset-closing>
                                Match Subtotal
                            </button>
                        @endif
                    </div>
                    <input type="number"
                           step="0.01"
                           min="0"
                           id="closing-value"
                           name="closing_value"
                           value="{{ $initialClosingValue }}"
                           required
                           class="form-input mt-1 h-9 w-full font-semibold text-base tabular-nums text-neutral-900">
                </div>
            </div>

            <div class="text-xs" data-oib-discount-hint></div>
            @error('closing_value')
                <p class="form-error text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @else
        {{-- Compact Subtotal + Floor bar for Admin views where Closing Value lives in the main financial grid --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-white px-3.5 py-2.5 text-xs">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <span class="text-neutral-500">Catalog Subtotal:</span>
                    <strong class="ml-1 font-semibold tabular-nums text-neutral-900" data-oib-subtotal>{{ $currencySymbol }}0.00</strong>
                </div>
                @if (! $canBypassFloor)
                    <div class="hidden" data-oib-floor-wrap>
                        <span class="text-neutral-500">Minimum Floor:</span>
                        <strong class="ml-1 font-medium tabular-nums text-amber-700" data-oib-floor>{{ $currencySymbol }}0.00</strong>
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-neutral-500" data-oib-discount-hint></span>
                @if (! $locked)
                    <button type="button"
                            class="hidden rounded border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100"
                            data-oib-reset-closing>
                        Match Subtotal
                    </button>
                @endif
            </div>
        </div>
    @endif

    @error('items')
        <p class="form-error text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error('items.*.product_id')
        <p class="form-error text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error('items.*.quantity')
        <p class="form-error text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error('items.*.duration')
        <p class="form-error text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

@if (! $locked)
<script>
(function () {
    const root = document.getElementById(@json($instanceId . '-root'));
    if (!root || root.dataset.oibInitialized === '1') return;
    root.dataset.oibInitialized = '1';

    const cur = root.dataset.oibCurrency || @json($currencySymbol);
    const canBypassFloor = root.dataset.oibBypassFloor === '1';
    const catalog = @json($catalogById);
    const durationsMap = @json($durationsMap);
    let rows = @json($normalizedExisting).map(r => Object.assign({}, r));

    const form = root.closest('form');
    const searchInput = root.querySelector('[data-oib-search]');
    const searchLoadingEl = root.querySelector('[data-oib-search-loading]');
    const toggleBtn = root.querySelector('[data-oib-toggle-catalog]');
    const dropdown = root.querySelector('[data-oib-dropdown]');
    const noMatchEl = root.querySelector('[data-oib-no-match]');
    const tbody = root.querySelector('[data-oib-rows]');
    const emptyEl = root.querySelector('[data-oib-empty]');
    const countEl = root.querySelector('[data-oib-count]');
    const subtotalEl = root.querySelector('[data-oib-subtotal]');
    const floorWrapEl = root.querySelector('[data-oib-floor-wrap]');
    const floorEl = root.querySelector('[data-oib-floor]');
    const statusBadgeEl = root.querySelector('[data-oib-pricing-status]');
    const hintEl = root.querySelector('[data-oib-discount-hint]');
    const resetClosingBtn = root.querySelector('[data-oib-reset-closing]');

    const manualWrap = form ? form.querySelector('[data-oib-manual-fallback]') : null;
    const productValueInput = form ? form.querySelector('[name="product_value"]') : null;
    const closingValueInput = form ? (form.querySelector('#closing-value') || form.querySelector('[name="closing_value"]')) : null;
    const productNameInput = form ? form.querySelector('[name="product_name"]') : null;
    const durationSelectInput = form ? form.querySelector('select[name="duration"]') : null;
    const legacyProductSelect = form ? form.querySelector('[name="product_id"]') : null;

    let lastAutoTotal = null;
    let closingManuallyEdited = false;
    let filterTimer = null;

    if (closingValueInput) {
        const initVal = parseFloat(closingValueInput.value || '0');
        const initBasketTotal = rows.reduce((s, r) => s + r.price * r.quantity, 0);
        if (initVal > 0 && Math.abs(initVal - initBasketTotal) > 0.009) {
            closingManuallyEdited = true;
        }
        closingValueInput.addEventListener('input', () => {
            const current = parseFloat(closingValueInput.value || '0');
            if (lastAutoTotal !== null && Math.abs(current - lastAutoTotal) > 0.009) {
                closingManuallyEdited = true;
            }
            updateDiscountHint();
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function computeTotals() {
        let subtotalCents = 0;
        let floorCents = 0;
        let totalQty = 0;
        rows.forEach(r => {
            const q = Math.max(1, parseInt(r.quantity, 10) || 1);
            subtotalCents += Math.round(r.price * 100) * q;
            floorCents += Math.round((r.floor || 0) * 100) * q;
            totalQty += q;
        });
        return {
            subtotal: subtotalCents / 100,
            floor: floorCents / 100,
            totalQty,
        };
    }

    function updatePricingStatus(subtotal, floor, closing) {
        if (resetClosingBtn) {
            resetClosingBtn.classList.toggle('hidden', !(rows.length > 0 && Math.abs(closing - subtotal) >= 0.01));
        }

        if (rows.length === 0) {
            if (statusBadgeEl) {
                statusBadgeEl.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-600';
                statusBadgeEl.textContent = 'Custom / Manual Mode';
            }
            if (hintEl) hintEl.textContent = '';
            if (closingValueInput) {
                closingValueInput.classList.remove('border-red-400', 'focus:border-red-500');
            }
            return;
        }

        const belowFloor = !canBypassFloor && floor > 0 && closing < floor - 0.009;
        const discounted = !belowFloor && closing < subtotal - 0.009;

        if (belowFloor) {
            if (statusBadgeEl) {
                statusBadgeEl.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700';
                statusBadgeEl.textContent = 'Below Minimum Floor';
            }
            if (hintEl) {
                hintEl.innerHTML = '<span class="text-red-600 font-medium">Closing value cannot be below the minimum floor of ' + cur + floor.toFixed(2) + '.</span>';
            }
            if (closingValueInput) {
                closingValueInput.classList.add('border-red-400', 'focus:border-red-500');
            }
        } else if (discounted) {
            const disc = subtotal - closing;
            if (statusBadgeEl) {
                statusBadgeEl.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800';
                statusBadgeEl.textContent = 'Discount — Approval Required';
            }
            if (hintEl) {
                hintEl.innerHTML = '<span class="text-amber-700 font-medium">Discount of ' + cur + disc.toFixed(2) + ' applied (requires Super Admin approval).</span>';
            }
            if (closingValueInput) {
                closingValueInput.classList.remove('border-red-400', 'focus:border-red-500');
            }
        } else {
            if (statusBadgeEl) {
                statusBadgeEl.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700';
                statusBadgeEl.textContent = 'Valid Pricing (Accepted)';
            }
            if (hintEl) {
                hintEl.innerHTML = '<span class="text-emerald-700">Matches full catalog value.</span>';
            }
            if (closingValueInput) {
                closingValueInput.classList.remove('border-red-400', 'focus:border-red-500');
            }
        }
    }

    function updateDiscountHint() {
        const { subtotal, floor } = computeTotals();
        const closing = closingValueInput ? (parseFloat(closingValueInput.value || '0') || 0) : subtotal;
        updatePricingStatus(subtotal, floor, closing);
    }

    resetClosingBtn?.addEventListener('click', () => {
        if (!closingValueInput) return;
        const { subtotal } = computeTotals();
        closingManuallyEdited = false;
        closingValueInput.value = subtotal.toFixed(2);
        lastAutoTotal = subtotal;
        updateDiscountHint();
        closingValueInput.dispatchEvent(new Event('input', { bubbles: true }));
    });

    function syncOptionBadges() {
        if (!dropdown) return;
        dropdown.querySelectorAll('[data-oib-option-badge]').forEach(badge => {
            const pid = Number(badge.dataset.oibOptionBadge);
            const existing = rows.find(r => Number(r.id) === pid);
            if (existing) {
                badge.textContent = 'Added (×' + existing.quantity + ')';
                badge.className = 'rounded-md bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-medium text-emerald-700';
            } else {
                badge.textContent = '+ Add';
                badge.className = 'rounded-md bg-neutral-900 px-2.5 py-1 text-xs font-medium text-white';
            }
        });
    }

    function syncParentFields(shouldSyncClosing) {
        const { subtotal, floor } = computeTotals();

        if (rows.length > 0) {
            if (manualWrap) {
                manualWrap.classList.add('hidden');
            }
            if (legacyProductSelect) {
                legacyProductSelect.value = '';
            }
            if (productValueInput) {
                productValueInput.value = subtotal.toFixed(2);
            }
            if (productNameInput && !productNameInput.value) {
                productNameInput.value = rows[0].name;
            }
            if (durationSelectInput && rows[0].duration_code && durationsMap[rows[0].duration_code]) {
                durationSelectInput.value = rows[0].duration_code;
            }
            if (closingValueInput && shouldSyncClosing) {
                const curClosing = parseFloat(closingValueInput.value || '0');
                if (!closingManuallyEdited || curClosing === 0 || (lastAutoTotal !== null && Math.abs(curClosing - lastAutoTotal) < 0.01)) {
                    closingValueInput.value = subtotal.toFixed(2);
                }
            }
            lastAutoTotal = subtotal;
        } else {
            if (manualWrap) {
                manualWrap.classList.remove('hidden');
            }
            if (shouldSyncClosing) {
                if (productValueInput && !legacyProductSelect?.value) {
                    productValueInput.value = '0.00';
                }
                if (closingValueInput && !closingManuallyEdited) {
                    closingValueInput.value = '0.00';
                }
            }
            lastAutoTotal = 0;
        }

        if (countEl) {
            const lineCount = rows.length;
            countEl.textContent = lineCount + (lineCount === 1 ? ' product' : ' products');
        }
        if (subtotalEl) {
            subtotalEl.textContent = cur + subtotal.toFixed(2);
        }
        if (floorEl) {
            floorEl.textContent = cur + floor.toFixed(2);
        }
        if (floorWrapEl) {
            floorWrapEl.classList.toggle('hidden', !(floor > 0 && rows.length > 0));
        }

        syncOptionBadges();
        updateDiscountHint();

        root.dispatchEvent(new CustomEvent('order-items:changed', {
            bubbles: true,
            detail: { rows, subtotal, floor },
        }));
    }

    function renderRows(shouldSyncClosing) {
        tbody.innerHTML = '';
        if (rows.length === 0) {
            emptyEl?.classList.remove('hidden');
        } else {
            emptyEl?.classList.add('hidden');
        }

        rows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            const lineTotal = (row.price * row.quantity).toFixed(2);
            const floorHtml = (!canBypassFloor && row.floor > 0)
                ? '<span class="block text-[11px] text-amber-700">Floor: ' + cur + row.floor.toFixed(2) + '</span>'
                : '';

            let durationOptions = '';
            Object.entries(durationsMap).forEach(([code, label]) => {
                const sel = code === row.duration_code ? ' selected' : '';
                durationOptions += '<option value="' + escapeHtml(code) + '"' + sel + '>' + escapeHtml(label) + '</option>';
            });

            tr.innerHTML =
                '<td class="px-3.5 py-3 align-middle" data-label="Product">' +
                    '<input type="hidden" name="items[' + idx + '][product_id]" value="' + row.id + '">' +
                    '<div class="text-sm font-medium text-neutral-900">' + escapeHtml(row.name) +
                        (row.inactive ? ' <span class="text-xs font-normal text-amber-600">(archived)</span>' : '') +
                    '</div>' +
                '</td>' +
                '<td class="px-3.5 py-3 align-middle" data-label="Subscription Duration">' +
                    '<select name="items[' + idx + '][duration]" data-oib-duration="' + idx + '" class="form-select oib-row-select w-full bg-white text-xs">' +
                        durationOptions +
                    '</select>' +
                '</td>' +
                '<td class="px-3.5 py-3 text-right align-middle tabular-nums text-neutral-800" data-label="Unit Price">' +
                    '<span class="font-medium">' + cur + row.price.toFixed(2) + '</span>' +
                    floorHtml +
                '</td>' +
                '<td class="px-3.5 py-3 text-center align-middle" data-label="Quantity">' +
                    '<div class="inline-flex h-9 items-center rounded-md border border-neutral-300 bg-white">' +
                        '<button type="button" class="h-full px-2.5 text-neutral-700 hover:bg-neutral-100 font-semibold" data-oib-dec="' + idx + '" aria-label="Decrease quantity">&minus;</button>' +
                        '<input type="number" min="1" step="1" name="items[' + idx + '][quantity]" value="' + row.quantity + '" ' +
                               'class="w-12 h-full border-x border-neutral-200 text-center text-xs font-medium tabular-nums focus:outline-none" data-oib-qty="' + idx + '">' +
                        '<button type="button" class="h-full px-2.5 text-neutral-700 hover:bg-neutral-100 font-semibold" data-oib-inc="' + idx + '" aria-label="Increase quantity">+</button>' +
                    '</div>' +
                '</td>' +
                '<td class="px-3.5 py-3 text-right align-middle tabular-nums text-sm font-semibold text-neutral-900" data-label="Line Total" data-oib-line-total="' + idx + '">' +
                    cur + lineTotal +
                '</td>' +
                '<td class="px-3.5 py-3 text-right align-middle" data-label="Actions">' +
                    '<button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 bg-red-50/70 text-red-600 hover:bg-red-100 transition" data-oib-remove="' + idx + '" aria-label="Remove ' + escapeHtml(row.name) + '" title="Remove product">' +
                        '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />' +
                        '</svg>' +
                    '</button>' +
                '</td>';
            tbody.appendChild(tr);
        });

        syncParentFields(shouldSyncClosing);
    }

    function addProduct(productId) {
        const prod = catalog[String(productId)];
        if (!prod) return;
        const existing = rows.find(r => Number(r.id) === Number(prod.id));
        if (existing) {
            existing.quantity = Math.max(1, parseInt(existing.quantity, 10) || 1) + 1;
        } else {
            rows.push(Object.assign({}, prod, { quantity: 1 }));
        }
        renderRows(true);
    }

    function filterDropdown() {
        if (!dropdown) return;
        const term = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;
        dropdown.querySelectorAll('[data-oib-option]').forEach(btn => {
            const match = !term || (btn.dataset.productName || '').includes(term);
            btn.classList.toggle('hidden', !match);
            if (match) visibleCount++;
        });
        if (noMatchEl) {
            noMatchEl.classList.toggle('hidden', visibleCount > 0);
        }
    }

    if (searchInput && dropdown) {
        searchInput.addEventListener('focus', () => {
            filterDropdown();
            dropdown.classList.remove('hidden');
        });
        searchInput.addEventListener('input', () => {
            searchLoadingEl?.classList.remove('hidden');
            clearTimeout(filterTimer);
            filterTimer = setTimeout(() => {
                filterDropdown();
                dropdown.classList.remove('hidden');
                searchLoadingEl?.classList.add('hidden');
            }, 90);
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                dropdown.classList.add('hidden');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const firstVisible = dropdown.querySelector('[data-oib-option]:not(.hidden)');
                if (firstVisible) {
                    addProduct(firstVisible.dataset.productId);
                    searchInput.value = '';
                    filterDropdown();
                }
            }
        });
    }

    toggleBtn?.addEventListener('click', () => {
        if (!dropdown) return;
        filterDropdown();
        dropdown.classList.toggle('hidden');
    });

    dropdown?.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-oib-option]');
        if (!opt) return;
        addProduct(opt.dataset.productId);
        if (searchInput) searchInput.value = '';
        dropdown.classList.add('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target) && dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    });

    tbody.addEventListener('click', (e) => {
        const dec = e.target.closest('[data-oib-dec]');
        if (dec) {
            const idx = parseInt(dec.dataset.oibDec, 10);
            if (rows[idx]) {
                rows[idx].quantity = Math.max(1, (parseInt(rows[idx].quantity, 10) || 1) - 1);
                renderRows(true);
            }
            return;
        }
        const inc = e.target.closest('[data-oib-inc]');
        if (inc) {
            const idx = parseInt(inc.dataset.oibInc, 10);
            if (rows[idx]) {
                rows[idx].quantity = (parseInt(rows[idx].quantity, 10) || 1) + 1;
                renderRows(true);
            }
            return;
        }
        const rem = e.target.closest('[data-oib-remove]');
        if (rem) {
            const idx = parseInt(rem.dataset.oibRemove, 10);
            rows.splice(idx, 1);
            renderRows(true);
        }
    });

    tbody.addEventListener('change', (e) => {
        const durSelect = e.target.closest('[data-oib-duration]');
        if (!durSelect) return;
        const idx = parseInt(durSelect.dataset.oibDuration, 10);
        if (!rows[idx]) return;
        rows[idx].duration_code = durSelect.value;
        rows[idx].duration_label = durationsMap[durSelect.value] || durSelect.value;
        if (idx === 0 && durationSelectInput && durationsMap[durSelect.value]) {
            durationSelectInput.value = durSelect.value;
        }
    });

    tbody.addEventListener('input', (e) => {
        const qtyInput = e.target.closest('[data-oib-qty]');
        if (!qtyInput) return;
        const idx = parseInt(qtyInput.dataset.oibQty, 10);
        if (!rows[idx]) return;
        const parsed = parseInt(qtyInput.value, 10);
        const validQty = (!isNaN(parsed) && parsed >= 1) ? parsed : 1;
        rows[idx].quantity = validQty;
        const lineTotalEl = tbody.querySelector('[data-oib-line-total="' + idx + '"]');
        if (lineTotalEl) {
            lineTotalEl.textContent = cur + (rows[idx].price * validQty).toFixed(2);
        }
        syncParentFields(true);
    });

    renderRows(false);
})();
</script>
@endif
