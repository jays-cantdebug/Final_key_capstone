<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Request-scoped record of encrypted values that couldn't be decrypted
 * (typically: encrypted with a previous APP_KEY). Logs ONE warning per
 * model, id and column per request: never the value, only where it is.
 * Bound with `scoped()` in AppServiceProvider.
 *
 * Across requests (docs/BUG_LOG.md N5): a record is logged by at most one
 * request per hour. The first request that meets it reserves it in the
 * cache (atomic Cache::add) and logs each of its unreadable columns; later
 * requests within the hour skip it, so a list page viewed all day doesn't
 * repeat the same lines. If the cache fails, the warning is logged anyway.
 */
class UnreadableEncryptedValues
{
    public const REPEAT_AFTER_SECONDS = 3600;

    /** @var array<string, true> */
    private array $logged = [];

    /** @var array<string, bool> record => whether this request logs it */
    private array $recordAllowed = [];

    public function record(Model $model, string $column): void
    {
        $recordKey = $model::class.'#'.($model->getKey() ?? 'new');
        $key = $recordKey.'.'.$column;

        if (isset($this->logged[$key])) {
            return;
        }

        $this->logged[$key] = true;

        if (! ($this->recordAllowed[$recordKey] ??= $this->reserve($recordKey))) {
            return;
        }

        Log::warning('Encrypted value could not be decrypted with the current APP_KEY (or APP_PREVIOUS_KEYS); shown as unreadable and left unchanged in the database.', [
            'model' => $model::class,
            'id' => $model->getKey(),
            'column' => $column,
        ]);
    }

    public function count(): int
    {
        return count($this->logged);
    }

    /**
     * True when no request logged this record in the last hour (and marks it).
     */
    private function reserve(string $recordKey): bool
    {
        try {
            return Cache::add('unreadable-encrypted-logged:'.sha1($recordKey), true, self::REPEAT_AFTER_SECONDS);
        } catch (Throwable) {
            return true;
        }
    }
}
