<?php

use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AuthenticationController as AdminAuth;
use App\Http\Controllers\Api\V1\Admin\EmployeesController;
use App\Http\Controllers\Api\V1\Admin\PaymentsController;
use App\Http\Controllers\Api\V1\Admin\ReportsController;
use App\Http\Controllers\Api\V1\Admin\TeamsController;
use App\Http\Controllers\Api\V1\Employee\AnalyticsController as EmployeeAnalytics;
use App\Http\Controllers\Api\V1\Employee\AuthenticationController as EmployeeAuth;
use App\Http\Controllers\Api\V1\Employee\GoogleCalendarController;
use App\Http\Controllers\Api\V1\FollowUpsController;
use App\Http\Controllers\Api\V1\GatewayWebhookController;
use App\Http\Controllers\Api\V1\OrderCredentialController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\Team\AnalyticsController as TeamAnalytics;
use App\Http\Controllers\Api\V1\Team\AuthenticationController as TeamAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Super Admin ----
    Route::post('admin/login', [AdminAuth::class, 'login']);

    Route::middleware(['auth:sanctum', 'ensure-role-active:admin'])->group(function () {
        Route::get('admin/profile', [AdminAuth::class, 'profile']);
        Route::post('admin/logout', [AdminAuth::class, 'logout']);

        // Team management
        Route::get('admin/teams', [TeamsController::class, 'index']);
        Route::post('admin/teams', [TeamsController::class, 'store']);
        Route::get('admin/teams/{team}', [TeamsController::class, 'show']);
        Route::put('admin/teams/{team}', [TeamsController::class, 'update']);
        Route::delete('admin/teams/{team}', [TeamsController::class, 'destroy']);
        Route::post('admin/teams/{team}/members', [TeamsController::class, 'assignMember']);
        Route::delete('admin/teams/{team}/members/{user}', [TeamsController::class, 'removeMember']);

        // Employee management
        Route::get('admin/employees', [EmployeesController::class, 'index']);
        Route::post('admin/employees', [EmployeesController::class, 'store']);
        Route::get('admin/employees/{employee}', [EmployeesController::class, 'show']);
        Route::put('admin/employees/{employee}', [EmployeesController::class, 'update']);
        Route::delete('admin/employees/{employee}', [EmployeesController::class, 'destroy']);

        // Payment management (Phase 07)
        Route::get('admin/payments', [PaymentsController::class, 'index']);
        Route::get('admin/payments/{payment}', [PaymentsController::class, 'show']);
        Route::post('admin/payments', [PaymentsController::class, 'store']);
        Route::post('admin/payments/{payment}/verify', [PaymentsController::class, 'verify']);
        Route::post('admin/payments/{payment}/reject', [PaymentsController::class, 'reject']);

        // Sales Analytics & Reports (Phase 10)
        Route::get('admin/analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('admin/analytics/revenue', [AnalyticsController::class, 'revenue']);
        Route::get('admin/analytics/products', [AnalyticsController::class, 'productReport']);
        Route::get('admin/analytics/employees', [AnalyticsController::class, 'employees']);
        Route::get('admin/analytics/teams', [AnalyticsController::class, 'teams']);
        Route::get('admin/analytics/monthly', [AnalyticsController::class, 'monthly']);
        Route::get('admin/analytics/summary', [AnalyticsController::class, 'summary']);

        Route::get('admin/reports/orders', [ReportsController::class, 'orders']);
        Route::get('admin/reports/payments', [ReportsController::class, 'payments']);
        Route::get('admin/reports/subscriptions', [ReportsController::class, 'subscriptions']);
    });

    // ---- Team ----
    Route::post('team/login', [TeamAuth::class, 'login']);

    Route::middleware(['auth:sanctum', 'ensure-role-active:team'])->group(function () {
        Route::get('team/profile', [TeamAuth::class, 'profile']);
        Route::get('team/members', [TeamAuth::class, 'members']);
        Route::get('team/analytics', [TeamAnalytics::class, 'index']);
        Route::post('team/logout', [TeamAuth::class, 'logout']);
    });

    // ---- Individual Employee ----
    Route::post('employee/login', [EmployeeAuth::class, 'login']);

    Route::middleware(['auth:sanctum', 'ensure-role-active:employee'])->group(function () {
        Route::get('employee/profile', [EmployeeAuth::class, 'profile']);
        Route::get('employee/analytics', [EmployeeAnalytics::class, 'index']);
        Route::post('employee/logout', [EmployeeAuth::class, 'logout']);

        // Phase 08, Module 2: personal Google Calendar OAuth (employee only).
        // The team shared login must never be used here.
        Route::get('employee/google/calendar/connect', [GoogleCalendarController::class, 'connect']);
        Route::get('employee/google/calendar', [GoogleCalendarController::class, 'status']);
        Route::post('employee/google/calendar/disconnect', [GoogleCalendarController::class, 'disconnect']);
    });

    // Phase 08, Module 2: OAuth callback is a top-level browser navigation
    // from Google (no Bearer token). Identity comes from the signed
    // single-use state, which is what structurally prevents a team login
    // from attaching a personal calendar.
    Route::get('google/calendar/callback', [GoogleCalendarController::class, 'callback']);
    Route::post('google/calendar/callback', [GoogleCalendarController::class, 'callback']);

    // ---- Orders (admin / team / employee; scoped per-actor in controller) ----
    Route::middleware('auth:sanctum')->group(function () {
        // Active catalog for order selection (database is the source of
        // truth for names, prices and durations).
        Route::get('products', [ProductsController::class, 'index']);

        Route::get('orders', [OrdersController::class, 'index']);
        Route::post('orders', [OrdersController::class, 'store']);
        Route::get('orders/by-number/{orderNumber}', [OrdersController::class, 'byNumber']);
        Route::get('orders/{order}', [OrdersController::class, 'show']);
        Route::get('orders/{order}/whatsapp-share', [OrdersController::class, 'whatsappShare']);
        Route::post('orders/{order}/whatsapp-share-log', [OrdersController::class, 'whatsappShareLog']);
        Route::put('orders/{order}/activate', [OrdersController::class, 'activate']);

        // Order delivery: the customer's own API key, per order. Status and
        // store are scoped per actor; reveal is the one authorized plaintext
        // read (never logged). No key is ever generated or echoed in list
        // endpoints — OrderResource does not expose it.
        Route::get('orders/{order}/api-key', [OrderCredentialController::class, 'show']);
        Route::post('orders/{order}/api-key', [OrderCredentialController::class, 'store']);
        Route::post('orders/{order}/api-key/reveal', [OrderCredentialController::class, 'reveal']);

        // Subscriptions (Phase 07) - read + lifecycle actions, scoped per
        // actor in the controller.
        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

        // Phase 08, Module 1 - customer follow-ups. Reads are scoped to the
        // actor's team/employee inside FollowUpsController; the customer
        // listing is restricted to verified CRM customers so it can be
        // dropped straight into the follow-up picker.
        Route::get('customers/verified', [FollowUpsController::class, 'verifiedCustomers']);
        Route::get('follow-ups', [FollowUpsController::class, 'index']);
        Route::post('follow-ups', [FollowUpsController::class, 'store']);
        Route::get('follow-ups/{followUp}', [FollowUpsController::class, 'show']);
        Route::get('follow-ups/{followUp}/history', [FollowUpsController::class, 'history']);
        Route::post('follow-ups/{followUp}/complete', [FollowUpsController::class, 'complete']);
        Route::post('follow-ups/{followUp}/reschedule', [FollowUpsController::class, 'reschedule']);
        Route::post('follow-ups/{followUp}/cancel', [FollowUpsController::class, 'cancel']);
    });
});

// ---- Gateway webhook (unauthenticated; trust comes from the gateway's
// signed payload, verified server-side - see GatewayService) ----
Route::post('v1/gateway/webhook', [GatewayWebhookController::class, 'handle']);