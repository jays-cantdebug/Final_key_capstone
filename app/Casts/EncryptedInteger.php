<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Same AES-256 primitive as Laravel's built-in `encrypted` cast (via the
 * `Crypt` facade), but decrypts back to an `int` rather than a string —
 * this codebase's `declare(strict_types=1)` convention (and tests that
 * `assertSame()` an exact int score) need the DASS-21 score columns to
 * keep their original type across the round trip.
 *
 * @implements CastsAttributes<int|null, int|string|null>
 */
class EncryptedInteger implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : (int) Crypt::decryptString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString((string) $value);
    }
}
