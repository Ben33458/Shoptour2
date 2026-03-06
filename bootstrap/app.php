<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Custom middleware aliases
        $middleware->alias([
            'admin'          => \App\Http\Middleware\AdminMiddleware::class,
            'company'        => \App\Http\Middleware\CompanyMiddleware::class,
            'driver.auth'    => \App\Http\Middleware\DriverAuth::class,
            'role'           => \App\Http\Middleware\EnsureRole::class,
            'customer.scope' => \App\Http\Middleware\EnsureCustomerScope::class,
        ]);

        // Add security headers to every web response
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
