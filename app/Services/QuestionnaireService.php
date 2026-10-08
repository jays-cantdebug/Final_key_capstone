<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LookupRecordInUseException;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;

/**
 * Handles persistence and retrieval of Questionnaire template records.
 */
class QuestionnaireService
{
    public function __construct(private readonly DatabaseManager $database) {}

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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Questionnaire
    {
        return $this->database->transaction(fn (): Questionnaire => Questionnaire::query()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws LookupRecordInUseException if it would leave the Active version under an Inactive/Archived questionnaire.
     */
    public function update(Questionnaire $questionnaire, array $data): Questionnaire
    {
        // The New Assessment wizard always uses the one Active version, so
        // its questionnaire can't be made Inactive/Archived underneath it
        // (activation refuses the reverse; see QuestionnaireVersionService).
        if (
            ($data['status'] ?? Questionnaire::STATUS_ACTIVE) !== Questionnaire::STATUS_ACTIVE
            && $questionnaire->versions()->where('status', QuestionnaireVersion::STATUS_ACTIVE)->exists()
        ) {
            throw new LookupRecordInUseException(
                'This questionnaire has the Active version, so it must stay Active. Activate a version of another questionnaire first, then change this status.'
            );
        }

        return $this->database->transaction(function () use ($questionnaire, $data): Questionnaire {
            $questionnaire->update($data);

            return $questionnaire->refresh();
        });
    }

    /**
     * Archive (soft-delete) a questionnaire.
     *
     * @throws LookupRecordInUseException if any of its versions has been
     *                                    used by an assessment, or is the
     *                                    currently Active version (which
     *                                    would otherwise keep being used by
     *                                    the New Assessment wizard while
     *                                    hidden from Questionnaire Management).
     */
    public function delete(Questionnaire $questionnaire): void
    {
        $hasAssessments = $questionnaire->versions()->whereHas('assessments')->exists();

        if ($hasAssessments) {
            throw new LookupRecordInUseException(
                'Cannot archive a questionnaire with a version that has been used by an assessment.'
            );
        }

        if ($questionnaire->versions()->where('status', QuestionnaireVersion::STATUS_ACTIVE)->exists()) {
            throw new LookupRecordInUseException(
                'Activate another version before archiving this questionnaire.'
            );
        }

        $this->database->transaction(static fn (): bool => (bool) $questionnaire->delete());
    }
}
