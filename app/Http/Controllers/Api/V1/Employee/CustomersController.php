<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee CRM customers — mirrors the web panel's Customers page: the
 * employee's own customers (by order phone) or, with an active grant, the
 * team's customers in the grant window. Read-only.
 */
class CustomersController extends Controller
{
    use EmployeeScoped;

    /**
     * GET /api/v1/employee/customers
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $resolved = $this->resolveView($employee, $request->input('view'));

        $customers = $this->customerQueryForView($employee, $resolved['view'], $resolved['grant'])
            ->orderByDesc('created_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => $this->listMeta($resolved, $customers),
            'data' => CustomerResource::collection($customers->items())->resolve(),
        ]);
    }

    /**
     * GET /api/v1/employee/customers/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $customer = Customer::findOrFail($id);

        // A customer is only reachable through one of the employee's views:
        // their own orders, or (with a grant) the team grant window.
        $resolved = $this->resolveView($employee, $request->input('view'));
        $query = $this->customerQueryForView($employee, $resolved['view'], $resolved['grant']);

        if (! $query->whereKey($customer->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer' => 'Customer not found or not accessible.',
            ]);
        }

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    /**
     * The customers an employee can reach in a given view (mirrors the web
     * panel's customer listing per view).
     */
    protected function customerQueryForView(User $employee, string $view, ?\App\Models\EmployeeTeamAccessGrant $grant): \Illuminate\Database\Eloquent\Builder
    {
        $orderQuery = \DB::table('sales_orders');

        if ($view === 'team_data' && $grant && $employee->team_id) {
            $orderQuery->where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $grant->start_date)
                ->whereDate('created_at', '<=', $grant->end_date);
        } else {
            $orderQuery->where('assigned_employee_id', $employee->id);
        }

        $phones = $orderQuery->distinct()->pluck('customer_phone');

        return Customer::whereIn('phone', $phones);
    }
}
