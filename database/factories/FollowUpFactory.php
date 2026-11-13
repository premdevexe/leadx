<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;

class FollowUpFactory extends Factory
{
    protected $model = FollowUp::class;

    public function definition(): array
    {
        // Keep the follow-up's team consistent with its customer's team.
        $customer = Customer::factory()->paying()->create();

        return [
            'customer_id' => $customer->id,
            'team_id' => $customer->team_id,
            'assigned_employee_id' => null,
            'created_by' => null,
            'created_by_type' => null,
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'follow_up_type' => 'general',
            'scheduled_at' => now()->addDays(3),
            'status' => 'scheduled',
            'priority' => 'medium',
            'calendar_sync_status' => 'not_synced',
        ];
    }
}
