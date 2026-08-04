<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureSingleActiveSession;
use App\Http\Middleware\EnsureUserHasRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '127.0.0.1');
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'single-session' => EnsureSingleActiveSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
