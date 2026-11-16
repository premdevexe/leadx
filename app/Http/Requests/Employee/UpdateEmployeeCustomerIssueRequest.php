<?php

namespace App\Http\Requests\Employee;

use App\Models\CrmCustomerIssue;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a customer issue for the employee panel API
 * (PUT /api/v1/employee/customer-issues/{id}).
 *
 * Mirrors the web panel's UpdateCustomerIssueRequest, resolving the actor
 * through the Sanctum guard and route-binding the issue by id (the web
 * request relies on named route binding against the session guard, which a
 * Bearer-token request has no way to satisfy here — the controller
 * pre-fetches the issue and passes it to the service for the ownership
 * check).
 */
class UpdateEmployeeCustomerIssueRequest extends FormRequest
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
            $authorizedCustomerIds = [0];
        }

        return [
            'customer_id' => [
                'sometimes',
                'integer',
                Rule::exists('customers', 'id')->whereIn('id', $authorizedCustomerIds),
            ],
            'issue_title' => ['sometimes', 'string', 'max:255'],
            'issue_description' => ['sometimes', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(CrmCustomerIssue::allStatuses())],
            'update_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'issue_title.max' => 'Issue title must be under 255 characters.',
            'issue_description.max' => 'Description must be under 5000 characters.',
            'status.in' => 'Invalid status selected.',
        ];
    }
}
