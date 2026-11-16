<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // No demo credentials: the install wizard creates the real admin
        // account during its admin step. Seeding admin/admin-password-style
        // accounts into every deployment (including production) is a
        // standing backdoor, so this seeder only installs the reference
        // data the app needs to boot (permissions + refund statuses).
        $this->call([
            PermissionSeeder::class,
            RefundStatusSeeder::class,
        ]);
    }
}
