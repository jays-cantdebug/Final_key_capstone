<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown by AssessmentService::save() when the student a New Assessment
 * would register already has an active record with the same name — e.g.
 * they were registered from another tab, or a stale wizard session or a
 * double submit reached the final save after Step 1's own check passed.
 * Nothing is saved; the Psychometrician is sent to Take Again instead.
 */
class DuplicateStudentException extends Exception
{
    /**
     * @param  array<int, int>  $studentIds  The matching active students.
     */
    public function __construct(public readonly array $studentIds)
    {
        parent::__construct('An active student with this name already exists.');
    }
}
