<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gatekeeping is in controller via $this->user()
    }

    /**
     * @return array<string, \Illuminate\Validation\Rules\Rule|string|array<mixed>>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $employee */
        $employee = $this->user();

        $authorizedCustomerIds = $employee
            ->salesOrders()
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->distinct()
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

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
            'status' => ['nullable', Rule::in(\App\Models\CrmCustomerIssue::allStatuses())],
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
