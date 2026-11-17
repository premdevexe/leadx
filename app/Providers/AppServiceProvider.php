<?php

namespace App\Providers;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Polymorphic mapping for non-class legacy audit/request identifiers
        Relation::morphMap([
            'api_request' => \App\Models\ApiRequestLog::class,
        ]);

        // Rate limits for the unauthenticated public order share pages.
        // 'public-order-view' bounds how fast any visitor can open a link;
        // 'public-order-reveal' (tighter) bounds the optional API-key reveal,
        // so a leaked token cannot be hammered for the credential.
        RateLimiter::for('public-order-view', fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(60));
        RateLimiter::for('public-order-reveal', fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(10));

        Blade::if('canEmployee', function (string $permission) {
            $user = auth()->guard('employee')->user() ?? auth()->user();
            if (! $user) {
                return false;
            }

            return app(PermissionService::class)->hasPermission($user, $permission);
        });

        Blade::if('canCard', function (string $cardKey) {
            $user = auth()->guard('employee')->user() ?? auth()->user();
            if (! $user) {
                return false;
            }

            return app(PermissionService::class)->canViewDashboardCard($user, $cardKey);
        });

        Blade::if('canMenu', function (string $menuKey) {
            $user = auth()->guard('employee')->user() ?? auth()->user();
            if (! $user) {
                return false;
            }

            return app(PermissionService::class)->canViewSidebarMenu($user, $menuKey);
        });

        \App\Models\ApiSetting::bootstrapRuntimeConfig();
    }
}
