<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreCustomerIssueRequest;
use App\Http\Requests\Employee\UpdateCustomerIssueRequest;
use App\Models\CrmCustomerIssue;
use App\Services\CrmCustomerIssueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerIssuesController extends Controller
{
    public function __construct(private CrmCustomerIssueService $service) {}

    public function index(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $query = $this->service->getEmployeeIssuesQuery($employee);

        // Search by customer name
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        // Filter by status
        $status = $request->input('status');
        if ($status && in_array($status, CrmCustomerIssue::allStatuses(), true)) {
            $query->where('status', $status);
        }

        // Filter by date
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom) {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        // Sort by latest updated
        $issues = $query->orderByDesc('updated_at')->paginate(15)->appends($request->query());

        // Dashboard counts
        $countsQuery = $this->service->getEmployeeIssuesQuery($employee);
        $totalIssues = (clone $countsQuery)->count();
        $fixedIssues = (clone $countsQuery)->where('status', CrmCustomerIssue::STATUS_FIXED)->count();
        $stillFacingIssues = (clone $countsQuery)->where('status', CrmCustomerIssue::STATUS_STILL_FACING)->count();
        $customersAffected = (clone $countsQuery)->distinct('customer_id')->count('customer_id');

        $authorizedCustomerIds = $this->service->getAuthorizedCustomerIds($employee);

        return view('employee.customer_issues.index', [
            'issues' => $issues,
            'employee' => $employee,
            'totalIssues' => $totalIssues,
            'fixedIssues' => $fixedIssues,
            'stillFacingIssues' => $stillFacingIssues,
            'customersAffected' => $customersAffected,
            'search' => $search,
            'statusFilter' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'authorizedCustomerIds' => $authorizedCustomerIds,
        ]);
    }

    public function create(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $authorizedCustomerIds = $this->service->getAuthorizedCustomerIds($employee);

        return view('employee.customer_issues.create', [
            'employee' => $employee,
            'authorizedCustomerIds' => $authorizedCustomerIds,
        ]);
    }

    public function store(StoreCustomerIssueRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $this->service->create($employee, $request->validated());

        return redirect()->route('employee.customer-issues.index')
            ->with('status', 'Issue reported successfully.');
    }

    public function show(Request $request, CrmCustomerIssue $customer_issue): View|RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        if ((int) $customer_issue->employee_id !== (int) $employee->id) {
            return redirect()->route('employee.customer-issues.index')
                ->with('error', 'You are not authorized to view this issue.');
        }

        $issue = $customer_issue->load('customer', 'histories.employee');

        $authorizedCustomerIds = $this->service->getAuthorizedCustomerIds($employee);

        return view('employee.customer_issues.show', [
            'issue' => $issue,
            'employee' => $employee,
            'authorizedCustomerIds' => $authorizedCustomerIds,
        ]);
    }

    public function edit(Request $request, CrmCustomerIssue $customer_issue): View|RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        if ((int) $customer_issue->employee_id !== (int) $employee->id) {
            return redirect()->route('employee.customer-issues.index')
                ->with('error', 'You are not authorized to edit this issue.');
        }

        $issue = $customer_issue->load('customer');

        $authorizedCustomerIds = $this->service->getAuthorizedCustomerIds($employee);

        return view('employee.customer_issues.edit', [
            'issue' => $issue,
            'employee' => $employee,
            'authorizedCustomerIds' => $authorizedCustomerIds,
        ]);
    }

    public function update(UpdateCustomerIssueRequest $request, CrmCustomerIssue $customer_issue): RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        if ((int) $customer_issue->employee_id !== (int) $employee->id) {
            return redirect()->route('employee.customer-issues.index')
                ->with('error', 'You are not authorized to edit this issue.');
        }

        $validated = $request->validated();
        $updateNote = $validated['update_note'] ?? null;

        if ($updateNote === null && ($validated['status'] ?? null) !== $customer_issue->status) {
            $updateNote = 'Status updated.';
        }

        $this->service->update($employee, $customer_issue, $validated, $updateNote);

        return redirect()->route('employee.customer-issues.show', $customer_issue)
            ->with('status', 'Issue updated successfully.');
    }
}
