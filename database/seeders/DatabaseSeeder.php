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
        // User::factory(10)->create();

        User::firstOrNew(['email' => 'test@example.com'])->fill([
            'name' => 'Test User',
            'username' => 'testuser',
            'password' => 'password',
        ])->save();

        // The Admin model has a 'password' => 'hashed' cast, so pass the
        // plaintext and let the cast do the hashing (pre-hashing here would
        // double-hash it).
        Admin::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Demo Admin',
                'email' => 'admin@leadx.local',
                'password' => 'password',
            ]
        );
    }
}
