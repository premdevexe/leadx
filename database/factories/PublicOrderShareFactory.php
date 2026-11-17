<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\PublicOrderShare;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PublicOrderShare>
 */
class PublicOrderShareFactory extends Factory
{
    protected $model = PublicOrderShare::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => SalesOrder::factory(),
            'token' => Str::random(PublicOrderShare::TOKEN_LENGTH),
            'include_api_key' => false,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function live(): static
    {
        return $this->state(['revoked_at' => null, 'expires_at' => now()->addDay()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()->subHour()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }
}
