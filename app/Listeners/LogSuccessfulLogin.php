<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;

/**
 * Records a "Login" audit entry whenever Laravel's built-in Login event
 * fires (from Auth::attempt() in the frozen LoginRequest, Module 1).
 */
class LogSuccessfulLogin
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function handle(Login $event): void
    {
        $this->auditLogService->record(
            'Authentication',
            'Login',
            $event->user->getKey(),
            null,
            null,
            $event->user->getKey()
        );
    }
}
