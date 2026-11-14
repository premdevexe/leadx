<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * The model name that the factory applies to.
     */
    protected $model = SalesOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'FAK-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'team_id' => Team::factory(),
            'team_code_snapshot' => null,
            'assigned_employee_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => '+1'.fake()->unique()->numerify('5##-###-####'),
            'product_name' => fake()->word(),
            'duration' => fake()->randomElement(array_keys(SalesOrder::DURATIONS)),
            'product_value' => fake()->randomFloat(2, 0, 5000),
            'discount_amount' => 0,
            'closing_value' => 0,
            'amount_received' => 0,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ];
    }

    /**
     * A confirmed order ready to be activated.
     */
    public function confirmed(): static
    {
        return $this->state(['order_status' => 'confirmed', 'payment_status' => 'paid']);
    }

    /**
     * An active order with an active subscription (share-eligible).
     */
    public function active(): static
    {
        return $this->state([
            'order_status' => 'active',
            'payment_status' => 'paid',
            'closing_value' => fn (array $attributes) => $attributes['product_value'] ?? 0,
        ]);
    }

    /**
     * A cancelled order (share-ineligible).
     */
    public function cancelled(): static
    {
        return $this->state(['order_status' => 'cancelled']);
    }
}
