<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Validation\Rules\Rule|string|array<mixed>>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $employee */
        $employee = $this->user();
        $issue = $this->route('customer_issue');

        if (! $issue) {
            return ['issue_title' => ['required', 'string', 'max:255']];
        }

        // Ownership check - handled by controller redirect
        if ((int) $issue->employee_id !== (int) $employee->id) {
            return [];
        }

        $authorizedCustomerIds = app(\App\Services\CrmCustomerIssueService::class)->getAuthorizedCustomerIds($employee);

        if (empty($authorizedCustomerIds)) {
            $authorizedCustomerIds = [0];
        }

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->whereIn('id', $authorizedCustomerIds),
            ],
            'issue_title' => ['required', 'string', 'max:255'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(\App\Models\CrmCustomerIssue::allStatuses())],
            'update_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'issue_title.required' => 'Issue title is required.',
            'issue_description.required' => 'Please describe the problem.',
            'status.in' => 'Invalid status selected.',
        ];
    }
}
