<?php

namespace App\Services\Installer;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminAccountManager
{
    /**
     * Create the primary Super Administrator account.
     *
     * Create-only: if an admin account already exists (in either the `admins`
     * table or `users` with role=admin), existing credentials are preserved
     * and NOT overwritten. This prevents the install wizard — which is
     * reachable without auth when re-opened via Installer Recovery — from
     * being abused to silently change a live super admin's password by
     * matching on their email. Fresh installs (no admin yet) still get a
     * brand-new account.
     *
     * @param  array{name: string, username: string, email: string, password: string}  $data
     * @return array{success: bool, admin?: Admin, user?: User, skipped?: bool, error?: string}
     */
    public function createOrUpdateAdmin(array $data): array
    {
        $name = trim($data['name']);
        $username = trim($data['username']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'];

        try {
            // 1. admins table (auth guard: admin)
            $admin = null;
            $adminPreexisting = false;
            if (Schema::hasTable('admins')) {
                $admin = Admin::query()->where('email', $email)->first();
                $adminPreexisting = $admin !== null;

                if (! $admin) {
                    $admin = Admin::create([
                        'name' => $name,
                        'username' => $username,
                        'password' => $password, // Auto-hashed via model cast
                        'email' => $email,
                        'email_verified_at' => now(),
                    ]);
                }
            }

            // 2. users table (auth guard: web, role: admin)
            $user = null;
            $userPreexisting = false;
            if (Schema::hasTable('users')) {
                $user = User::query()->where('email', $email)->first();
                $userPreexisting = $user !== null;

                if (! $user) {
                    $user = User::create([
                        'name' => $name,
                        'username' => $username,
                        'password' => $password,
                        'email' => $email,
                        'role' => 'admin',
                        'status' => 'active',
                        'email_verified_at' => now(),
                    ]);
                }
            }

            // "skipped" is true when an admin for this email already existed in
            // either store — i.e. nothing was (re)created and no credentials
            // were touched. Callers can surface that to the wizard UI.
            return [
                'success' => true,
                'admin' => $admin,
                'user' => $user,
                'skipped' => $adminPreexisting || $userPreexisting,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if at least one administrator account exists in the database.
     */
    public function hasAdminAccount(): bool
    {
        try {
            $adminCount = Schema::hasTable('admins') ? Admin::count() : 0;
            $userAdminCount = Schema::hasTable('users') ? User::where('role', 'admin')->count() : 0;

            return ($adminCount + $userAdminCount) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Count total existing administrators.
     */
    public function countAdmins(): int
    {
        try {
            $adminCount = Schema::hasTable('admins') ? Admin::count() : 0;
            $userAdminCount = Schema::hasTable('users') ? User::where('role', 'admin')->count() : 0;

            return max($adminCount, $userAdminCount);
        } catch (Throwable) {
            return 0;
        }
    }
}
