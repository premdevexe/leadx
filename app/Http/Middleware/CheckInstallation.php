<?php

namespace App\Http\Middleware;

use App\Services\Installer\InstallationStateManager;
use Closure;
use Illuminate\Http\Request;

class CheckInstallation
{
    protected InstallationStateManager $stateManager;

    public function __construct(InstallationStateManager $stateManager)
    {
        $this->stateManager = $stateManager;
    }

    public function handle(Request $request, Closure $next)
    {
        // Allow unit tests to bypass unless specifically testing installation middleware
        if ((app()->runningUnitTests() || app()->environment('testing'))
            && ! config('installer.testing_middleware')
            && ! $request->hasHeader('X-Test-Enforce-Installer')) {
            return $next($request);
        }

        $isInstalled = $this->stateManager->isInstalled();
        $path = trim($request->path(), '/');

        // Check if current route is an installer route
        $isInstall = $request->is('install')
            || $request->is('install/*')
            || $request->is('installer')
            || $request->is('installer/*');

        // Exemption: Admin recovery / setup core routes are handled by auth:admin, not blocked
        if ($request->is('admin/installer/recovery*') || $request->is('admin/setup/core*')) {
            return $next($request);
        }

        if ($isInstalled) {
            // When installed, lock down the installer
            if ($isInstall) {
                // Allow completion screen immediately after finish
                if ($request->is('install/finish') || $request->routeIs('install.finish')) {
                    return $next($request);
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Application is already installed and locked.',
                    ], 403);
                }

                return redirect('/')->with('info', 'Application is already installed.');
            }

            return $next($request);
        }

        // When NOT installed: only the installer and public asset routes may be reached
        if ($isInstall || $this->shouldSkipWhenUninstalled($request, $path)) {
            return $next($request);
        }

        return redirect('/install');
    }

    /**
     * Paths allowed through even when the application is uninstalled.
     */
    protected function shouldSkipWhenUninstalled(Request $request, string $path): bool
    {
        if ($path === 'up' || str_starts_with($path, 'api/health')) {
            return true;
        }

        if (str_starts_with($path, 'assets/')
            || str_starts_with($path, 'css/')
            || str_starts_with($path, 'js/')
            || $path === 'favicon.ico') {
            return true;
        }

        return false;
    }
}
