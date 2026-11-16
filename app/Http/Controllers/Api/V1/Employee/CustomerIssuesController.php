<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeCustomerIssueRequest;
use App\Http\Requests\Employee\UpdateEmployeeCustomerIssueRequest;
use App\Http\Resources\CustomerIssueResource;
use App\Models\CrmCustomerIssue;
use App\Models\User;
use App\Services\CrmCustomerIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CRM customer issues — mirrors the employee web panel's Customer Issues
 * page, reusing the exact business rules of {@see CrmCustomerIssueService}
 * (customers must be verified-order customers of the employee, only the
 * owning employee may read/update their issues, status changes are
 * history-tracked).
 */
class CustomerIssuesController extends Controller
{
    use EmployeeScoped;

    public function __construct(protected CrmCustomerIssueService $issues)
    {
    }

    /**
     * GET /api/v1/employee/customer-issues
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $query = $this->issues->getEmployeeIssuesQuery($employee);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $status = $request->input('status');
        if ($status && in_array($status, CrmCustomerIssue::allStatuses(), true)) {
            $query->where('status', $status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issue_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('issue_date', '<=', $request->input('date_to'));
        }

        $issues = $query->orderByDesc('updated_at')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        // Dashboard-style counts for the panel's summary chips.
        $countsQuery = $this->issues->getEmployeeIssuesQuery($employee);

        return response()->json([
            'meta' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'total_issues' => (clone $countsQuery)->count(),
                'fixed_issues' => (clone $countsQuery)->where('status', CrmCustomerIssue::STATUS_FIXED)->count(),
                'still_facing_issues' => (clone $countsQuery)->where('status', CrmCustomerIssue::STATUS_STILL_FACING)->count(),
                'customers_affected' => (clone $countsQuery)->distinct('customer_id')->count('customer_id'),
            ],
            'data' => CustomerIssueResource::collection($issues->items())->resolve(),
        ]);
    }

    /**
     * POST /api/v1/employee/customer-issues
     */
    public function store(StoreEmployeeCustomerIssueRequest $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $issue = $this->issues->create($employee, $request->validated());

        return (new CustomerIssueResource($issue->load('customer', 'histories.employee')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/employee/customer-issues/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $issue = CrmCustomerIssue::with(['customer', 'histories.employee'])->findOrFail($id);

        if ((int) $issue->employee_id !== (int) $employee->id) {
            throw ValidationException::withMessages([
                'issue' => 'You are not authorized to view this issue.',
            ]);
        }

        return response()->json(['data' => new CustomerIssueResource($issue)]);
    }

    /**
     * PUT /api/v1/employee/customer-issues/{id}
     */
    public function update(UpdateEmployeeCustomerIssueRequest $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $issue = CrmCustomerIssue::findOrFail($id);

        if ((int) $issue->employee_id !== (int) $employee->id) {
            throw ValidationException::withMessages([
                'issue' => 'You are not authorized to update this issue.',
            ]);
        }

        $validated = $request->validated();
        $updateNote = $validated['update_note'] ?? null;

        if ($updateNote === null && ($validated['status'] ?? null) !== null && $validated['status'] !== $issue->status) {
            $updateNote = 'Status updated.';
        }

        $updated = $this->issues->update($employee, $issue, $validated, $updateNote);

        return response()->json([
            'message' => 'Issue updated successfully.',
            'data' => new CustomerIssueResource($updated->load('customer', 'histories.employee')),
        ]);
    }
}
