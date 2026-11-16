<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee payments — mirrors the web panel's Payments page: the employee's
 * own payments or, with an active grant, the team's payments in the grant
 * window. Read-only (verification/rejection stay admin-only).
 */
class PaymentsController extends Controller
{
    use EmployeeScoped;

    /**
     * GET /api/v1/employee/payments
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $resolved = $this->resolveView($employee, $request->input('view'));
        $orderIds = $this->scopedOrderIds($employee, $resolved['view'], $resolved['grant']);

        $payments = Payment::whereIn('order_id', $orderIds)
            ->with(['order.team', 'order.product', 'verifiedByAdmin'])
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => $this->listMeta($resolved, $payments),
            'data' => PaymentResource::collection($payments->items())->resolve(),
        ]);
    }

    /**
     * GET /api/v1/employee/payments/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $payment = Payment::with(['order.team', 'order.product', 'verifiedByAdmin'])->findOrFail($id);

        // Reachable only when the payment's order is within the employee's
        // scoping (own order or team grant window).
        $resolved = $this->resolveView($employee, $request->input('view'));
        $inScope = $this->scopedOrderIds($employee, $resolved['view'], $resolved['grant'])
            ->contains($payment->order_id);

        if (! $inScope) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment' => 'Payment not found or not accessible.',
            ]);
        }

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    /**
     * The order ids the employee's payments list is scoped to, per view.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function scopedOrderIds(User $employee, string $view, ?EmployeeTeamAccessGrant $grant)
    {
        $query = SalesOrder::query();

        if ($view === 'team_data' && $grant && $employee->team_id) {
            $query->where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $grant->start_date)
                ->whereDate('created_at', '<=', $grant->end_date);
        } else {
            $query->where('assigned_employee_id', $employee->id);
        }

        return $query->pluck('id');
    }
}
