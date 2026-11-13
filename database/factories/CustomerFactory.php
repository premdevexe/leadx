<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phone = '+1'.fake()->numerify('5##-###-####');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => $phone,
            'normalized_phone' => preg_replace('/[^\d+]/', '', $phone),
            'normalized_email' => strtolower(fake()->unique()->safeEmail()),
            'status' => 'enquiry',
            'team_id' => Team::factory(),
            'purchases_count' => 0,
            'total_purchased' => 0,
        ];
    }

    /**
     * A customer with at least one *verified* purchase — the set a
     * follow-up may target (Phase 08, Module 1 rule 1).
     */
    public function paying(): static
    {
        return $this->state(['status' => 'paying', 'purchases_count' => 1]);
    }

    /**
     * An enquiry with no verified purchases — not a valid follow-up target.
     */
    public function enquiry(): static
    {
        return $this->state(['status' => 'enquiry', 'purchases_count' => 0]);
    }
}
