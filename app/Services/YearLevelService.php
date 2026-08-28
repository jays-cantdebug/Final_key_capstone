<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LookupRecordInUseException;
use App\Models\YearLevel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence and retrieval of Year Level records.
 */
class YearLevelService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @return Collection<int, YearLevel>
     */
    public function all(): Collection
    {
        return YearLevel::query()->orderBy('display_order')->get();
    }

    /**
     * The next unused display order, suggested as the default on the Add
     * Year Level form so the Psychometrician doesn't have to guess/hunt
     * for a free number (only active records count — an archived year
     * level's old order is free to be reassigned).
     */
    public function nextDisplayOrder(): int
    {
        return ((int) YearLevel::query()->max('display_order')) + 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): YearLevel
    {
        return $this->database->transaction(fn (): YearLevel => YearLevel::query()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(YearLevel $yearLevel, array $data): YearLevel
    {
        return $this->database->transaction(function () use ($yearLevel, $data): YearLevel {
            $yearLevel->update($data);

            return $yearLevel->refresh();
        });
    }

    /**
     * Archive (soft-delete) a year level.
     *
     * @throws LookupRecordInUseException if any student still references
     *                                    this year level — deactivate
     *                                    instead.
     */
    public function delete(YearLevel $yearLevel): void
    {
        if ($yearLevel->students()->exists()) {
            throw new LookupRecordInUseException(
                'Cannot delete a year level referenced by existing students. Deactivate it instead.'
            );
        }

        $this->database->transaction(static fn (): bool => (bool) $yearLevel->delete());
    }
}
