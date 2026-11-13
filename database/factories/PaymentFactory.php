<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => SalesOrder::factory(),
            'customer_id' => null,
            'payment_method' => fake()->randomElement(Payment::METHODS),
            'transaction_reference' => 'REF-'.fake()->unique()->uuid(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'USD',
            'payment_date' => now(),
            'payment_status' => 'pending',
            'source' => 'manual',
            'verified_by_admin_id' => null,
            'verification_source' => null,
            'verified_at' => null,
            'admin_notes' => null,
        ];
    }
}
