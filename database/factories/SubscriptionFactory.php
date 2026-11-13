<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The model name that the factory applies to.
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => SalesOrder::factory(),
            'team_id' => null,
            'status' => 'pending',
            'started_at' => null,
            'expires_at' => now()->addMonth(),
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
    }
}
