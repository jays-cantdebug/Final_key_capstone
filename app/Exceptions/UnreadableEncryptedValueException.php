<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code tries to replace an unreadable encrypted value with
 * null: the original ciphertext must stay in the database so the value can
 * still be recovered with the old key (APP_PREVIOUS_KEYS).
 */
class UnreadableEncryptedValueException extends RuntimeException
{
    public static function for(string $model, mixed $id, string $column): self
    {
        return new self(sprintf(
            'Refusing to overwrite the unreadable encrypted %s.%s (id %s) with an empty value; the original ciphertext is kept.',
            class_basename($model),
            $column,
            (string) $id,
        ));
    }
}
