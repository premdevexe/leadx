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

        $order = DB::transaction(function () use ($team, $employee, $data, $product) {
            $orderNumber = $this->numbers->next($team);

            $productName = $product ? $product->name : ($data['product_name'] ?? null);
            $productPrice = $product ? $product->price : ($data['product_value'] ?? 0);
            $productDuration = $product ? (string) $product->duration : ($data['duration'] ?? '1y');
            $minClosingPrice = $product ? (float) ($product->minimum_closing_price ?? 0) : 0;
            $apiKeyRequired = $product ? (bool) ($product->api_key_required ?? false) : false;

            $closingValue = array_key_exists('closing_value', $data) && $data['closing_value'] !== ''
                ? (float) $data['closing_value']
                : (float) $productPrice;
            $discountAmount = round(max(0.0, (float) $productPrice - $closingValue), 2);

            $needsApproval = $minClosingPrice > 0 && $closingValue < $minClosingPrice;
            $closingStatus = $needsApproval ? 'Requested' : 'Accepted';

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
                'minimum_closing_price_snapshot' => $minClosingPrice,
                'api_key_required_snapshot' => $apiKeyRequired,
                'closing_value_status' => $closingStatus,
                'closing_approval_requested_at' => $needsApproval ? now() : null,
                'duration' => $productDuration,
                'product_value' => $productPrice,
                'discount_amount' => $discountAmount,
                'closing_value' => $closingValue,
                'amount_received' => $data['amount_received'] ?? 0,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'pending',
                'order_status' => $data['order_status'] ?? 'pending',
            ]);
        });

        SalesAnalyticsService::invalidateCache();

        app(\App\Services\NotificationHubService::class)->notifyOrderCreated($order);
        if ($order->closing_value_status === 'Requested') {
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalRequested($order);
        }

        // Email triggers (Section 5, all non-blocking — email failure
        // never breaks order creation; each event is admin-toggleable).
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
            // Customer order confirmation.
            app(\App\Services\EmailService::class)->dispatch('order.created', $context + ['to_email' => $order->customer_email]);
        } catch (\Throwable) {
        }

        try {
            // Team: new order notification to active team members.
            app(\App\Services\EmailService::class)->dispatch('team.order_created', $context + ['team' => $team, 'order' => $order]);
        } catch (\Throwable) {
        }

        try {
            // Assigned employee: personal order notification.
            if ($employee) {
                app(\App\Services\EmailService::class)->dispatch('employee.order_created', $context + ['order' => $order, 'employee' => $employee]);
            }
        } catch (\Throwable) {
        }

        try {
            // Admin: new order visibility.
            app(\App\Services\EmailService::class)->dispatch('admin.new_order', $context + ['order' => $order]);
        } catch (\Throwable) {
        }

        return $order;
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

            if (! $isOwner) {
                $this->deny('You may only access orders assigned directly to you.');
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