<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LookupRecordInUseException;
use App\Models\Section;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence and retrieval of Section records.
 */
class SectionService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @return Collection<int, Section>
     */
    public function all(): Collection
    {
        return Section::query()->orderBy('section_name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Section
    {
        return $this->database->transaction(fn (): Section => Section::query()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Section $section, array $data): Section
    {
        return $this->database->transaction(function () use ($section, $data): Section {
            $section->update($data);

            return $section->refresh();
        });
    }

    /**
     * Archive (soft-delete) a section.
     *
     * @throws LookupRecordInUseException if any student still references
     *                                    this section — deactivate
     *                                    instead.
     */
    public function delete(Section $section): void
    {
        if ($section->students()->exists()) {
            throw new LookupRecordInUseException(
                'Cannot delete a section referenced by existing students. Deactivate it instead.'
            );
        }

        $this->database->transaction(static fn (): bool => (bool) $section->delete());
    }
}
