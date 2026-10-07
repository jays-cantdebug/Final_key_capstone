<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown by AssessmentService::save() when the wizard's answers, or its
 * cached Step 3 review, don't belong to the questionnaire version the
 * wizard was pinned to at Step 2 — e.g. a stale session from before
 * pinning existed, or a tampered request. Nothing is saved; the
 * Psychometrician is sent back to answer the questionnaire again.
 */
class QuestionnaireVersionMismatchException extends Exception
{
    public function __construct()
    {
        parent::__construct('Nothing was saved: the answers did not match the questionnaire version they were given for. Please answer the questionnaire again.');
    }
}
