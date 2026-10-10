<?php

declare(strict_types=1);

namespace App\Casts;

/**
 * Encrypted text, byte-compatible with Laravel's built-in `encrypted` cast
 * (same Crypt primitive), but an unreadable value reads as null and is
 * flagged instead of throwing (see EncryptedCast). Used for counseling
 * session notes.
 */
class EncryptedString extends EncryptedCast
{
    protected function fromPlaintext(string $plaintext): string
    {
        return $plaintext;
    }
}
