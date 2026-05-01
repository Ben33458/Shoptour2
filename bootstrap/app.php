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
        // Trust the local nginx reverse proxy so that CSRF, URL generation
        // and redirect responses use the correct scheme/host/port.
        $middleware->trustProxies(at: '*');

        // Custom middleware aliases
        $middleware->alias([
            'admin'            => \App\Http\Middleware\AdminMiddleware::class,
            'company'          => \App\Http\Middleware\CompanyMiddleware::class,
            'driver.auth'      => \App\Http\Middleware\DriverAuth::class,
            'role'             => \App\Http\Middleware\EnsureRole::class,
            'customer.scope'   => \App\Http\Middleware\EnsureCustomerScope::class,
            'wawi.token'       => \App\Http\Middleware\WawiTokenMiddleware::class,
            'employee-session' => \App\Http\Middleware\EmployeeSession::class,
            'sub_user'         => \App\Http\Middleware\SubUserPermission::class,
            'shop.order'       => \App\Http\Middleware\EnforceShopOrderPermission::class,
        ]);

        // Add security headers to every web response
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);

        // Logout routes don't need CSRF — worst case an attacker logs out the user
        $middleware->validateCsrfTokens(except: [
            'mein/logout',
            'abmelden',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
