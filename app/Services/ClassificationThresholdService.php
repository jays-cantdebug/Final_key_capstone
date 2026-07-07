<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassificationThreshold;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Manages the guarded Override Mode for DASS-21 classification thresholds:
 * detecting drift from the official published values, saving an override,
 * and restoring the official values — each save recorded as a single,
 * consolidated Audit Log entry with a real before/after diff.
 */
class ClassificationThresholdService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * All 15 threshold rows, ordered for display (subscale, then official
     * clinical severity order).
     *
     * @return Collection<int, ClassificationThreshold>
     */
    public function all(): Collection
    {
        $severityOrder = array_flip(ClassificationThreshold::severityOrder());

        return ClassificationThreshold::query()
            ->orderBy('subscale')
            ->get()
            ->sortBy(fn (ClassificationThreshold $threshold): int => $severityOrder[$threshold->severity_level] ?? 99)
            ->values();
    }

    /**
     * Whether any threshold currently differs from the official, seeded
     * value — drives the persistent "Non-official thresholds are in
     * effect" warning banner.
     */
    public function isOverridden(): bool
    {
        return $this->diffAgainst($this->officialValuesByKey()) !== [];
    }

    /**
     * Save an Override Mode edit: updates every row's min/max score and
     * records a single "Classification Threshold Override" audit entry
     * containing only the rows that actually changed. No entry is
     * recorded if nothing actually changed.
     *
     * @param array<int, array{id: int, min_score: int, max_score: int}> $rows
     */
    public function update(array $rows): void
    {
        $this->database->transaction(function () use ($rows): void {
            $targets = [];
            foreach ($rows as $row) {
                $targets[(int) $row['id']] = [
                    'min_score' => (int) $row['min_score'],
                    'max_score' => (int) $row['max_score'],
                ];
            }

            $this->applyAndLog($targets, 'Classification Threshold Override');
        });
    }

    /**
     * Reset every threshold to the official, published value and record
     * a single "Classification Threshold Restore" audit entry containing
     * only the rows that actually changed.
     */
    public function restoreOfficial(): void
    {
        $this->database->transaction(function (): void {
            $targets = [];
            foreach (ClassificationThreshold::all() as $threshold) {
                $official = $this->officialValuesByKey()[$this->key($threshold->subscale, $threshold->severity_level)] ?? null;

                if ($official !== null) {
                    $targets[$threshold->id] = $official;
                }
            }

            $this->applyAndLog($targets, 'Classification Threshold Restore');
        });
    }

    /**
     * Apply the given {id: [min_score, max_score]} targets, updating only
     * rows that actually differ, and write a single audit log entry for
     * the whole batch (keyed by "Subscale:SeverityLevel") if anything
     * changed.
     *
     * @param array<int, array{min_score: int, max_score: int}> $targets
     */
    private function applyAndLog(array $targets, string $action): void
    {
        $oldValues = [];
        $newValues = [];

        $thresholds = ClassificationThreshold::query()->whereIn('id', array_keys($targets))->get()->keyBy('id');

        foreach ($targets as $id => $target) {
            $threshold = $thresholds->get($id);

            if ($threshold === null) {
                continue;
            }

            if ($threshold->min_score === $target['min_score'] && $threshold->max_score === $target['max_score']) {
                continue;
            }

            $key = $this->key($threshold->subscale, $threshold->severity_level);
            $oldValues[$key] = ['min_score' => $threshold->min_score, 'max_score' => $threshold->max_score];
            $newValues[$key] = $target;

            $threshold->update($target);
        }

        if ($oldValues !== []) {
            $this->auditLogService->record('Classification Thresholds', $action, null, $oldValues, $newValues);
        }
    }

    /**
     * @return array<int, array{subscale: string, severity_level: string, min_score: int, max_score: int}>
     */
    private function diffAgainst(array $officialByKey): array
    {
        $diff = [];

        foreach (ClassificationThreshold::all() as $threshold) {
            $official = $officialByKey[$this->key($threshold->subscale, $threshold->severity_level)] ?? null;

            if ($official === null) {
                continue;
            }

            if ($threshold->min_score !== $official['min_score'] || $threshold->max_score !== $official['max_score']) {
                $diff[] = $threshold;
            }
        }

        return $diff;
    }

    /**
     * @return array<string, array{min_score: int, max_score: int}>
     */
    private function officialValuesByKey(): array
    {
        $byKey = [];

        foreach (ClassificationThreshold::officialValues() as $row) {
            $byKey[$this->key($row['subscale'], $row['severity_level'])] = [
                'min_score' => $row['min_score'],
                'max_score' => $row['max_score'],
            ];
        }

        return $byKey;
    }

    private function key(string $subscale, string $severityLevel): string
    {
        return $subscale.':'.$severityLevel;
    }
}
