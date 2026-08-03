<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LookupRecordInUseException;
use App\Models\Questionnaire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;

/**
 * Handles persistence and retrieval of Questionnaire template records.
 */
class QuestionnaireService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    /**
     * Paginate questionnaires with their version counts loaded.
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return Questionnaire::query()
            ->withCount('versions')
            ->orderBy('title')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Questionnaire
    {
        return $this->database->transaction(fn (): Questionnaire => Questionnaire::query()->create($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Questionnaire $questionnaire, array $data): Questionnaire
    {
        return $this->database->transaction(function () use ($questionnaire, $data): Questionnaire {
            $questionnaire->update($data);

            return $questionnaire->refresh();
        });
    }

    /**
     * Archive (soft-delete) a questionnaire.
     *
     * @throws LookupRecordInUseException if any of its versions has been
     *                                     used by an assessment.
     */
    public function delete(Questionnaire $questionnaire): void
    {
        $hasAssessments = $questionnaire->versions()->whereHas('assessments')->exists();

        if ($hasAssessments) {
            throw new LookupRecordInUseException(
                'Cannot delete a questionnaire with a version that has been used by an assessment.'
            );
        }

        $this->database->transaction(static fn (): bool => (bool) $questionnaire->delete());
    }
}
