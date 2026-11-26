<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Product;
use App\Models\RefundStatus;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central sales order creation and edit service (single source of truth).
 *
 * Catalog products are resolved server-side: client-submitted prices,
 * durations, line totals, and order product_value totals are ignored when
 * backed by catalog products. Multi-item orders store one row per product in
 * `sales_order_items` (deduplicated by merging quantities) and mirror the
 * primary item onto legacy `sales_orders` columns for full backward
 * compatibility.
 */
class OrderService
{
    public function __construct(protected OrderNumberService $numbers)
    {
    }

    /**
     * Create a new order (single or multi-product) for a team.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForTeam(Team $team, ?User $employee, array $data): SalesOrder
    {
        $this->normalizePhone($data);

        // Resolve and validate all line items BEFORE claiming an order number
        // so invalid/inactive products or quantities never burn a sequence value.
        $resolved = $this->resolveLineItems($data);
        $items = $resolved['items'];
        $hasCatalogProduct = $resolved['has_catalog_product'];

        $productValueCents = 0;
        $minFloorCents = 0;
        $anyApiKeyRequired = false;
        foreach ($items as $item) {
            $productValueCents += $item['line_total_cents'];
            $minFloorCents += $item['min_floor_cents'];
            if ($item['api_key_required_snapshot']) {
                $anyApiKeyRequired = true;
            }
        }

        $productValueStr = Money::format($productValueCents);
        $minFloorStr = Money::format($minFloorCents);

        $hasExplicitClosing = array_key_exists('closing_value', $data)
            && $data['closing_value'] !== null
            && $data['closing_value'] !== '';

        $closingCents = $hasExplicitClosing
            ? Money::toCents($data['closing_value'])
            : $productValueCents;
        $closingValueStr = Money::format($closingCents);
        $discountStr = Money::format(max(0, $productValueCents - $closingCents));

        $isAdmin = (bool) ($data['is_admin'] ?? false);

        // Employee floor price rule against basket minimum closing sum
        if (! $isAdmin && $employee && $hasCatalogProduct && $minFloorCents > 0 && $closingCents < $minFloorCents) {
            throw ValidationException::withMessages([
                'closing_value' => "The closing value (\${$closingValueStr}) cannot be less than the minimum closing price of \${$minFloorStr}.",
            ]);
        }

        // Approval workflow: employee closing below catalog basket sum (or team below floor)
        $needsApproval = false;
        if (! $isAdmin) {
            if ($employee && $hasCatalogProduct && $closingCents < $productValueCents) {
                $needsApproval = true;
            } elseif (! $employee && $minFloorCents > 0 && $closingCents < $minFloorCents) {
                $needsApproval = true;
            }
        }
        $closingStatus = $needsApproval ? 'Requested' : 'Accepted';

        $first = $items[0];

        $order = DB::transaction(function () use (
            $team,
            $employee,
            $data,
            $items,
            $first,
            $productValueStr,
            $minFloorStr,
            $discountStr,
            $closingValueStr,
            $anyApiKeyRequired,
            $closingStatus,
            $needsApproval
        ) {
            $orderNumber = $this->numbers->next($team);

            // Legacy columns on sales_orders are derived mirrors from the first line item
            // so existing reports, exports, API consumers, and the mobile app continue working.
            $order = SalesOrder::create([
                'order_number' => $orderNumber,
                'team_id' => $team->id,
                'team_code_snapshot' => $team->team_code,
                'assigned_employee_id' => $employee?->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_country_code' => $data['customer_country_code'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'product_id' => $first['product_id'],
                'product_name' => $first['product_name_snapshot'],
                'product_name_snapshot' => $first['product_name_snapshot'],
                'product_price_snapshot' => $first['unit_price'],
                'product_duration_snapshot' => $first['product_duration_snapshot'],
                'minimum_closing_price_snapshot' => $minFloorStr,
                'api_key_required_snapshot' => $anyApiKeyRequired,
                'closing_value_status' => $closingStatus,
                'closing_approval_requested_at' => $needsApproval ? now() : null,
                'duration' => $first['product_duration_snapshot'],
                'product_value' => $productValueStr,
                'discount_amount' => $discountStr,
                'closing_value' => $closingValueStr,
                'amount_received' => $data['amount_received'] ?? 0,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'pending',
                'order_status' => $data['order_status'] ?? 'pending',
            ]);

            foreach ($items as $item) {
                SalesOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name_snapshot' => $item['product_name_snapshot'],
                    'product_price_snapshot' => $item['unit_price'],
                    'product_duration_snapshot' => $item['product_duration_snapshot'],
                    'minimum_closing_price_snapshot' => $item['min_closing_unit'],
                    'api_key_required_snapshot' => $item['api_key_required_snapshot'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order->load('items');
        });

        SalesAnalyticsService::invalidateCache();

        if (! ($data['skip_notifications'] ?? false)) {
            app(\App\Services\NotificationHubService::class)->notifyOrderCreated($order);
            if ($order->closing_value_status === 'Requested') {
                $order->closingValueHistories()->create([
                    'actor_type' => $employee ? 'employee' : 'team',
                    'actor_id' => $employee?->id ?? $team->id,
                    'status' => 'Requested',
                    'previous_value' => $productValueStr,
                    'proposed_value' => $order->closing_value,
                    'notes' => 'Initial discounted order submission requiring approval.',
                ]);
                app(\App\Services\NotificationHubService::class)->notifyClosingApprovalRequested($order);
            }

            $context = [
                'order_id'         => $order->id,
                'order_number'     => $order->order_number,
                'customer_name'    => $order->customer_name,
                'customer_email'   => $order->customer_email,
                'team_name'        => $team->team_name,
                'product_name'     => $order->product_name_snapshot ?? '—',
                'product_price'    => number_format((float) $order->product_price_snapshot, 2),
                'product_duration' => $order->product_duration_snapshot ?? '—',
                'closing_value'    => number_format((float) $order->closing_value, 2),
                'payment_method'   => $order->payment_method ?? '—',
                'payment_status'   => $order->payment_status ?? '—',
            ];

            try {
                app(\App\Services\EmailService::class)->dispatch('order.created', $context + ['to_email' => $order->customer_email]);
            } catch (\Throwable) {
            }

            try {
                app(\App\Services\EmailService::class)->dispatch('team.order_created', $context + ['team' => $team, 'order' => $order]);
            } catch (\Throwable) {
            }

            try {
                if ($employee) {
                    app(\App\Services\EmailService::class)->dispatch('employee.order_created', $context + ['order' => $order, 'employee' => $employee]);
                }
            } catch (\Throwable) {
            }

            try {
                app(\App\Services\EmailService::class)->dispatch('admin.new_order', $context + ['order' => $order]);
            } catch (\Throwable) {
            }
        }

        return $order;
    }

    /**
     * Admin order update with item-edit locking, server-side recomputation,
     * and OrderEditHistory audit logging.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFromAdmin(SalesOrder $order, array $data, int $adminId, string $editReason = 'Order updated via Admin Panel'): SalesOrder
    {
        $this->normalizePhone($data);

        $hasItemsInput = ! empty($data['items']) && is_array($data['items']);
        $itemsPayloadProvided = $hasItemsInput || array_key_exists('product_id', $data) || array_key_exists('quantity', $data);

        $existingItems = $order->items()->get();
        $oldSignature = $this->itemsSignature($existingItems);

        $resolvedItems = null;
        if ($hasItemsInput) {
            $resolvedItems = $this->resolveLineItems(['items' => $data['items']], allowInactiveExistingFor: $existingItems->pluck('product_id')->filter()->all())['items'];
        } elseif ($itemsPayloadProvided && ($data['product_id'] ?? null) !== null) {
            $resolvedItems = $this->resolveLineItems([
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'] ?? 1,
            ], allowInactiveExistingFor: $existingItems->pluck('product_id')->filter()->all())['items'];
        } elseif ($itemsPayloadProvided && empty($data['product_id']) && ! empty($data['product_name'])) {
            $resolvedItems = $this->resolveLineItems($data)['items'];
        }

        $itemsChanged = false;
        if ($resolvedItems !== null) {
            $newSignature = collect($resolvedItems)->map(fn ($i) => [
                'product_id' => $i['product_id'],
                'name' => $i['product_name_snapshot'],
                'quantity' => (int) $i['quantity'],
                'unit_price' => (string) $i['unit_price'],
                'duration' => (string) ($i['product_duration_snapshot'] ?? '1y'),
            ])->values()->all();
            $itemsChanged = $oldSignature !== $newSignature;
        }

        if ($itemsChanged && $this->isItemEditLocked($order)) {
            throw ValidationException::withMessages([
                'items' => 'Order line items cannot be modified once verified payments exist or the order is cancelled/finalized.',
            ]);
        }

        return DB::transaction(function () use ($order, $data, $adminId, $editReason, $resolvedItems, $itemsChanged, $existingItems) {
            $updateFields = [
                'assigned_employee_id' => $data['assigned_employee_id'] ?? $order->assigned_employee_id,
                'customer_name' => $data['customer_name'] ?? $order->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $order->customer_phone,
                'customer_country_code' => $data['customer_country_code'] ?? $order->customer_country_code,
                'customer_email' => $data['customer_email'] ?? $order->customer_email,
                'payment_status' => $data['payment_status'] ?? $order->payment_status,
                'order_status' => $data['order_status'] ?? $order->order_status,
                'amount_received' => array_key_exists('amount_received', $data) && $data['amount_received'] !== null
                    ? $data['amount_received']
                    : $order->amount_received,
            ];

            if ($resolvedItems !== null && $itemsChanged) {
                $productValueCents = 0;
                $minFloorCents = 0;
                $anyApiKeyRequired = false;
                foreach ($resolvedItems as $item) {
                    $productValueCents += $item['line_total_cents'];
                    $minFloorCents += $item['min_floor_cents'];
                    if ($item['api_key_required_snapshot']) {
                        $anyApiKeyRequired = true;
                    }
                }
                $first = $resolvedItems[0];

                $productValStr = Money::format($productValueCents);
                $closingVal = (isset($data['closing_value']) && $data['closing_value'] !== '' && $data['closing_value'] !== null)
                    ? (float) $data['closing_value']
                    : (float) $productValStr;
                $closingCents = Money::toCents($closingVal);
                $discountStr = Money::format(max(0, $productValueCents - $closingCents));

                // Derived mirrors from first line item
                $updateFields['product_id'] = $first['product_id'];
                $updateFields['product_name'] = $first['product_name_snapshot'];
                $updateFields['product_name_snapshot'] = $first['product_name_snapshot'];
                $updateFields['product_price_snapshot'] = $first['unit_price'];
                $updateFields['product_duration_snapshot'] = $first['product_duration_snapshot'];
                $updateFields['minimum_closing_price_snapshot'] = Money::format($minFloorCents);
                $updateFields['api_key_required_snapshot'] = $anyApiKeyRequired;
                $updateFields['duration'] = $first['product_duration_snapshot'];
                $updateFields['product_value'] = $productValStr;
                $updateFields['closing_value'] = Money::format($closingCents);
                $updateFields['discount_amount'] = $discountStr;

                $this->auditItemChanges($order, $existingItems, $resolvedItems, $adminId, $editReason);

                $order->items()->delete();
                foreach ($resolvedItems as $item) {
                    SalesOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name_snapshot' => $item['product_name_snapshot'],
                        'product_price_snapshot' => $item['unit_price'],
                        'product_duration_snapshot' => $item['product_duration_snapshot'],
                        'minimum_closing_price_snapshot' => $item['min_closing_unit'],
                        'api_key_required_snapshot' => $item['api_key_required_snapshot'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['line_total'],
                    ]);
                }
            } else {
                // Scalar pricing updates when line items themselves were not edited
                $productVal = (isset($data['product_value']) && $data['product_value'] !== '' && $data['product_value'] !== null)
                    ? (float) $data['product_value']
                    : (float) ($order->product_value ?? 0);
                $closingVal = (isset($data['closing_value']) && $data['closing_value'] !== '' && $data['closing_value'] !== null)
                    ? (float) $data['closing_value']
                    : ((float) ($order->closing_value ?? 0) > 0 ? (float) $order->closing_value : $productVal);

                $updateFields['product_value'] = Money::format(Money::toCents($productVal));
                $updateFields['closing_value'] = Money::format(Money::toCents($closingVal));
                $updateFields['discount_amount'] = Money::format(max(0, Money::toCents($productVal) - Money::toCents($closingVal)));

                if (isset($data['duration']) && $existingItems->count() <= 1) {
                    $updateFields['duration'] = $data['duration'];
                    $updateFields['product_duration_snapshot'] = $data['duration'];
                }
                if (array_key_exists('product_name', $data) && $existingItems->isEmpty()) {
                    $updateFields['product_name'] = $data['product_name'];
                    $updateFields['product_name_snapshot'] = $data['product_name'];
                }
            }

            $order->fill($updateFields);
            $dirty = $order->getDirty();

            foreach ($dirty as $field => $newValue) {
                if ($field === 'updated_at') {
                    continue;
                }
                $oldValue = $order->getOriginal($field);
                $order->editHistories()->create([
                    'admin_id' => $adminId,
                    'field_name' => $field,
                    'old_value' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
                    'new_value' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
                    'reason' => $editReason,
                ]);
            }

            $order->save();
            SalesAnalyticsService::invalidateCache();

            return $order->fresh(['items', 'team', 'assignedEmployee', 'subscription', 'product']);
        });
    }

    /**
     * Resolve and merge line items from either `items[]` or legacy single `product_id`.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $allowInactiveExistingFor
     * @return array{items: list<array<string, mixed>>, has_catalog_product: bool}
     */
    public function resolveLineItems(array $data, array $allowInactiveExistingFor = []): array
    {
        $rawItems = [];

        if (! empty($data['items']) && is_array($data['items'])) {
            $rawItems = $data['items'];
        } elseif (! empty($data['product_id'])) {
            $rawItems = [[
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'] ?? 1,
            ]];
        }

        if (! empty($rawItems)) {
            $merged = [];
            foreach ($rawItems as $idx => $row) {
                if (! is_array($row) || empty($row['product_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.product_id" => 'A valid product is required for each line item.',
                    ]);
                }

                $qtyRaw = $row['quantity'] ?? 1;
                if (! is_numeric($qtyRaw) || (int) $qtyRaw != $qtyRaw || (int) $qtyRaw < 1) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.quantity" => 'Quantity must be an integer of at least 1.',
                    ]);
                }
                $qty = (int) $qtyRaw;

                $product = $this->resolveProduct(
                    (int) $row['product_id'],
                    in_array((int) $row['product_id'], $allowInactiveExistingFor, true)
                );

                $submittedDuration = isset($row['duration']) && array_key_exists((string) $row['duration'], SalesOrder::DURATIONS)
                    ? (string) $row['duration']
                    : null;

                if (isset($merged[$product->id])) {
                    $merged[$product->id]['quantity'] += $qty;
                    if ($submittedDuration !== null) {
                        $merged[$product->id]['duration'] = $submittedDuration;
                    }
                } else {
                    $merged[$product->id] = [
                        'product' => $product,
                        'quantity' => $qty,
                        'duration' => $submittedDuration ?? (string) ($product->duration ?: '1y'),
                    ];
                }
            }

            $items = [];
            foreach ($merged as $entry) {
                /** @var Product $product */
                $product = $entry['product'];
                $qty = $entry['quantity'];
                $durationCode = $entry['duration'];

                $unitCents = Money::toCents($product->price);
                $lineCents = $unitCents * $qty;
                $minUnitCents = Money::toCents($product->minimum_closing_price ?? 0);
                $minFloorCents = $minUnitCents * $qty;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'product_duration_snapshot' => $durationCode,
                    'unit_price' => Money::format($unitCents),
                    'line_total' => Money::format($lineCents),
                    'line_total_cents' => $lineCents,
                    'min_closing_unit' => Money::format($minUnitCents),
                    'min_floor_cents' => $minFloorCents,
                    'api_key_required_snapshot' => (bool) ($product->api_key_required ?? false),
                    'quantity' => $qty,
                ];
            }

            return ['items' => $items, 'has_catalog_product' => true];
        }

        // Manual / uncataloged order fallback (legacy compatibility)
        $qtyRaw = $data['quantity'] ?? 1;
        if (! is_numeric($qtyRaw) || (int) $qtyRaw != $qtyRaw || (int) $qtyRaw < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be an integer of at least 1.',
            ]);
        }
        $qty = (int) $qtyRaw;

        $manualPrice = $data['product_value'] ?? ($data['closing_value'] ?? 0);
        $unitCents = Money::toCents($manualPrice);
        $lineCents = $unitCents * $qty;

        return [
            'items' => [[
                'product_id' => null,
                'product_name_snapshot' => $data['product_name'] ?? null,
                'product_duration_snapshot' => (string) ($data['duration'] ?? '1y'),
                'unit_price' => Money::format($unitCents),
                'line_total' => Money::format($lineCents),
                'line_total_cents' => $lineCents,
                'min_closing_unit' => '0.00',
                'min_floor_cents' => 0,
                'api_key_required_snapshot' => false,
                'quantity' => $qty,
            ]],
            'has_catalog_product' => false,
        ];
    }

    /**
     * Paid, partially-paid, cancelled, or finally-refunded orders cannot have their line items edited.
     */
    public function isItemEditLocked(SalesOrder $order): bool
    {
        if (in_array($order->order_status, ['cancelled'], true)) {
            return true;
        }

        if (in_array($order->payment_status, ['paid', 'partially_paid', 'refunded', 'partially_refunded'], true)) {
            return true;
        }

        if ((float) $order->amount_received > 0) {
            return true;
        }

        $hasVerifiedPayments = $order->payments()
            ->where(fn ($q) => $q->whereNotNull('verified_by_admin_id')->orWhere('verification_source', 'gateway'))
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->exists();

        if ($hasVerifiedPayments) {
            return true;
        }

        $finalSlugs = RefundStatus::where('is_final', true)->pluck('slug')->all();
        if (! empty($finalSlugs) && $order->refunds()->whereIn('status', $finalSlugs)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the submitted catalog product for an order.
     *
     * @throws ValidationException
     */
    public function resolveProduct(?int $productId, bool $allowInactive = false): ?Product
    {
        if ($productId === null) {
            return null;
        }

        $product = Product::find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product does not exist.',
            ]);
        }

        if (! $allowInactive && $product->status !== 'active') {
            throw ValidationException::withMessages([
                'product_id' => 'Only active products can be used for a new order.',
            ]);
        }

        return $product;
    }

    /**
     * The active catalog products available for order selection, by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function activeProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('status', 'active')->orderBy('name')->get();
    }

    /**
     * Guard: only the order's team, that team's employees, or an admin may act on an order.
     *
     * @param  Team|User|Admin|mixed  $actor
     */
    public function assertCanAccess(SalesOrder $order, mixed $actor): void
    {
        if ($actor instanceof Team) {
            if ((int) $order->team_id !== (int) $actor->id) {
                $this->deny('Orders from another team may not be accessed.');
            }

            return;
        }

        if ($actor instanceof User) {
            $isOwner = (int) ($order->assigned_employee_id ?? 0) === (int) $actor->id;

            if (! $isOwner) {
                $this->deny('You may only access orders assigned directly to you.');
            }

            return;
        }
    }

    /**
     * Normalize split country code + local phone fields into E.164 `customer_phone`.
     *
     * @param  array<string, mixed>  $data
     */
    public function normalizePhone(array &$data): void
    {
        if (! array_key_exists('customer_phone_local', $data) && ! array_key_exists('customer_country_code', $data)) {
            return;
        }

        $local = $data['customer_phone_local'] ?? ($data['customer_phone'] ?? null);
        $cc = $data['customer_country_code'] ?? null;

        if ($local && ! $cc && str_starts_with((string) $local, '+')) {
            $data['customer_phone'] = $local;
        } elseif ($local) {
            $digits = preg_replace('/\D/', '', (string) $local);
            $data['customer_phone'] = $cc ? '+' . preg_replace('/\D/', '', (string) $cc) . ltrim($digits, '0') : $local;
        } elseif (array_key_exists('customer_phone_local', $data)) {
            $data['customer_phone'] = null;
        }

        unset($data['customer_phone_local']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SalesOrderItem>  $items
     * @return list<array<string, mixed>>
     */
    protected function itemsSignature($items): array
    {
        return $items->map(fn (SalesOrderItem $i) => [
            'product_id' => $i->product_id,
            'name' => $i->product_name_snapshot,
            'quantity' => (int) $i->quantity,
            'unit_price' => (string) $i->unit_price,
            'duration' => (string) ($i->product_duration_snapshot ?? '1y'),
        ])->values()->all();
    }

    /**
     * Write granular OrderEditHistory entries for line item additions, removals, and quantity changes.
     *
     * @param  \Illuminate\Support\Collection<int, SalesOrderItem>  $oldItems
     * @param  list<array<string, mixed>>  $newItems
     */
    protected function auditItemChanges(SalesOrder $order, $oldItems, array $newItems, int $adminId, string $reason): void
    {
        $oldByKey = $oldItems->keyBy(fn (SalesOrderItem $i) => $i->product_id ? 'p:'.$i->product_id : 'm:'.$i->product_name_snapshot);
        $newByKey = collect($newItems)->keyBy(fn (array $i) => $i['product_id'] ? 'p:'.$i['product_id'] : 'm:'.$i['product_name_snapshot']);

        foreach ($oldByKey as $key => $oldItem) {
            if (! $newByKey->has($key)) {
                $order->editHistories()->create([
                    'admin_id' => $adminId,
                    'field_name' => 'item_removed',
                    'old_value' => ($oldItem->product_name_snapshot ?? 'Item').' x'.$oldItem->quantity,
                    'new_value' => null,
                    'reason' => $reason,
                ]);
            } else {
                $newItem = $newByKey->get($key);
                if ((int) $oldItem->quantity !== (int) $newItem['quantity']) {
                    $order->editHistories()->create([
                        'admin_id' => $adminId,
                        'field_name' => 'item_quantity',
                        'old_value' => ($oldItem->product_name_snapshot ?? 'Item').' x'.$oldItem->quantity,
                        'new_value' => ($newItem['product_name_snapshot'] ?? 'Item').' x'.$newItem['quantity'],
                        'reason' => $reason,
                    ]);
                }
            }
        }

        foreach ($newByKey as $key => $newItem) {
            if (! $oldByKey->has($key)) {
                $order->editHistories()->create([
                    'admin_id' => $adminId,
                    'field_name' => 'item_added',
                    'old_value' => null,
                    'new_value' => ($newItem['product_name_snapshot'] ?? 'Item').' x'.$newItem['quantity'],
                    'reason' => $reason,
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    protected function deny(string $reason): void
    {
        throw ValidationException::withMessages(['order' => $reason]);
    }
}
