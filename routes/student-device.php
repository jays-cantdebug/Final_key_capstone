<?php

use App\Http\Controllers\StudentDeviceController;
use App\Http\Middleware\EnsureSameOrigin;
use App\Http\Middleware\RefuseStaffBrowser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student device routes
|--------------------------------------------------------------------------
|
| The only routes a student device uses. Registered from bootstrap/app.php
| under the `student-device` middleware group INSTEAD of `web`: no session,
| no CSRF token, no authentication — so a request here can never carry a
| staff identity, and no `sessions` row is ever created. Access is by a
| typed short code or a link token, then by the HttpOnly device cookie — and, when
| REMOTE_ASSESSMENT_ALLOWED_IPS is set, only from those addresses
| (RestrictStudentDeviceNetwork, first in the group). POSTs require a
| same-origin request (EnsureSameOrigin). Every response, errors included,
| carries StudentDeviceHeaders.
|
*/

Route::prefix('s')->name('student-device.')->group(function (): void {
    // Code and link entry: the only places a credential is tried. Failed
    // attempts are limited per IP in StudentDeviceController (successful
    // claims don't count, so a class behind one NAT isn't locked out).
    Route::get('/', [StudentDeviceController::class, 'entry'])
        ->middleware(['throttle:student-device-page', RefuseStaffBrowser::class])
        ->name('entry');
    Route::post('/', [StudentDeviceController::class, 'enterCode'])
        ->middleware(['throttle:student-device-page', EnsureSameOrigin::class, RefuseStaffBrowser::class])
        ->name('code');
    // The link from the live page's Copy link: GET only shows Begin (a link
    // preview can't use it up); the Begin POST claims.
    Route::get('/t/{token}', [StudentDeviceController::class, 'begin'])
        ->where('token', '[A-Za-z0-9_-]{43}')
        ->middleware(['throttle:student-device-page', RefuseStaffBrowser::class])
        ->name('begin');
    Route::post('/t/{token}', [StudentDeviceController::class, 'claim'])
        ->where('token', '[A-Za-z0-9_-]{43}')
        ->middleware(['throttle:student-device-page', EnsureSameOrigin::class, RefuseStaffBrowser::class])
        ->name('claim');

    Route::get('/q', [StudentDeviceController::class, 'show'])
        ->middleware(['throttle:student-device-page', RefuseStaffBrowser::class])
        ->name('show');

    Route::middleware(['throttle:student-device-device', EnsureSameOrigin::class])->group(function (): void {
        Route::post('/consent', [StudentDeviceController::class, 'consent'])->middleware(RefuseStaffBrowser::class)->name('consent');
        Route::post('/decline', [StudentDeviceController::class, 'decline'])->middleware(RefuseStaffBrowser::class)->name('decline');
        Route::post('/identity', [StudentDeviceController::class, 'identity'])->middleware(RefuseStaffBrowser::class)->name('identity');
        Route::post('/answer', [StudentDeviceController::class, 'answer'])->name('answer');
        Route::post('/done', [StudentDeviceController::class, 'done'])->name('done');
    });

    Route::get('/state', [StudentDeviceController::class, 'state'])
        ->middleware('throttle:student-device-device')
        ->name('state');
});
