<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates sales orders, wiring the atomic order number from
 * {@see OrderNumberService} into the same transaction that persists the
 * order itself, so a failure to claim the number never orphans a record.
 *
 * Catalog products are the single source of truth for a new order: when the
 * request carries a product_id, the order's name / price / duration
 * snapshots are read from the catalog row (current database values) and any
 * client-submitted price or duration is ignored. Only *active* products may
 * back a new order.
 */
class OrderService
{
    public function __construct(protected OrderNumberService $numbers)
    {
    }

    /**
     * Create a new order for a team, attributed to an employee.
     *
     * @param array<string, mixed> $data validated request data
     */
    public function createForTeam(Team $team, ?User $employee, array $data): SalesOrder
    {
        // Resolve the catalog product *before* claiming a sequence value,
        // so an unknown or inactive product never burns an order number.
        $product = $this->resolveProduct($data['product_id'] ?? null);

        return DB::transaction(function () use ($team, $employee, $data, $product) {
            $orderNumber = $this->numbers->next($team);

            $productName = $product ? $product->name : ($data['product_name'] ?? null);
            $productPrice = $product ? $product->price : ($data['product_value'] ?? 0);
            $productDuration = $product ? (string) $product->duration : ($data['duration'] ?? '1y');

            return SalesOrder::create([
                'order_number' => $orderNumber,
                'team_id' => $team->id,
                'team_code_snapshot' => $team->team_code,
                'assigned_employee_id' => $employee?->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'product_id' => $product?->id,
                'product_name' => $productName,
                'product_name_snapshot' => $productName,
                'product_price_snapshot' => $productPrice,
                'product_duration_snapshot' => $productDuration,
                'duration' => $productDuration,
                'product_value' => $productPrice,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'closing_value' => $data['closing_value'] ?? 0,
                'amount_received' => $data['amount_received'] ?? 0,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'pending',
                'order_status' => $data['order_status'] ?? 'pending',
            ]);

            SalesAnalyticsService::invalidateCache();

            return $order;
        });
    }

    /**
     * Resolve the submitted catalog product for a new order.
     *
     * Only *active* products may be selected; an unknown or inactive product
     * is rejected (422) so retired catalog entries can never flow into the
     * payment and subscription pipeline. The returned row is the
     * authoritative source for the order's name / price / duration
     * snapshots, which are frozen at creation so later catalog edits never
     * rewrite existing orders.
     *
     * @throws ValidationException
     */
    public function resolveProduct(?int $productId): ?Product
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

        if ($product->status !== 'active') {
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
     * Guard: only the order's team, that team's employees, or an admin may
     * act on an order. Throws a ValidationException (surfaced as 422) when
     * the actor has no access, so a team can never reach into another
     * team's orders and an employee can never bypass their team.
     *
     * @param Team|User|Admin|mixed $actor
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
            $isTeamMember = (int) ($actor->team_id ?? 0) === (int) ($order->team_id ?? 0);

            if (! $isOwner && ! $isTeamMember) {
                $this->deny('You may only access orders from your assigned team.');
            }

            return;
        }

        // Admin (or any explicitly authorized actor): unrestricted.
    }

    /**
     * @throws ValidationException
     */
    protected function deny(string $reason): void
    {
        throw ValidationException::withMessages(['order' => $reason]);
    }
}