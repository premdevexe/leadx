<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CheckInstallation
{
    public function handle(Request $request, Closure $next)
    {
        // Never gate the test environment.
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return $next($request);
        }

        $lockFile = storage_path('app/installed.lock');
        $isInstall = $request->is('install') || $request->is('install/*');

        if (File::exists($lockFile)) {
            // Installed: keep the application, but lock down the installer.
            if ($isInstall) {
                return redirect('/');
            }
            return $next($request);
        }

        // Not installed: only the installer may be reached.
        if ($isInstall) {
            return $next($request);
        }

        return redirect('/install');
    }
}
