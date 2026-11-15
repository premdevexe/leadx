<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * The model name that the factory applies to.
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 50, 5000);

        return [
            'name' => fake()->unique()->words(2, true),
            'price' => $price,
            'minimum_closing_price' => round($price * 0.7, 2),
            'duration' => fake()->randomElement(array_keys(\App\Models\SalesOrder::DURATIONS)),
            'api_key_required' => false,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
