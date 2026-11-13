<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates sales orders, wiring the atomic order number from
 * {@see OrderNumberService} into the same transaction that persists the
 * order itself — so a failure to claim the number never orphans a record.
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
        return DB::transaction(function () use ($team, $employee, $data) {
            $orderNumber = $this->numbers->next($team);

            return SalesOrder::create([
                'order_number' => $orderNumber,
                'team_id' => $team->id,
                'team_code_snapshot' => $team->team_code,
                'assigned_employee_id' => $employee?->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'product_name' => $data['product_name'] ?? null,
                'subscription_plan' => $data['subscription_plan'] ?? null,
                'duration' => $data['duration'] ?? '1y',
                'product_value' => $data['product_value'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'closing_value' => $data['closing_value'] ?? 0,
                'amount_received' => $data['amount_received'] ?? 0,
                'payment_status' => $data['payment_status'] ?? 'pending',
                'order_status' => $data['order_status'] ?? 'pending',
            ]);
        });
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
