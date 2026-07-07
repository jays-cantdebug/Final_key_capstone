<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when attempting to delete (archive) a Course, Year Level, or
 * Section that is still referenced by at least one Student — per the
 * business rule, deletion is blocked in this case; deactivate instead.
 */
class LookupRecordInUseException extends Exception
{
}
