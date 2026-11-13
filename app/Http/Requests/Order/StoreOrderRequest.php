<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SalesOrder;

class StoreOrderRequest extends FormRequest
{
    /**
     * The concrete role-scoping happens in the controller; here we only
     * validate the payload the actor is allowed to submit.
     */
    public function authorize(): bool
    {
        $actor = $this->user('sanctum');

        return $actor instanceof \App\Models\Team
            || $actor instanceof \App\Models\User
            || $actor instanceof \App\Models\Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subscription_plan' => ['sometimes', 'nullable', 'string', 'max:50'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'product_value' => ['sometimes', 'numeric', 'min:0', 'max:999999999999999.99'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'closing_value' => ['sometimes', 'numeric', 'min:0'],
            'amount_received' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', 'in:'.implode(',', SalesOrder::PAYMENT_STATUSES)],
            'order_status' => ['sometimes', 'in:'.implode(',', SalesOrder::STATUSES)],
        ];

        // Admins create orders on behalf of a specific team; teams and
        // employees act within their own (scope-checked in the controller).
        if ($this->user('sanctum') instanceof \App\Models\Admin) {
            $rules['team_id'] = ['required', 'integer', 'exists:teams,id'];
        } else {
            $rules['team_id'] = ['sometimes', 'integer', 'exists:teams,id'];
        }

        $rules['assigned_employee_id'] = ['sometimes', 'nullable', 'integer', 'exists:users,id'];

        return $rules;
    }
}
