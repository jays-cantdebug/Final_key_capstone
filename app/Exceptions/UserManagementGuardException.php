<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a User Management action would violate the Account
 * Recovery Safety Net (e.g. demoting the last remaining active
 * Psychometrician account away from that role).
 */
class UserManagementGuardException extends RuntimeException
{
}
