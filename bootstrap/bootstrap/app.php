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
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Named middleware aliases ──────────────────────────────────────────
        $middleware->alias([
            'admin'           => \App\Http\Middleware\AdminMiddleware::class,
            'company'         => \App\Http\Middleware\CompanyMiddleware::class,
            'role'            => \App\Http\Middleware\EnsureRole::class,
            'customer.scope'  => \App\Http\Middleware\EnsureCustomerScope::class,
        ]);

        // ── Global web middleware ─────────────────────────────────────────────
        // SecurityHeaders runs on every web response (admin, login, customer portal).
        // The API routes use Bearer tokens so they do not need the same headers.
        $middleware->web(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
