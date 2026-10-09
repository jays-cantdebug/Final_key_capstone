<?php

declare(strict_types=1);

namespace App\Casts;

use App\Exceptions\UnreadableEncryptedValueException;
use App\Support\UnreadableEncryptedValues;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Shared by the encrypted casts: Crypt::encryptString()/decryptString()
 * (AES-256-CBC + HMAC, keyed by APP_KEY, with APP_PREVIOUS_KEYS tried for
 * decryption), byte-compatible with Laravel's built-in `encrypted` cast.
 *
 * A value that can't be decrypted (encrypted with a key that is no longer
 * configured) reads as null instead of throwing, is flagged on the model
 * (TracksUnreadableEncryptedAttributes) and logged once per request. It is
 * never written back: reading doesn't make it dirty, and setting it to null
 * throws UnreadableEncryptedValueException. Replacing it with a real new
 * value is allowed.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
abstract class EncryptedCast implements CastsAttributes, EncryptedAttribute
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            return $this->fromPlaintext(Crypt::decryptString((string) $value));
        } catch (DecryptException) {
            app(UnreadableEncryptedValues::class)->record($model, $key);

            if (method_exists($model, 'markEncryptedAttributeUnreadable')) {
                $model->markEncryptedAttributeUnreadable($key);
            }

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            if (method_exists($model, 'isUnreadable') && $model->isUnreadable($key)) {
                throw UnreadableEncryptedValueException::for($model::class, $model->getKey(), $key);
            }

            return null;
        }

        return Crypt::encryptString((string) $value);
    }

    abstract protected function fromPlaintext(string $plaintext): mixed;
}
