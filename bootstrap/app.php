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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'subscription.tier' => \App\Http\Middleware\CheckSubscriptionTier::class,
            'subscription.limits.predictions' => \App\Http\Middleware\CheckPredictionLimits::class,
            'subscription.limits.groups' => \App\Http\Middleware\CheckGroupLimits::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
