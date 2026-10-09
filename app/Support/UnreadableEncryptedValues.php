<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Request-scoped record of encrypted values that couldn't be decrypted
 * (typically: encrypted with a previous APP_KEY). Logs ONE warning per
 * model, id and column per request: never the value, only where it is.
 * Bound with `scoped()` in AppServiceProvider.
 */
class UnreadableEncryptedValues
{
    /** @var array<string, true> */
    private array $logged = [];

    public function record(Model $model, string $column): void
    {
        $key = $model::class.'#'.($model->getKey() ?? 'new').'.'.$column;

        if (isset($this->logged[$key])) {
            return;
        }

        $this->logged[$key] = true;

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
}
