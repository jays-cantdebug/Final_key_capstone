<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuestionnaireVersionLockedException;
use App\Models\DassQuestion;
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
    /**
     * The official DASS-21 layout: 7 items per subscale, 21 in total.
     */
    public const QUESTIONS_PER_SUBSCALE = 7;

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
     * Applies equally to a Draft and to a previously Archived version
     * being reactivated.
     *
     * @throws QuestionnaireVersionLockedException if the version is not a valid DASS-21 layout.
     */
    public function activate(QuestionnaireVersion $version): QuestionnaireVersion
    {
        $this->assertValidDass21Layout($version);

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
     * DASS-21 scoring (raw sum x 2, see DassScoringService) and the official
     * classification_thresholds (top band ending at 42) are only valid for
     * exactly 7 questions per subscale, every one of them answered. Fewer
     * questions silently cap the score below Severe (so a student can never
     * be flagged); more can exceed 42 and match no threshold band; an
     * optional question left blank fails scoring. Only activation is
     * guarded — a Draft may hold any layout while it is being built.
     *
     * @throws QuestionnaireVersionLockedException naming each wrong subscale and its count.
     */
    private function assertValidDass21Layout(QuestionnaireVersion $version): void
    {
        $questions = $version->questions()->get(['subscale', 'item_number', 'is_required']);
        $problems = [];

        foreach ([DassQuestion::SUBSCALE_DEPRESSION, DassQuestion::SUBSCALE_ANXIETY, DassQuestion::SUBSCALE_STRESS] as $subscale) {
            $count = $questions->where('subscale', $subscale)->count();

            if ($count !== self::QUESTIONS_PER_SUBSCALE) {
                $problems[] = sprintf(
                    '%s has %d %s; it needs exactly %d.',
                    $subscale,
                    $count,
                    $count === 1 ? 'question' : 'questions',
                    self::QUESTIONS_PER_SUBSCALE
                );
            }
        }

        $optionalItems = $questions->where('is_required', false)->pluck('item_number')->sort()->values();

        if ($optionalItems->isNotEmpty()) {
            $problems[] = $optionalItems->count() === 1
                ? "Every question must be required; item {$optionalItems->first()} is optional."
                : "Every question must be required; items {$optionalItems->implode(', ')} are optional.";
        }

        if ($problems !== []) {
            throw new QuestionnaireVersionLockedException(
                'This version cannot be activated. '.implode(' ', $problems)
            );
        }
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
