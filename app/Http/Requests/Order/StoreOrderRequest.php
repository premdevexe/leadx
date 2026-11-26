<?php

namespace App\Http\Requests\Order;

use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
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
            'customer_email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'product_value' => ['sometimes', 'numeric', 'min:0', 'max:999999999999999.99'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'closing_value' => ['sometimes', 'numeric', 'min:0'],
            'amount_received' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['sometimes', 'nullable', 'in:'.implode(',', \App\Models\Setting::enabledPaymentMethods())],
            'payment_status' => ['sometimes', 'in:'.implode(',', SalesOrder::PAYMENT_STATUSES)],
            'order_status' => ['sometimes', 'in:'.implode(',', SalesOrder::STATUSES)],
            // Multi-product basket support
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.duration' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            // Legacy single-product fields (Flutter app & older integrations)
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ];

        if ($this->user('sanctum') instanceof \App\Models\Admin) {
            $rules['team_id'] = ['required', 'integer', 'exists:teams,id'];
        } else {
            $rules['team_id'] = ['sometimes', 'integer', 'exists:teams,id'];
        }

        $rules['assigned_employee_id'] = ['sometimes', 'nullable', 'integer', 'exists:users,id'];

        return $rules;
    }

    /**
     * Enforce the basket minimum closing price floor when closing_value is provided.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $closingValue = $this->input('closing_value');
            if ($closingValue === null || $closingValue === '') {
                return;
            }

            if ($this->user('sanctum') instanceof \App\Models\Admin) {
                return;
            }

            $items = $this->input('items');
            $minFloor = 0.0;

            if (is_array($items) && ! empty($items)) {
                foreach ($items as $row) {
                    $pid = $row['product_id'] ?? null;
                    $qty = max(1, (int) ($row['quantity'] ?? 1));
                    if ($pid && ($product = Product::find($pid))) {
                        $minFloor += (float) ($product->minimum_closing_price ?? 0) * $qty;
                    }
                }
            } elseif ($productId = $this->input('product_id')) {
                $qty = max(1, (int) ($this->input('quantity', 1)));
                if ($product = Product::find($productId)) {
                    $minFloor = (float) ($product->minimum_closing_price ?? 0) * $qty;
                }
            }

            if ($minFloor > 0 && (float) $closingValue < $minFloor) {
                $validator->errors()->add(
                    'closing_value',
                    'The closing value cannot be less than the product minimum closing price of '.number_format($minFloor, 2)
                );
            }
        });
    }
}
