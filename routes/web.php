<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminsController;
use App\Http\Controllers\Admin\ApiManagementController;
use App\Http\Controllers\Admin\CustomersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailSettingsController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Admin\OrderCredentialController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PermissionDefinitionsController;
use App\Http\Controllers\Admin\PermissionPresetsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\RefundStatusesController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamAccessGrantsController;
use App\Http\Controllers\Admin\TeamsController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Employee\CreateOrderController;
use App\Http\Controllers\Employee\CustomersController as EmployeeCustomersController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\FollowUpsController;
use App\Http\Controllers\Employee\LoginController as EmployeeLoginController;
use App\Http\Controllers\Employee\NotificationsController as EmployeeNotificationsController;
use App\Http\Controllers\Employee\OrdersController as EmployeeOrdersController;
use App\Http\Controllers\Employee\PaymentsController as EmployeePaymentsController;
use App\Http\Controllers\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Employee\SubscriptionsController as EmployeeSubscriptionsController;
use App\Http\Controllers\Team\CreateOrderController as TeamCreateOrderController;
use App\Http\Controllers\Team\CustomersController as TeamCustomersController;
use App\Http\Controllers\Team\DashboardController as TeamDashboardController;
use App\Http\Controllers\Team\LoginController as TeamLoginController;
use App\Http\Controllers\Team\NotificationsController as TeamNotificationsController;
use App\Http\Controllers\Team\OrdersController as TeamOrdersController;
use App\Http\Controllers\Team\PaymentsController as TeamPaymentsController;
use App\Http\Controllers\Team\SubscriptionsController as TeamSubscriptionsController;
use App\Http\Controllers\Team\TeamMembersController;

// ── Installation Wizard ─────────────────────────────────────

Route::prefix('install')->name('install.')->middleware('guest')->group(function () {
    Route::get('/', [InstallController::class, 'requirements'])->name('requirements');
    Route::get('/environment', [InstallController::class, 'environment'])->name('environment');
    Route::post('/environment', [InstallController::class, 'saveEnvironment'])->name('save-environment');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database/test', [InstallController::class, 'testDatabase'])->name('test-database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('save-database');
    Route::get('/migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('/migrate/run', [InstallController::class, 'runMigrations'])->name('run-migrate');
    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'saveAdmin'])->name('save-admin');
    Route::get('/config', [InstallController::class, 'config'])->name('config');
    Route::post('/config', [InstallController::class, 'saveConfig'])->name('save-config');
    Route::get('/verify', [InstallController::class, 'verify'])->name('verify');
    Route::post('/finish', [InstallController::class, 'finish'])->name('finish');
});

// ── Application Routes ──────────────────────────────────────

Route::get('/', function () {
    if (auth()->guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    if (auth()->guard('employee')->check() || (auth()->check() && auth()->user()->role === 'employee')) {
        return redirect()->route('employee.dashboard');
    }
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if ($user && $user->role === 'employee' && ! app()->runningUnitTests()) {
            \Illuminate\Support\Facades\Auth::guard('employee')->login($user);
            $request->session()->put('employee_id', $user->id);

            return redirect()->route('employee.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

// Broadcast auth routes (POST /broadcasting/auth, GET /broadcasting/auth).
// Employee authentication goes through the employee guard, so we protect
// the broadcast endpoint with the employee.web middleware and a dedicated
// controller that uses the employee guard for authorization.
Route::middleware('employee.web')->group(function () {
    \Illuminate\Support\Facades\Broadcast::routes(['middleware' => []]);
});

// ---- Admin panel ----

Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'login'])->name('admin.login.attempt');
});

Route::middleware('admin')->group(function () {
    Route::get('/admin', function () {
        return redirect()->route('admin.dashboard');
    })->name('admin');

    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/dashboard/preferences', [DashboardController::class, 'savePreferences'])->name('admin.dashboard.preferences.save');
    Route::post('/admin/dashboard/preferences/reset', [DashboardController::class, 'resetPreferences'])->name('admin.dashboard.preferences.reset');
    Route::get('/admin/reports', [ReportsController::class, 'index'])->name('admin.reports.index');

    // Team management
    Route::get('/admin/teams', [TeamsController::class, 'index'])->name('admin.teams');
    Route::get('/admin/teams/create', [TeamsController::class, 'create'])->name('admin.teams.create');
    Route::post('/admin/teams', [TeamsController::class, 'store'])->name('admin.teams.store');
    Route::get('/admin/teams/{team}/edit', [TeamsController::class, 'edit'])->name('admin.teams.edit');
    Route::put('/admin/teams/{team}', [TeamsController::class, 'update'])->name('admin.teams.update');
    Route::delete('/admin/teams/{team}', [TeamsController::class, 'destroy'])->name('admin.teams.destroy');

    // Product catalog
    Route::get('/admin/products', [ProductsController::class, 'index'])->name('admin.products');
    Route::get('/admin/products/create', [ProductsController::class, 'create'])->name('admin.products.create');
    Route::post('/admin/products', [ProductsController::class, 'store'])->name('admin.products.store');
    Route::get('/admin/products/{product}/edit', [ProductsController::class, 'edit'])->name('admin.products.edit');
    Route::put('/admin/products/{product}', [ProductsController::class, 'update'])->name('admin.products.update');
    Route::patch('/admin/products/{product}/toggle-status', [ProductsController::class, 'toggleStatus'])->name('admin.products.toggle-status');
    Route::delete('/admin/products/{product}', [ProductsController::class, 'destroy'])->name('admin.products.destroy');

    // Order management
    Route::get('/admin/orders', [OrdersController::class, 'index'])->name('admin.orders');
    Route::get('/admin/orders/create', [OrdersController::class, 'create'])->name('admin.orders.create');
    Route::post('/admin/orders', [OrdersController::class, 'store'])->name('admin.orders.store');
    Route::get('/admin/orders/{order}', [OrdersController::class, 'show'])->name('admin.orders.show');
    Route::get('/admin/orders/{order}/edit', [OrdersController::class, 'edit'])->name('admin.orders.edit');
    Route::put('/admin/orders/{order}', [OrdersController::class, 'update'])->name('admin.orders.update');
    Route::put('/admin/orders/{order}/activate', [OrdersController::class, 'activate'])->name('admin.orders.activate');
    Route::post('/admin/orders/{order}/whatsapp-share-log', [OrdersController::class, 'recordWhatsappShareLog'])->name('admin.orders.whatsapp-log');

    // Order delivery: the customer's own API key, stored per order.
    // Status is rendered on the order detail page; store/reveal are the only
    // write + authorized-plaintext-read actions. The key is encrypted at
    // rest and never generated by LeadX.
    Route::post('/admin/orders/{order}/api-key', [OrderCredentialController::class, 'store'])->name('admin.orders.api-key.store');
    Route::post('/admin/orders/{order}/api-key/reveal', [OrderCredentialController::class, 'reveal'])->name('admin.orders.api-key.reveal');
    Route::post('/admin/orders/{order}/payments', [OrdersController::class, 'recordPayment'])->name('admin.orders.payments.store');
    Route::post('/admin/orders/{order}/payments/{payment}/verify', [OrdersController::class, 'verifyPayment'])->name('admin.orders.payments.verify');
    Route::post('/admin/orders/{order}/payments/{payment}/reject', [OrdersController::class, 'rejectPayment'])->name('admin.orders.payments.reject');

    // Closing Value Approvals, Order Cancellations & Refunds
    Route::post('/admin/orders/{order}/closing-approval/accept', [OrdersController::class, 'acceptClosingApproval'])->name('admin.orders.closing-approval.accept');
    Route::post('/admin/orders/{order}/closing-approval/reject', [OrdersController::class, 'rejectClosingApproval'])->name('admin.orders.closing-approval.reject');
    Route::post('/admin/orders/{order}/closing-value/modify', [OrdersController::class, 'modifyAcceptedClosingValue'])->name('admin.orders.closing-value.modify');
    Route::post('/admin/orders/{order}/cancel', [OrdersController::class, 'cancelOrder'])->name('admin.orders.cancel');
    Route::post('/admin/orders/{order}/refunds', [OrdersController::class, 'processRefund'])->name('admin.orders.refunds.store');

    // Settings (payment methods, email, …)
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    // Refund Statuses Configuration (Section 5)
    Route::get('/admin/settings/refund-statuses', [\App\Http\Controllers\Admin\RefundStatusesController::class, 'index'])->name('admin.settings.refund-statuses');
    Route::post('/admin/settings/refund-statuses', [\App\Http\Controllers\Admin\RefundStatusesController::class, 'store'])->name('admin.settings.refund-statuses.store');
    Route::put('/admin/settings/refund-statuses/{status}', [\App\Http\Controllers\Admin\RefundStatusesController::class, 'update'])->name('admin.settings.refund-statuses.update');

    // Dynamic API Integrations & Credentials (Pusher, Google, WhatsApp)
    Route::get('/admin/api-settings', [\App\Http\Controllers\Admin\ApiSettingsController::class, 'index'])->name('admin.api-settings.index');
    Route::put('/admin/api-settings/pusher', [\App\Http\Controllers\Admin\ApiSettingsController::class, 'updatePusher'])->name('admin.api-settings.pusher.update');
    Route::put('/admin/api-settings/google', [\App\Http\Controllers\Admin\ApiSettingsController::class, 'updateGoogle'])->name('admin.api-settings.google.update');
    Route::put('/admin/api-settings/whatsapp', [\App\Http\Controllers\Admin\ApiSettingsController::class, 'updateWhatsapp'])->name('admin.api-settings.whatsapp.update');

    // Email management (SMTP, templates, notification events, logs)
    Route::get('/admin/email', [EmailSettingsController::class, 'index'])->name('admin.email.index');
    Route::put('/admin/email/smtp', [EmailSettingsController::class, 'updateSmtp'])->name('admin.email.smtp.update');
    Route::post('/admin/email/test', [EmailSettingsController::class, 'testEmail'])->name('admin.email.test');
    Route::put('/admin/email/events', [EmailSettingsController::class, 'updateEvents'])->name('admin.email.events.update');
    Route::put('/admin/email/reminders', [EmailSettingsController::class, 'updateReminders'])->name('admin.email.reminders.update');
    Route::put('/admin/email/templates/{template}', [EmailSettingsController::class, 'updateTemplate'])->name('admin.email.templates.update');
    Route::post('/admin/email/templates/{template}/toggle', [EmailSettingsController::class, 'toggleTemplate'])->name('admin.email.templates.toggle');
    Route::delete('/admin/email/logs', [EmailSettingsController::class, 'clearLogs'])->name('admin.email.logs.clear');
    Route::post('/admin/email/logs/{log}/resend', [EmailSettingsController::class, 'resendLog'])->name('admin.email.logs.resend');

    // Employee management
    Route::get('/admin/employees', [EmployeesController::class, 'index'])->name('admin.employees');
    Route::get('/admin/employees/create', [EmployeesController::class, 'create'])->name('admin.employees.create');
    Route::post('/admin/employees', [EmployeesController::class, 'store'])->name('admin.employees.store');
    Route::get('/admin/employees/{employee}/edit', [EmployeesController::class, 'edit'])->name('admin.employees.edit');
    Route::put('/admin/employees/{employee}', [EmployeesController::class, 'update'])->name('admin.employees.update');
    Route::delete('/admin/employees/{employee}', [EmployeesController::class, 'destroy'])->name('admin.employees.destroy');

    // Permission presets & employee overrides
    Route::get('/admin/presets', [PermissionPresetsController::class, 'index'])->name('admin.presets');
    Route::get('/admin/presets/create', [PermissionPresetsController::class, 'create'])->name('admin.presets.create');
    Route::post('/admin/presets', [PermissionPresetsController::class, 'store'])->name('admin.presets.store');
    Route::get('/admin/presets/{preset}/edit', [PermissionPresetsController::class, 'edit'])->name('admin.presets.edit');
    Route::put('/admin/presets/{preset}', [PermissionPresetsController::class, 'update'])->name('admin.presets.update');
    Route::post('/admin/presets/{preset}/apply', [PermissionPresetsController::class, 'apply'])->name('admin.presets.apply');
    Route::get('/admin/employees/{employee}/permissions', [PermissionPresetsController::class, 'employee'])->name('admin.presets.employee');
    Route::put('/admin/employees/{employee}/permissions', [PermissionPresetsController::class, 'updateEmployee'])->name('admin.presets.employee.update');
    Route::post('/admin/employees/{employee}/permissions/reset', [PermissionPresetsController::class, 'resetEmployee'])->name('admin.presets.employee.reset');

    // Team Data Access Grants
    Route::post('/admin/employees/{employee}/team-access/grant', [TeamAccessGrantsController::class, 'store'])->name('admin.employees.team-access.grant');
    Route::post('/admin/employees/team-access/{grant}/revoke', [TeamAccessGrantsController::class, 'revoke'])->name('admin.employees.team-access.revoke');

    // Permission definitions
    Route::get('/admin/permissions', [PermissionDefinitionsController::class, 'index'])->name('admin.permissions.index');
    Route::post('/admin/permissions', [PermissionDefinitionsController::class, 'store'])->name('admin.permissions.store');
    Route::delete('/admin/permissions/{permission}', [PermissionDefinitionsController::class, 'destroy'])->name('admin.permissions.destroy');

    // Admin account management
    Route::get('/admin/admins', [AdminsController::class, 'index'])->name('admin.admins');
    Route::get('/admin/admins/create', [AdminsController::class, 'create'])->name('admin.admins.create');
    Route::post('/admin/admins', [AdminsController::class, 'store'])->name('admin.admins.store');
    Route::get('/admin/admins/{admin}/edit', [AdminsController::class, 'edit'])->name('admin.admins.edit');
    Route::put('/admin/admins/{admin}', [AdminsController::class, 'update'])->name('admin.admins.update');

    // Customer management (CRM)
    Route::get('/admin/customers', [CustomersController::class, 'index'])->name('admin.customers');
    Route::get('/admin/customers/{customer}', [CustomersController::class, 'show'])->name('admin.customers.show');

    // Notification sending (Pusher Beams web push)
    Route::get('/admin/notifications', [NotificationsController::class, 'index'])->name('admin.notifications');
    Route::post('/admin/notifications', [NotificationsController::class, 'store'])->name('admin.notifications.store');
    Route::post('/admin/notifications/{log}/resend', [NotificationsController::class, 'resend'])->name('admin.notifications.resend');
    Route::delete('/admin/notifications', [NotificationsController::class, 'clear'])->name('admin.notifications.clear');

    // ── Installer Recovery & Diagnostics ────────────────────────
    Route::get('/admin/installer/recovery', [\App\Http\Controllers\Admin\InstallRecoveryController::class, 'index'])->name('admin.installer.recovery');
    Route::post('/admin/installer/recovery/action', [\App\Http\Controllers\Admin\InstallRecoveryController::class, 'performAction'])->name('admin.installer.recovery.action');
    Route::get('/admin/setup/core', [\App\Http\Controllers\Admin\InstallRecoveryController::class, 'index'])->name('admin.setup.core');
    Route::post('/admin/setup/core/action', [\App\Http\Controllers\Admin\InstallRecoveryController::class, 'performAction'])->name('admin.setup.core.action');

    // ── Backup & Disaster Recovery (Phase 16) ───────────────────
    Route::get('/admin/backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('admin.backups.index');
    Route::post('/admin/backups', [\App\Http\Controllers\Admin\BackupController::class, 'store'])->name('admin.backups.store');
    Route::get('/admin/backups/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restoreView'])->name('admin.backups.restore');
    Route::post('/admin/backups/restore', [\App\Http\Controllers\Admin\BackupController::class, 'performRestore'])->name('admin.backups.restore.perform');
    Route::get('/admin/backups/settings', [\App\Http\Controllers\Admin\BackupController::class, 'settings'])->name('admin.backups.settings');
    Route::put('/admin/backups/settings', [\App\Http\Controllers\Admin\BackupController::class, 'updateSettings'])->name('admin.backups.settings.update');
    Route::get('/admin/backups/{backup}', [\App\Http\Controllers\Admin\BackupController::class, 'show'])->name('admin.backups.show');
    Route::post('/admin/backups/{backup}/verify', [\App\Http\Controllers\Admin\BackupController::class, 'verify'])->name('admin.backups.verify');
    Route::get('/admin/backups/{backup}/download', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('admin.backups.download');
    Route::delete('/admin/backups/{backup}', [\App\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('admin.backups.destroy');

    // ── API Management (Super Admin only) ───────────────────────
    Route::prefix('admin/api-management')->name('admin.api-management.')->group(function () {
        Route::get('/', [ApiManagementController::class, 'index'])->name('index');
        Route::post('/keys/generate', [ApiManagementController::class, 'generateKey'])->name('keys.generate');
        Route::post('/keys/{key}/regenerate', [ApiManagementController::class, 'regenerateKey'])->name('keys.regenerate');
        Route::post('/keys/{key}/revoke', [ApiManagementController::class, 'revokeKey'])->name('keys.revoke');
        Route::post('/keys/{key}/toggle', [ApiManagementController::class, 'toggleKey'])->name('keys.toggle');
        Route::put('/config', [ApiManagementController::class, 'updateConfig'])->name('config.update');
        Route::get('/health', [ApiManagementController::class, 'healthCheck'])->name('health');
        Route::get('/docs/openapi', [ApiManagementController::class, 'docsOpenApi'])->name('docs.openapi');
        Route::get('/docs/swagger', [ApiManagementController::class, 'docsSwagger'])->name('docs.swagger');
        Route::get('/docs/postman', [ApiManagementController::class, 'docsPostman'])->name('docs.postman');
        Route::get('/docs/flutter', [ApiManagementController::class, 'docsFlutter'])->name('docs.flutter');
    });

    Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
});

// ---- Team portal (session) ----

Route::middleware('guest:team')->group(function () {
    Route::get('/team/login', [TeamLoginController::class, 'showLoginForm'])->name('team.login');
    Route::post('/team/login', [TeamLoginController::class, 'login'])->name('team.login.attempt');
});

Route::middleware('team.web')->group(function () {
    Route::post('/team/logout', [TeamLoginController::class, 'logout'])->name('team.logout');

    Route::get('/team', function () {
        return redirect()->route('team.dashboard');
    })->name('team');

    Route::get('/team/dashboard', [TeamDashboardController::class, 'index'])->name('team.dashboard');
    Route::get('/team/orders', [TeamOrdersController::class, 'index'])->name('team.orders');
    Route::get('/team/orders/create', [TeamCreateOrderController::class, 'create'])->name('team.orders.create');
    Route::post('/team/orders', [TeamCreateOrderController::class, 'store'])->name('team.orders.store');
    Route::get('/team/orders/{order}', [TeamOrdersController::class, 'show'])->name('team.orders.show');
    Route::get('/team/customers', [TeamCustomersController::class, 'index'])->name('team.customers');
    Route::get('/team/payments', [TeamPaymentsController::class, 'index'])->name('team.payments');
    Route::get('/team/subscriptions', [TeamSubscriptionsController::class, 'index'])->name('team.subscriptions');
    Route::post('/team/subscriptions/{subscription}/renew', [TeamSubscriptionsController::class, 'renew'])->name('team.subscriptions.renew');
    Route::post('/team/subscriptions/{subscription}/cancel', [TeamSubscriptionsController::class, 'cancel'])->name('team.subscriptions.cancel');
    Route::get('/team/follow-ups', [\App\Http\Controllers\Team\FollowUpsController::class, 'index'])->name('team.follow-ups');
    Route::get('/team/follow-ups/create', [\App\Http\Controllers\Team\FollowUpsController::class, 'create'])->name('team.follow-ups.create');
    Route::post('/team/follow-ups', [\App\Http\Controllers\Team\FollowUpsController::class, 'store'])->name('team.follow-ups.store');
    Route::get('/team/follow-ups/{followUp}', [\App\Http\Controllers\Team\FollowUpsController::class, 'show'])->name('team.follow-ups.show');
    Route::post('/team/follow-ups/{followUp}/complete', [\App\Http\Controllers\Team\FollowUpsController::class, 'complete'])->name('team.follow-ups.complete');
    Route::post('/team/follow-ups/{followUp}/reschedule', [\App\Http\Controllers\Team\FollowUpsController::class, 'reschedule'])->name('team.follow-ups.reschedule');
    Route::post('/team/follow-ups/{followUp}/cancel', [\App\Http\Controllers\Team\FollowUpsController::class, 'cancel'])->name('team.follow-ups.cancel');
    Route::get('/team/members', [TeamMembersController::class, 'index'])->name('team.members');
    Route::get('/team/notifications', [TeamNotificationsController::class, 'index'])->name('team.notifications');
});

// ---- Employee portal (session) ----

Route::middleware('guest:employee')->group(function () {
    Route::get('/employee/login', [EmployeeLoginController::class, 'showLoginForm'])->name('employee.login');
    Route::post('/employee/login', [EmployeeLoginController::class, 'login'])->name('employee.login.attempt');
});

Route::middleware('employee.web')->group(function () {
    Route::post('/employee/logout', [EmployeeLoginController::class, 'logout'])->name('employee.logout');

    Route::get('/employee', function () {
        return redirect()->route('employee.dashboard');
    })->name('employee');

    Route::get('/employee/dashboard', [EmployeeDashboardController::class, 'index'])->name('employee.dashboard');
    Route::post('/employee/dashboard/preferences', [EmployeeDashboardController::class, 'savePreferences'])->name('employee.dashboard.preferences.save');
    Route::post('/employee/dashboard/preferences/reset', [EmployeeDashboardController::class, 'resetPreferences'])->name('employee.dashboard.preferences.reset');
    Route::post('/employee/sidebar/preferences', [EmployeeDashboardController::class, 'saveSidebarPreferences'])->name('employee.sidebar.preferences.save');
    Route::post('/employee/sidebar/preferences/reset', [EmployeeDashboardController::class, 'resetSidebarPreferences'])->name('employee.sidebar.preferences.reset');

    Route::get('/employee/profile', [EmployeeProfileController::class, 'show'])->name('employee.profile');
    Route::put('/employee/profile', [EmployeeProfileController::class, 'update'])->name('employee.profile.update');
    Route::put('/employee/profile/password', [EmployeeProfileController::class, 'updatePassword'])->name('employee.profile.password');

    Route::get('/employee/calendar', [EmployeeDashboardController::class, 'calendar'])->name('employee.calendar');
    Route::get('/employee/calendar/connect', [EmployeeDashboardController::class, 'connectCalendar'])->name('employee.calendar.connect');
    Route::get('/employee/calendar/callback', [EmployeeDashboardController::class, 'calendarCallback'])->name('employee.calendar.callback');
    Route::post('/employee/calendar/disconnect', [EmployeeDashboardController::class, 'disconnectCalendar'])->name('employee.calendar.disconnect');
    Route::get('/employee/subscriptions/expiring', [EmployeeSubscriptionsController::class, 'expiring'])->name('employee.subscriptions.expiring')->middleware('permission:subscriptions.view');
    Route::get('/employee/orders', [EmployeeOrdersController::class, 'index'])->name('employee.orders')->middleware('permission:orders.view');
    Route::get('/employee/orders/create', [CreateOrderController::class, 'create'])->name('employee.orders.create')->middleware('permission:orders.create');
    Route::post('/employee/orders', [CreateOrderController::class, 'store'])->name('employee.orders.store')->middleware('permission:orders.create');
    Route::get('/employee/orders/{order}', [EmployeeOrdersController::class, 'show'])->name('employee.orders.show');
    Route::post('/employee/orders/{order}/closing-approval/resubmit', [EmployeeOrdersController::class, 'resubmitClosingValue'])->name('employee.orders.closing-approval.resubmit');
    Route::post('/employee/orders/{order}/complete', [EmployeeOrdersController::class, 'complete'])->name('employee.orders.complete');
    Route::get('/employee/orders/{order}/complete', function (\App\Models\SalesOrder $order) {
        return redirect()->route('employee.orders.show', $order);
    });
    Route::post('/employee/orders/{order}/api-key', [EmployeeOrdersController::class, 'storeApiKey'])->name('employee.orders.api-key.store');
    Route::post('/employee/orders/{order}/api-key/reveal', [EmployeeOrdersController::class, 'revealApiKey'])->name('employee.orders.api-key.reveal');
    Route::get('/employee/customers', [EmployeeCustomersController::class, 'index'])->name('employee.customers')->middleware('permission:customers.view');
    Route::get('/employee/payments', [EmployeePaymentsController::class, 'index'])->name('employee.payments')->middleware('permission:payments.view');
    Route::get('/employee/subscriptions', [EmployeeSubscriptionsController::class, 'index'])->name('employee.subscriptions')->middleware('permission:subscriptions.view');
    Route::get('/employee/follow-ups', [FollowUpsController::class, 'index'])->name('employee.follow-ups')->middleware('permission:follow_ups.view');
    Route::get('/employee/follow-ups/create', [FollowUpsController::class, 'create'])->name('employee.follow-ups.create')->middleware('permission:follow_ups.create');
    Route::post('/employee/follow-ups', [FollowUpsController::class, 'store'])->name('employee.follow-ups.store')->middleware('permission:follow_ups.create');
    Route::get('/employee/follow-ups/{followUp}', [FollowUpsController::class, 'show'])->name('employee.follow-ups.show')->middleware('permission:follow_ups.view');
    Route::post('/employee/follow-ups/{followUp}/complete', [FollowUpsController::class, 'complete'])->name('employee.follow-ups.complete')->middleware('permission:follow_ups.edit');
    Route::post('/employee/follow-ups/{followUp}/reschedule', [FollowUpsController::class, 'reschedule'])->name('employee.follow-ups.reschedule')->middleware('permission:follow_ups.edit');
    Route::post('/employee/follow-ups/{followUp}/cancel', [FollowUpsController::class, 'cancel'])->name('employee.follow-ups.cancel')->middleware('permission:follow_ups.edit');
    Route::get('/employee/notifications', [EmployeeNotificationsController::class, 'index'])->name('employee.notifications');
    Route::get('/employee/reports', [EmployeeDashboardController::class, 'reports'])->name('employee.reports')->middleware('permission:sidebar.reports');
    Route::get('/employee/settings', [EmployeeDashboardController::class, 'settings'])->name('employee.settings');
    Route::get('/employee/help', [EmployeeDashboardController::class, 'help'])->name('employee.help');

    // ---- CRM Customer Issues (Employee only) ----
    Route::get('/employee/customer-issues', [\App\Http\Controllers\Employee\CustomerIssuesController::class, 'index'])->name('employee.customer-issues.index');
    Route::get('/employee/customer-issues/create', [\App\Http\Controllers\Employee\CustomerIssuesController::class, 'create'])->name('employee.customer-issues.create');
    Route::post('/employee/customer-issues', [\App\Http\Controllers\Employee\CustomerIssuesController::class, 'store'])->name('employee.customer-issues.store');
    Route::get('/employee/customer-issues/{customer_issue}', [\App\Http\Controllers\Employee\CustomerIssuesController::class, 'show'])->name('employee.customer-issues.show');
    Route::get('/employee/customer-issues/{customer_issue}/edit', [\App\Http\Controllers\Employee\CustomerIssuesController::class, 'edit'])->name('employee.customer-issues.edit');
    Route::put('/employee/customer-issues/{customer_issue}', [\App\Http\Controllers\Employee\CustomerIssuesController::class, 'update'])->name('employee.customer-issues.update');
});
