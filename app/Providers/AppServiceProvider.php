<?php

namespace App\Providers;

use App\Services\PermissionService;
use Illuminate\Support\Facades\Blade;
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
    }
}
