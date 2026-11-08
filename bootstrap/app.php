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

        // ✅ Middlewares do grupo WEB
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ✅ Alias de middlewares (substitui o Kernel.php)
        $middleware->alias([
            'plan.active' => \App\Http\Middleware\EnsurePlanActive::class,
        ]);

        // (opcional) se você quiser criar um alias de admin, exemplo:
        // $middleware->alias([
        //     'admin' => \App\Http\Middleware\EnsureAdmin::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();