<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use Exception;

/**
 * Thrown by an AI provider scaffold that has not yet been implemented
 * (e.g. ClaudeAIProvider) when a prediction is requested from it.
 */
class AIProviderNotImplementedException extends Exception
{
}
