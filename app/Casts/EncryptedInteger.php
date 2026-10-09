<?php

declare(strict_types=1);

namespace App\Casts;

/**
 * Encrypts an integer at rest and decrypts it back to an int (Laravel's
 * built-in `encrypted` cast would return a string). Used for the DASS-21
 * answers and scores. An unreadable value reads as null, never 0 (see
 * EncryptedCast), so nothing can be computed from it.
 */
class EncryptedInteger extends EncryptedCast
{
    protected function fromPlaintext(string $plaintext): int
    {
        return (int) $plaintext;
    }
}
