<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A student-device action that the draft's current state doesn't allow
 * (e.g. an answer before consent, or after Done). `$state` is what the
 * device is told: consent, answering, locked or unavailable.
 */
class RemoteDraftStateException extends RuntimeException
{
    public function __construct(public readonly string $state)
    {
        parent::__construct("Remote draft is {$state}.");
    }
}
