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
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SwitchDemoDatabaseConnection::class,
            \App\Http\Middleware\RestrictDemoOwnAccount::class,
        ]);

        // SubstituteBindings (route model binding, e.g. {client}) is one of
        // Laravel's own priority-sorted middleware, so it runs before any
        // appended 'web' group middleware regardless of array order unless
        // explicitly told otherwise. It has to run AFTER the database
        // switch above, or binding a Demo request's own {client} etc.
        // would look it up on the wrong (main) connection and 404.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SwitchDemoDatabaseConnection::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
