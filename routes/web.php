<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\CustomersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamsController;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
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
    Route::put('/admin/orders/{order}/activate', [OrdersController::class, 'activate'])->name('admin.orders.activate');
    Route::post('/admin/orders/{order}/whatsapp-share-log', [OrdersController::class, 'recordWhatsappShareLog'])->name('admin.orders.whatsapp-log');
    Route::post('/admin/orders/{order}/payments', [OrdersController::class, 'recordPayment'])->name('admin.orders.payments.store');
    Route::post('/admin/orders/{order}/payments/{payment}/verify', [OrdersController::class, 'verifyPayment'])->name('admin.orders.payments.verify');
    Route::post('/admin/orders/{order}/payments/{payment}/reject', [OrdersController::class, 'rejectPayment'])->name('admin.orders.payments.reject');

    // Settings (payment methods, …)
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::put('/admin/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    // Employee management
    Route::get('/admin/employees', [EmployeesController::class, 'index'])->name('admin.employees');
    Route::get('/admin/employees/create', [EmployeesController::class, 'create'])->name('admin.employees.create');
    Route::post('/admin/employees', [EmployeesController::class, 'store'])->name('admin.employees.store');
    Route::get('/admin/employees/{employee}/edit', [EmployeesController::class, 'edit'])->name('admin.employees.edit');
    Route::put('/admin/employees/{employee}', [EmployeesController::class, 'update'])->name('admin.employees.update');
    Route::delete('/admin/employees/{employee}', [EmployeesController::class, 'destroy'])->name('admin.employees.destroy');

    // Customer management (CRM)
    Route::get('/admin/customers', [CustomersController::class, 'index'])->name('admin.customers');
    Route::get('/admin/customers/{customer}', [CustomersController::class, 'show'])->name('admin.customers.show');

    // Notification sending (Pusher Beams web push)
    Route::get('/admin/notifications', [NotificationsController::class, 'index'])->name('admin.notifications');
    Route::post('/admin/notifications', [NotificationsController::class, 'store'])->name('admin.notifications.store');
    Route::delete('/admin/notifications', [NotificationsController::class, 'clear'])->name('admin.notifications.clear');

    Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
});
