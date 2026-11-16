<?php

namespace App\Http\Requests\Employee;

use App\Models\CrmCustomerIssue;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store a customer issue for the employee panel API
 * (POST /api/v1/employee/customer-issues).
 *
 * Mirrors the web panel's StoreCustomerIssueRequest rules, but resolves the
 * actor through the Sanctum guard (the web request resolves $this->user()
 * through the session `employee` guard, which is empty on a Bearer-token
 * request). The customer must be one of the employee's authorized
 * (verified-order) customers — enforced by the same
 * CrmCustomerIssueService::getAuthorizedCustomerIds() rule.
 */
class StoreEmployeeCustomerIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('sanctum');

        return $actor instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $employee */
        $employee = $this->user('sanctum');

        $authorizedCustomerIds = app(\App\Services\CrmCustomerIssueService::class)
            ->getAuthorizedCustomerIds($employee);

        if (empty($authorizedCustomerIds)) {
            $authorizedCustomerIds = [0]; // fail everything if no authorized customers
        }

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->whereIn('id', $authorizedCustomerIds),
            ],
            'issue_title' => ['required', 'string', 'max:255'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'issue_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(CrmCustomerIssue::allStatuses())],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer.',
            'customer_id.exists' => 'The selected customer is not available for your issues.',
            'issue_title.required' => 'Issue title is required.',
            'issue_description.required' => 'Please describe the problem.',
            'issue_description.max' => 'Description must be under 5000 characters.',
            'status.in' => 'Invalid status selected.',
        ];
    }
}
