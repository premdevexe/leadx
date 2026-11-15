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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AuthenticateAdmin::class,
            'ensure-role-active' => \App\Http\Middleware\EnsureRoleActive::class,
            'team.web' => \App\Http\Middleware\AuthenticateTeamWeb::class,
            'employee.web' => \App\Http\Middleware\AuthenticateEmployeeWeb::class,
        ]);
    })
    ->withCommands()
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
