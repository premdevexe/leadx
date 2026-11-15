<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\PermissionPresetItem;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Orders
            ['name' => 'orders.view', 'module' => 'orders', 'action' => 'view', 'description' => 'View own orders'],
            ['name' => 'orders.create', 'module' => 'orders', 'action' => 'create', 'description' => 'Create new orders'],
            ['name' => 'orders.edit', 'module' => 'orders', 'action' => 'edit', 'description' => 'Edit order details'],
            ['name' => 'orders.delete', 'module' => 'orders', 'action' => 'delete', 'description' => 'Delete/cancel orders'],
            ['name' => 'orders.complete', 'module' => 'orders', 'action' => 'complete', 'description' => 'Mark order as completed/activated'],
            ['name' => 'orders.view_api_key', 'module' => 'orders', 'action' => 'view_api_key', 'description' => 'Reveal masked API credentials'],
            ['name' => 'orders.copy_api_key', 'module' => 'orders', 'action' => 'copy_api_key', 'description' => 'Copy API credentials to clipboard'],

            // Customers
            ['name' => 'customers.view', 'module' => 'customers', 'action' => 'view', 'description' => 'View assigned customers'],
            ['name' => 'customers.create', 'module' => 'customers', 'action' => 'create', 'description' => 'Create customer records'],
            ['name' => 'customers.edit', 'module' => 'customers', 'action' => 'edit', 'description' => 'Edit customer information'],
            ['name' => 'customers.delete', 'module' => 'customers', 'action' => 'delete', 'description' => 'Delete customer records'],

            // Payments
            ['name' => 'payments.view', 'module' => 'payments', 'action' => 'view', 'description' => 'View payment records'],
            ['name' => 'payments.create', 'module' => 'payments', 'action' => 'create', 'description' => 'Record new payments'],
            ['name' => 'payments.edit', 'module' => 'payments', 'action' => 'edit', 'description' => 'Edit payment records'],
            ['name' => 'payments.delete', 'module' => 'payments', 'action' => 'delete', 'description' => 'Delete payment records'],

            // Subscriptions
            ['name' => 'subscriptions.view', 'module' => 'subscriptions', 'action' => 'view', 'description' => 'View subscription records'],
            ['name' => 'subscriptions.create', 'module' => 'subscriptions', 'action' => 'create', 'description' => 'Create new subscriptions'],
            ['name' => 'subscriptions.edit', 'module' => 'subscriptions', 'action' => 'edit', 'description' => 'Edit subscription details'],
            ['name' => 'subscriptions.delete', 'module' => 'subscriptions', 'action' => 'delete', 'description' => 'Cancel/delete subscriptions'],

            // Follow-ups
            ['name' => 'follow_ups.view', 'module' => 'follow_ups', 'action' => 'view', 'description' => 'View follow-ups and leads'],
            ['name' => 'follow_ups.create', 'module' => 'follow_ups', 'action' => 'create', 'description' => 'Create follow-up tasks'],
            ['name' => 'follow_ups.edit', 'module' => 'follow_ups', 'action' => 'edit', 'description' => 'Update follow-up progress'],
            ['name' => 'follow_ups.delete', 'module' => 'follow_ups', 'action' => 'delete', 'description' => 'Delete follow-up records'],

            // Team Data
            ['name' => 'team_data.view', 'module' => 'team_data', 'action' => 'view', 'description' => 'View-only access to team data toggle'],

            // Dashboard Cards
            ['name' => 'dashboard.orders_count', 'module' => 'dashboard', 'action' => 'orders_count', 'description' => 'View Total Orders metric card'],
            ['name' => 'dashboard.active_subscriptions', 'module' => 'dashboard', 'action' => 'active_subscriptions', 'description' => 'View Active Subscriptions metric card'],
            ['name' => 'dashboard.expiring_soon', 'module' => 'dashboard', 'action' => 'expiring_soon', 'description' => 'View Expiring Soon metric card'],
            ['name' => 'dashboard.unassigned_leads', 'module' => 'dashboard', 'action' => 'unassigned_leads', 'description' => 'View Unassigned Leads metric card'],
            ['name' => 'dashboard.total_revenue', 'module' => 'dashboard', 'action' => 'total_revenue', 'description' => 'View Total Revenue metric card'],
            ['name' => 'dashboard.conversion_rate', 'module' => 'dashboard', 'action' => 'conversion_rate', 'description' => 'View Conversion Rate metric card'],
            ['name' => 'dashboard.average_order_value', 'module' => 'dashboard', 'action' => 'average_order_value', 'description' => 'View Average Order Value card'],
            ['name' => 'dashboard.recent_orders', 'module' => 'dashboard', 'action' => 'recent_orders', 'description' => 'View Recent Orders card'],
            ['name' => 'dashboard.upcoming_renewals', 'module' => 'dashboard', 'action' => 'upcoming_renewals', 'description' => 'View Upcoming Renewals card'],

            // Sidebar Menus
            ['name' => 'sidebar.dashboard', 'module' => 'sidebar', 'action' => 'dashboard', 'description' => 'View Dashboard sidebar menu item'],
            ['name' => 'sidebar.create_order', 'module' => 'sidebar', 'action' => 'create_order', 'description' => 'View Create Order sidebar menu item'],
            ['name' => 'sidebar.orders', 'module' => 'sidebar', 'action' => 'orders', 'description' => 'View My Orders sidebar menu item'],
            ['name' => 'sidebar.customers', 'module' => 'sidebar', 'action' => 'customers', 'description' => 'View Customers sidebar menu item'],
            ['name' => 'sidebar.payments', 'module' => 'sidebar', 'action' => 'payments', 'description' => 'View Payments sidebar menu item'],
            ['name' => 'sidebar.subscriptions', 'module' => 'sidebar', 'action' => 'subscriptions', 'description' => 'View Subscriptions sidebar menu item'],
            ['name' => 'sidebar.follow_ups', 'module' => 'sidebar', 'action' => 'follow_ups', 'description' => 'View Follow-ups sidebar menu item'],
            ['name' => 'sidebar.team_data', 'module' => 'sidebar', 'action' => 'team_data', 'description' => 'View Team Data sidebar menu item'],
            ['name' => 'sidebar.reports', 'module' => 'sidebar', 'action' => 'reports', 'description' => 'View Reports sidebar menu item'],
            ['name' => 'sidebar.notifications', 'module' => 'sidebar', 'action' => 'notifications', 'description' => 'View Notifications sidebar menu item'],
            ['name' => 'sidebar.settings', 'module' => 'sidebar', 'action' => 'settings', 'description' => 'View Settings sidebar menu item'],
            ['name' => 'sidebar.help', 'module' => 'sidebar', 'action' => 'help', 'description' => 'View Help sidebar menu item'],
        ];

        $permissionModels = [];
        foreach ($permissions as $data) {
            $permissionModels[$data['name']] = Permission::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }

        // 1. Admin Preset
        $adminPreset = PermissionPreset::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Full administrative access across all modules, cards, and sidebar features.',
                'is_default' => false,
            ]
        );
        foreach ($permissionModels as $perm) {
            PermissionPresetItem::updateOrCreate(
                ['preset_id' => $adminPreset->id, 'permission_id' => $perm->id],
                ['granted' => true]
            );
        }

        // 2. Sales Executive Preset (Default)
        $salesPreset = PermissionPreset::updateOrCreate(
            ['slug' => 'sales_executive'],
            [
                'name' => 'Sales Executive',
                'description' => 'Standard sales operations: create orders, view customers, manage leads and subscriptions.',
                'is_default' => true,
            ]
        );
        $salesGranted = [
            'orders.view', 'orders.create', 'orders.complete', 'orders.view_api_key', 'orders.copy_api_key',
            'customers.view', 'customers.create', 'customers.edit',
            'payments.view',
            'subscriptions.view',
            'follow_ups.view', 'follow_ups.create', 'follow_ups.edit',
            'dashboard.orders_count', 'dashboard.active_subscriptions', 'dashboard.expiring_soon',
            'dashboard.total_revenue', 'dashboard.recent_orders', 'dashboard.upcoming_renewals',
            'sidebar.dashboard', 'sidebar.create_order', 'sidebar.orders', 'sidebar.customers',
            'sidebar.payments', 'sidebar.subscriptions', 'sidebar.follow_ups', 'sidebar.notifications',
        ];
        foreach ($permissionModels as $name => $perm) {
            PermissionPresetItem::updateOrCreate(
                ['preset_id' => $salesPreset->id, 'permission_id' => $perm->id],
                ['granted' => in_array($name, $salesGranted, true)]
            );
        }

        // 3. Viewer Preset
        $viewerPreset = PermissionPreset::updateOrCreate(
            ['slug' => 'viewer'],
            [
                'name' => 'Viewer',
                'description' => 'Read-only access for auditing, monitoring, or trainee employees.',
                'is_default' => false,
            ]
        );
        $viewerGranted = [
            'orders.view',
            'customers.view',
            'payments.view',
            'subscriptions.view',
            'follow_ups.view',
            'dashboard.orders_count', 'dashboard.active_subscriptions',
            'sidebar.dashboard', 'sidebar.orders', 'sidebar.customers', 'sidebar.subscriptions',
        ];
        foreach ($permissionModels as $name => $perm) {
            PermissionPresetItem::updateOrCreate(
                ['preset_id' => $viewerPreset->id, 'permission_id' => $perm->id],
                ['granted' => in_array($name, $viewerGranted, true)]
            );
        }
    }
}
