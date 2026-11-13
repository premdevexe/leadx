<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development seeder: two teams, two employees, memberships.
 * Passwords are generated at runtime and written to the log —
 * never hardcoded in source.
 */
class TeamSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $salesPassword = Str::random(24);
        $supportPassword = Str::random(24);

        $teamSales = Team::firstOrCreate(
            ['username' => 'sales-team'],
            [
                'team_code' => 'SALES-001',
                'team_name' => 'Sales Team',
                'password' => $salesPassword,
            ]
        );

        $teamSupport = Team::firstOrCreate(
            ['username' => 'support-team'],
            [
                'team_code' => 'SUPPORT-001',
                'team_name' => 'Support Team',
                'password' => $supportPassword,
            ]
        );

        $jane = User::firstOrCreate(
            ['username' => 'jdoe'],
            [
                'name' => 'Jane Doe',
                'email' => 'jane.doe@example.com',
                'phone' => '+12025550101',
                'password' => Str::random(24),
                'role' => 'employee',
                'status' => 'active',
            ]
        );

        $adam = User::firstOrCreate(
            ['username' => 'asmith'],
            [
                'name' => 'Adam Smith',
                'email' => 'adam.smith@example.com',
                'phone' => '+12025550102',
                'password' => Str::random(24),
                'role' => 'employee',
                'status' => 'active',
            ]
        );

        if ($teamSales->wasRecentlyCreated) {
            Log::info("LeadX TeamSeeder: sales-team password = {$salesPassword} (dev only, regenerate with a fresh DB)");
        }
        if ($teamSupport->wasRecentlyCreated) {
            Log::info("LeadX TeamSeeder: support-team password = {$supportPassword} (dev only, regenerate with a fresh DB)");
        }

        $this->attach($teamSales, $jane);
        $this->attach($teamSupport, $adam);
    }

    /**
     * Open an active membership and keep the users.team_id FK in sync.
     */
    protected function attach(Team $team, User $user): void
    {
        TeamMember::firstOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'left_at' => null,
            ],
            ['joined_at' => now()]
        );

        $user->forceFill(['team_id' => $team->id])->save();
    }
}
