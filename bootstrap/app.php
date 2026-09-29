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
        // Pred aplikáciou je Cloudflare a za ním reverzná proxy v Dockeri.
        // Dôverujeme priamemu susedovi, privátnym sieťam a Cloudflaru – inak
        // by request()->ip() vracalo adresu Cloudflaru namiesto používateľa.
        $middleware->trustProxies(at: [
            'REMOTE_ADDR',
            'PRIVATE_SUBNETS',
            ...\App\Support\Cloudflare::IP_RANGES,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
