<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'product_id' => Product::factory(),
            'billing_cycle' => fake()->randomElement(Plan::CYCLES),
            'duration_code' => null,
            'price' => fake()->randomFloat(2, 50, 500),
            'description' => null,
            'status' => 'active',
        ];
    }
}
