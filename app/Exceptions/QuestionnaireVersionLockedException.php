<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation is attempted against a questionnaire version
 * that is no longer in a Draft state (i.e. it is Active or Archived).
 *
 * Active versions are protected because editing an active questionnaire
 * is prohibited by design; Archived versions are protected because they
 * must remain available, unmodified, for historical assessments.
 */
class QuestionnaireVersionLockedException extends RuntimeException {}
