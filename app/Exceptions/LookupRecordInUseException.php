<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when attempting to delete (archive) a lookup record that is
 * still referenced elsewhere — e.g. a Course/Year Level/Section still
 * referenced by a Student, or a Questionnaire with a version that has
 * been used by an Assessment — per the business rule, deletion is
 * blocked in this case; deactivate instead where applicable.
 */
class LookupRecordInUseException extends Exception {}
