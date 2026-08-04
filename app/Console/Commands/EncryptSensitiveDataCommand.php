<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time backfill: encrypts existing plaintext values in the columns
 * that just gained an `encrypted` Eloquent cast (dass_responses.answer_value,
 * dass_results' six raw/final score columns, counseling_sessions.session_notes)
 * so already-stored rows match what the app now expects to read.
 *
 * Deliberately writes via the query builder (`DB::table`), not Eloquent —
 * this bypasses model events entirely, so AuditableObserver never fires and
 * the audit trail isn't flooded with thousands of "Update" entries for a
 * maintenance operation. It also sidesteps a real trap: loading a row
 * through the model and reading the now-`encrypted`-cast attribute would
 * try to decrypt still-plaintext legacy data and throw immediately.
 *
 * Idempotent: a value already shaped like a Laravel encryption payload is
 * skipped, so this command is safe to re-run (e.g. if it was interrupted
 * partway through a previous run).
 */
class EncryptSensitiveDataCommand extends Command
{
    protected $signature = 'security:encrypt-sensitive-data {--dry-run : Report how many rows would be encrypted without writing anything}';

    protected $description = 'Backfill-encrypt existing plaintext DASS responses/scores and counseling session notes after the encrypted cast is deployed';

    /**
     * @var array<int, array{table: string, columns: array<int, string>}>
     */
    private const TARGETS = [
        ['table' => 'dass_responses', 'columns' => ['answer_value']],
        ['table' => 'dass_results', 'columns' => [
            'depression_raw_score', 'anxiety_raw_score', 'stress_raw_score',
            'depression_final_score', 'anxiety_final_score', 'stress_final_score',
        ]],
        ['table' => 'counseling_sessions', 'columns' => ['session_notes']],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (self::TARGETS as $target) {
            $this->encryptTable($target['table'], $target['columns'], $dryRun);
        }

        $this->info($dryRun ? 'Dry run complete — no rows were modified.' : 'Backfill encryption complete.');

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $columns
     */
    private function encryptTable(string $table, array $columns, bool $dryRun): void
    {
        $encryptedCount = 0;
        $skippedCount = 0;

        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $columns, $dryRun, &$encryptedCount, &$skippedCount) {
                DB::transaction(function () use ($rows, $table, $columns, $dryRun, &$encryptedCount, &$skippedCount) {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            $value = $row->{$column};

                            if ($value === null || $this->looksAlreadyEncrypted((string) $value)) {
                                continue;
                            }

                            $updates[$column] = Crypt::encryptString((string) $value);
                        }

                        if ($updates === []) {
                            $skippedCount++;

                            continue;
                        }

                        if (! $dryRun) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }

                        $encryptedCount++;
                    }
                });
            });

        $this->line(sprintf('%s: %d row(s) encrypted, %d already encrypted/skipped.', $table, $encryptedCount, $skippedCount));
    }

    /**
     * A Laravel encryption payload is base64 JSON with `iv`/`value`/`mac`
     * keys. Plaintext DASS answers/scores are short numeric strings and
     * session notes are free text — neither will ever coincidentally match
     * that shape, so this is a reliable "already migrated" guard.
     */
    private function looksAlreadyEncrypted(string $value): bool
    {
        if (! Str::isJson(base64_decode($value, true) ?: '')) {
            return false;
        }

        $decoded = json_decode(base64_decode($value, true), true);

        return is_array($decoded)
            && array_key_exists('iv', $decoded)
            && array_key_exists('value', $decoded)
            && array_key_exists('mac', $decoded);
    }
}
