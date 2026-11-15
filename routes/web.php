<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminsController;
use App\Http\Controllers\Admin\CustomersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailSettingsController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Admin\OrderCredentialController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\PermissionPresetsController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\TeamAccessGrantsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamsController;
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
    Route::get('/employee/subscriptions/expiring', [EmployeeSubscriptionsController::class, 'expiring'])->name('employee.subscriptions.expiring')->middleware('permission:subscriptions.view');
    Route::get('/employee/orders', [EmployeeOrdersController::class, 'index'])->name('employee.orders')->middleware('permission:orders.view');
    Route::get('/employee/orders/create', [CreateOrderController::class, 'create'])->name('employee.orders.create')->middleware('permission:orders.create');
    Route::post('/employee/orders', [CreateOrderController::class, 'store'])->name('employee.orders.store')->middleware('permission:orders.create');
    Route::get('/employee/orders/{order}', [EmployeeOrdersController::class, 'show'])->name('employee.orders.show');
    Route::post('/employee/orders/{order}/closing-approval/resubmit', [EmployeeOrdersController::class, 'resubmitClosingValue'])->name('employee.orders.closing-approval.resubmit');
    Route::post('/employee/orders/{order}/complete', [EmployeeOrdersController::class, 'complete'])->name('employee.orders.complete');
    Route::post('/employee/orders/{order}/api-key', [EmployeeOrdersController::class, 'storeApiKey'])->name('employee.orders.api-key.store');
    Route::post('/employee/orders/{order}/api-key/reveal', [EmployeeOrdersController::class, 'revealApiKey'])->name('employee.orders.api-key.reveal');
    Route::get('/employee/customers', [EmployeeCustomersController::class, 'index'])->name('employee.customers')->middleware('permission:customers.view');
    Route::get('/employee/payments', [EmployeePaymentsController::class, 'index'])->name('employee.payments')->middleware('permission:payments.view');
    Route::get('/employee/subscriptions', [EmployeeSubscriptionsController::class, 'index'])->name('employee.subscriptions')->middleware('permission:subscriptions.view');
    Route::get('/employee/follow-ups', [FollowUpsController::class, 'index'])->name('employee.follow-ups')->middleware('permission:follow_ups.view');
    Route::get('/employee/notifications', [EmployeeNotificationsController::class, 'index'])->name('employee.notifications');
    Route::get('/employee/reports', [EmployeeDashboardController::class, 'reports'])->name('employee.reports')->middleware('permission:sidebar.reports');
    Route::get('/employee/settings', [EmployeeDashboardController::class, 'settings'])->name('employee.settings');
    Route::get('/employee/help', [EmployeeDashboardController::class, 'help'])->name('employee.help');
});
