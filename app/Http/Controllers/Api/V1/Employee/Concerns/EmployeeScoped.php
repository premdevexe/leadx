<?php

namespace App\Http\Controllers\Api\V1\Employee\Concerns;

use App\Models\EmployeeTeamAccessGrant;
use App\Models\FollowUp;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared scoping for the employee panel REST API.
 *
 * Every read in the employee panel honours two views that match the web
 * panel exactly:
 *
 * - `my_data`    — records the employee owns directly
 *                  (orders/subscriptions/follow-ups assigned to them).
 * - `team_data`  — team records, but only within the employee's ACTIVE
 *                  Team Data access grant window (a grant an admin issued).
 *                  Without an active grant, team_data is never honored: the
 *                  view is silently downgraded to my_data and a warning is
 *                  surfaced, exactly like the web panel's redirect.
 *
 * Writes are always owned by the employee's own identity. No view ever
 * crosses team boundaries, and no endpoint trusts an id sent from the
 * client for the employee itself.
 */
trait EmployeeScoped
{
    /**
     * The authenticated employee, resolved from the Sanctum token.
     *
     * @throws ValidationException
     */
    protected function currentEmployee(Request $request): User
    {
        /** @var User|null $employee */
        $employee = $request->user('sanctum');

        if (! $employee instanceof User) {
            throw ValidationException::withMessages([
                'employee' => 'Only an individual employee may use this endpoint.',
            ]);
        }

        if (($employee->role ?? 'employee') !== 'employee') {
            throw ValidationException::withMessages([
                'employee' => 'This token is not an employee account.',
            ]);
        }

        return $employee;
    }

    /**
     * The employee's active team-data grant, when they have one.
     */
    protected function activeTeamGrant(User $employee): ?EmployeeTeamAccessGrant
    {
        $today = now()->startOfDay()->toDateString();

        return EmployeeTeamAccessGrant::where('user_id', $employee->id)
            ->whereNull('revoked_at')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();
    }

    /**
     * Resolve the requested view, downgrading to my_data when team access is
     * unavailable. Returns the view, the grant (for team_data), and an
     * optional warning string to echo back to the client.
     *
     * @return array{view: string, grant: ?EmployeeTeamAccessGrant, warning: ?string}
     */
    protected function resolveView(User $employee, ?string $requestedView): array
    {
        if ($requestedView === 'team_data') {
            if (! $employee->team_id) {
                return [
                    'view' => 'my_data',
                    'grant' => null,
                    'warning' => 'You are not assigned to a team.',
                ];
            }

            $grant = $this->activeTeamGrant($employee);

            return ['view' => 'team_data', 'grant' => $grant, 'warning' => null];
        }

        return ['view' => 'my_data', 'grant' => null, 'warning' => null];
    }

    /**
     * Orders visible to the employee for the resolved view.
     */
    protected function scopedOrders(User $employee, string $view, ?EmployeeTeamAccessGrant $grant): Builder
    {
        $query = SalesOrder::query();

        // Table-prefixed columns: callers may join `payments` onto this
        // query (e.g. the dashboard's verified-revenue stat), where the bare
        // names would become ambiguous.
        if ($view === 'team_data' && $employee->team_id) {
            $query->where('sales_orders.team_id', $employee->team_id);
            if ($grant) {
                $query->whereDate('sales_orders.created_at', '>=', $grant->start_date)
                    ->whereDate('sales_orders.created_at', '<=', $grant->end_date);
            }
        } else {
            $query->where('sales_orders.assigned_employee_id', $employee->id);
        }

        return $query;
    }

    /**
     * Subscriptions visible to the employee for the resolved view.
     */
    protected function scopedSubscriptions(User $employee, string $view, ?EmployeeTeamAccessGrant $grant): Builder
    {
        $query = Subscription::query()->with(['order.product', 'order.team', 'order.assignedEmployee', 'plan']);

        if ($view === 'team_data' && $employee->team_id) {
            $query->whereHas('order', function ($q) use ($employee, $grant): void {
                $q->where('team_id', $employee->team_id);
                if ($grant) {
                    $q->whereDate('created_at', '>=', $grant->start_date)
                        ->whereDate('created_at', '<=', $grant->end_date);
                }
            });
        } else {
            $query->whereHas('order', function ($q) use ($employee): void {
                $q->where('assigned_employee_id', $employee->id);
            });
        }

        return $query;
    }

    /**
     * Follow-ups visible to the employee for the resolved view.
     */
    protected function scopedFollowUps(User $employee, string $view, ?EmployeeTeamAccessGrant $grant): Builder
    {
        $query = FollowUp::query()->with(['customer', 'team', 'assignedEmployee', 'order']);

        if ($view === 'team_data' && $employee->team_id) {
            $orderQuery = SalesOrder::where('team_id', $employee->team_id);
            if ($grant) {
                $orderQuery->whereDate('created_at', '>=', $grant->start_date)
                    ->whereDate('created_at', '<=', $grant->end_date);
            }
            $orderIds = $orderQuery->pluck('id');

            $query->whereIn('order_id', $orderIds);
        } else {
            $query->where('assigned_employee_id', $employee->id);
        }

        return $query;
    }

    /**
     * Assert the employee can access a single order (ownership or an active
     * team grant covering the order's date). Mirrors the web panel's show.
     *
     * @return bool whether the order is owned (false = view-only team access)
     *
     * @throws ValidationException
     */
    protected function assertOrderAccess(User $employee, SalesOrder $order): bool
    {
        if ((int) $order->assigned_employee_id === (int) $employee->id) {
            return true;
        }

        $grant = $this->activeTeamGrant($employee);

        if ($grant
            && $employee->team_id
            && (int) $order->team_id === (int) $employee->team_id
        ) {
            $orderDate = $order->created_at->toDateString();

            if ($orderDate >= $grant->start_date->toDateString()
                && $orderDate <= $grant->end_date->toDateString()
            ) {
                return false; // view-only, via team grant
            }
        }

        throw ValidationException::withMessages([
            'order' => 'You may only access orders assigned to you, or team orders inside your grant window.',
        ]);
    }

    /**
     * Assert a single order belongs to the employee and is owned (not just
     * team-viewable). Used for mutating endpoints (complete, resubmit, key).
     *
     * @throws ValidationException
     */
    protected function assertOwnedOrder(User $employee, SalesOrder $order): void
    {
        if (! $this->assertOrderAccess($employee, $order)) {
            throw ValidationException::withMessages([
                'order' => 'You may only modify orders assigned directly to you.',
            ]);
        }
    }

    /**
     * Standardized list metadata (view + pagination) for list responses.
     *
     * @param array<string, mixed> $resolved
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     *
     * @return array<string, mixed>
     */
    protected function listMeta(array $resolved, $paginator): array
    {
        return [
            'view' => $resolved['view'],
            'warning' => $resolved['warning'],
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
