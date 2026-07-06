<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Lockout;

/**
 * Records a "Login Lockout" audit entry whenever Laravel's built-in
 * Lockout event fires (from LoginRequest::ensureIsNotRateLimited(),
 * Module 1, which already calls event(new Lockout($this))).
 *
 * There is no authenticated user at lockout time, so user_id is resolved
 * on a best-effort basis from the attempted email; it remains null if no
 * matching account exists.
 */
class LogLoginLockout
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function handle(Lockout $event): void
    {
        $email = $event->request->input('email');
        $userId = $email ? User::query()->where('email', $email)->value('id') : null;

        $this->auditLogService->record(
            'Authentication',
            'Login Lockout',
            $userId,
            null,
            ['email' => $email],
            $userId
        );
    }
}
