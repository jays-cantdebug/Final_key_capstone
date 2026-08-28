<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuestionnaireVersionLockedException;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence and lifecycle transitions (Draft / Active / Archived)
 * for Questionnaire Versions.
 *
 * Only one Questionnaire Version may be Active across the entire system at
 * any given time, since the New Assessment workflow always loads a single,
 * system-wide active questionnaire version.
 */
class QuestionnaireVersionService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * List all versions belonging to a questionnaire, most recent first.
     */
    public function listForQuestionnaire(Questionnaire $questionnaire): Collection
    {
        return $questionnaire->versions()
            ->withCount('questions')
            ->orderByDesc('version_number')
            ->get();
    }

    /**
     * Create a new Draft version for the given questionnaire.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Questionnaire $questionnaire, array $data): QuestionnaireVersion
    {
        return $this->database->transaction(function () use ($questionnaire, $data): QuestionnaireVersion {
            return $questionnaire->versions()->create([
                ...$data,
                'status' => QuestionnaireVersion::STATUS_DRAFT,
            ]);
        });
    }

    /**
     * Update a Draft version's version number / effective date.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws QuestionnaireVersionLockedException if the version is not Draft.
     */
    public function update(QuestionnaireVersion $version, array $data): QuestionnaireVersion
    {
        $this->assertEditable($version);

        return $this->database->transaction(function () use ($version, $data): QuestionnaireVersion {
            $version->update($data);

            return $version->refresh();
        });
    }

    /**
     * Permanently remove a version. Only Draft versions may be deleted;
     * Active and Archived versions are protected from deletion.
     *
     * @throws QuestionnaireVersionLockedException if the version is not Draft.
     */
    public function delete(QuestionnaireVersion $version): void
    {
        $this->assertEditable($version);

        $this->database->transaction(static function () use ($version): void {
            $version->delete();
        });
    }

    /**
     * Activate a version: archives whatever version is currently Active
     * anywhere in the system, then marks the given version Active.
     *
     * @throws QuestionnaireVersionLockedException if the version has no questions.
     */
    public function activate(QuestionnaireVersion $version): QuestionnaireVersion
    {
        if ($version->questions()->doesntExist()) {
            throw new QuestionnaireVersionLockedException(
                'A questionnaire version cannot be activated unless it contains at least one question.'
            );
        }

        return $this->database->transaction(function () use ($version): QuestionnaireVersion {
            QuestionnaireVersion::query()
                ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
                ->where('id', '!=', $version->id)
                ->update(['status' => QuestionnaireVersion::STATUS_ARCHIVED]);

            $version->update(['status' => QuestionnaireVersion::STATUS_ACTIVE]);

            return $version->refresh();
        });
    }

    /**
     * Archive an Active version, retiring it while keeping it available
     * for historical assessments.
     */
    public function archive(QuestionnaireVersion $version): QuestionnaireVersion
    {
        return $this->database->transaction(function () use ($version): QuestionnaireVersion {
            $version->update(['status' => QuestionnaireVersion::STATUS_ARCHIVED]);

            return $version->refresh();
        });
    }

    /**
     * @throws QuestionnaireVersionLockedException if the version is not Draft.
     */
    private function assertEditable(QuestionnaireVersion $version): void
    {
        if (! $version->isEditable()) {
            throw new QuestionnaireVersionLockedException(
                'This questionnaire version can no longer be modified because it is not in Draft status.'
            );
        }
    }
}
