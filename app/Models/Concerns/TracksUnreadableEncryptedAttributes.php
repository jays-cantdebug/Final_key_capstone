<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * For models with encrypted attributes (EncryptedInteger, EncryptedString):
 * an attribute that can't be decrypted reads as null and is flagged here,
 * so views can say "Unreadable (encrypted with a previous key)" instead of
 * showing nothing or failing. The ciphertext itself is never touched.
 */
trait TracksUnreadableEncryptedAttributes
{
    /** @var array<string, true> */
    protected array $unreadableEncryptedAttributes = [];

    /** Called by the encrypted casts when decryption fails. */
    public function markEncryptedAttributeUnreadable(string $key): void
    {
        $this->unreadableEncryptedAttributes[$key] = true;
    }

    /** Whether the attribute's stored value can't be decrypted right now. */
    public function isUnreadable(string $key): bool
    {
        // Re-read, so a value replaced since (or readable after a key fix)
        // isn't reported from a stale flag.
        unset($this->unreadableEncryptedAttributes[$key]);
        $this->getAttribute($key);

        return isset($this->unreadableEncryptedAttributes[$key]);
    }

    /** Whether any of the given attributes is unreadable. */
    public function hasUnreadable(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if ($this->isUnreadable($key)) {
                return true;
            }
        }

        return false;
    }
}
