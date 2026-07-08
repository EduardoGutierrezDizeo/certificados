<?php

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\VerifyInternalApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            ForcePasswordChange::class,
        ]);
        $middleware->alias([
            'internal.api' => VerifyInternalApiKey::class,
            'role' => RoleMiddleware::class,
            'force.password.change' => ForcePasswordChange::class,
            'subscription.active' => EnsureSubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
