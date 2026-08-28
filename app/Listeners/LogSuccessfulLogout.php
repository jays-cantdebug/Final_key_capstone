<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\AuditLogService;
use Illuminate\Auth\Events\Logout;

/**
 * Records a "Logout" audit entry whenever Laravel's built-in Logout event
 * fires (from Auth::guard('web')->logout() in the frozen
 * AuthenticatedSessionController, Module 1).
 */
class LogSuccessfulLogout
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function handle(Logout $event): void
    {
        $this->auditLogService->record(
            'Authentication',
            'Logout',
            $event->user?->getKey(),
            null,
            null,
            $event->user?->getKey()
        );
    }
}
