<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AuthenticateAdmin::class,
            'ensure-role-active' => \App\Http\Middleware\EnsureRoleActive::class,
            'team.web' => \App\Http\Middleware\AuthenticateTeamWeb::class,
            'employee.web' => \App\Http\Middleware\AuthenticateEmployeeWeb::class,
            'permission' => \App\Http\Middleware\EnsureEmployeePermission::class,
        ]);
    })
    ->withCommands()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'CSRF token mismatch. Please refresh and try again.'], 419);
                }

                return back()->withInput()->with('error', 'Your session or security token expired. The page has been refreshed — please try again.');
            }

            return $response;
        });
    })->create();
