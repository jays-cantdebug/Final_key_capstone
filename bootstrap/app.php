<?php

use App\Http\Middleware\EnsureSingleActiveSession;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RestrictStudentDeviceNetwork;
use App\Http\Middleware\StaffSecurityHeaders;
use App\Http\Middleware\StudentDeviceHeaders;
use App\Http\Responses\StudentDeviceResponse;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The student device's routes, outside the `web` group: no
        // session, no CSRF, no auth (see routes/student-device.php).
        then: function (): void {
            Route::middleware('student-device')->group(base_path('routes/student-device.php'));
        },
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '127.0.0.1');
        // Frame protection etc. for login and staff pages; `/s` has its own
        // (StudentDeviceHeaders). Prepended, so it is outermost and also
        // covers responses built before the controller (e.g. the guest
        // redirect from `auth`, which the priority list runs early).
        $middleware->web(prepend: [StaffSecurityHeaders::class]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'single-session' => EnsureSingleActiveSession::class,
            'active' => EnsureUserIsActive::class,
        ]);
        $middleware->group('student-device', [
            // First: an address not on REMOTE_ASSESSMENT_ALLOWED_IPS (when
            // set) gets the generic 404 before anything else runs.
            RestrictStudentDeviceNetwork::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StudentDeviceHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Any error on a student-device path is the generic message, never
        // Laravel's error page or a debug stack trace.
        $exceptions->render(fn (Throwable $exception, Request $request) => StudentDeviceResponse::forException($exception, $request));
    })->create();
