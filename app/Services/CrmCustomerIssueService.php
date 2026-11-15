<?php

namespace App\Services;

use App\Models\CrmCustomerIssue;
use App\Models\CrmCustomerIssueHistory;
use App\Models\User;

class CrmCustomerIssueService
{
    public function create(User $employee, array $data): CrmCustomerIssue
    {
        $data['employee_id'] = $employee->id;

        if (! isset($data['status']) || ! in_array($data['status'], CrmCustomerIssue::allStatuses(), true)) {
            $data['status'] = CrmCustomerIssue::STATUS_STILL_FACING;
        }

        if (empty($data['issue_date'])) {
            $data['issue_date'] = now()->toDateString();
        }

        $issue = CrmCustomerIssue::create($data);

        CrmCustomerIssueHistory::create([
            'issue_id' => $issue->id,
            'employee_id' => $employee->id,
            'old_status' => null,
            'new_status' => $issue->status,
            'update_note' => 'Issue created.',
            'created_at' => $issue->created_at ?? now(),
        ]);

        return $issue;
    }

    public function update(User $employee, CrmCustomerIssue $issue, array $data, ?string $updateNote = null): CrmCustomerIssue
    {
        if ((int) $issue->employee_id !== (int) $employee->id) {
            throw new \InvalidArgumentException('Employee does not own this issue.');
        }

        $oldStatus = $issue->status;
        $newStatus = $data['status'] ?? $oldStatus;
        $statusChanged = $newStatus !== $oldStatus;

        $issue->fill([
            'customer_id' => $data['customer_id'] ?? $issue->customer_id,
            'issue_title' => $data['issue_title'] ?? $issue->issue_title,
            'issue_description' => $data['issue_description'] ?? $issue->issue_description,
            'issue_date' => $data['issue_date'] ?? $issue->issue_date,
            'status' => $newStatus,
        ]);

        if ($statusChanged && in_array($newStatus, CrmCustomerIssue::allStatuses(), true)) {
            CrmCustomerIssueHistory::create([
                'issue_id' => $issue->id,
                'employee_id' => $employee->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'update_note' => $updateNote ?: ($oldStatus === null ? 'Issue created.' : 'Status updated.'),
                'created_at' => now(),
            ]);
        }

        $issue->save();

        return $issue;
    }

    public function getEmployeeIssuesQuery(User $employee)
    {
        return CrmCustomerIssue::query()
            ->forEmployee($employee->id)
            ->with(['customer:id,name', 'employee:id,name', 'histories' => fn ($q) => $q->limit(1)]);
    }

    public function getAuthorizedCustomerIds(User $employee): array
    {
        return $employee
            ->salesOrders()
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->distinct()
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
