<?php

namespace Database\Seeders;

use App\Models\RefundStatus;
use Illuminate\Database\Seeder;

class RefundStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RefundStatus::defaultStatuses() as $status) {
            RefundStatus::firstOrCreate(
                ['slug' => $status['slug']],
                $status
            );
        }
    }
}
