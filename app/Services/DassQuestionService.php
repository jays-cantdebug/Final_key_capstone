<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuestionnaireVersionLockedException;
use App\Models\DassQuestion;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles persistence for DASS Questions belonging to a Questionnaire Version.
 *
 * Questions may only be added, edited, or removed while their parent
 * version is in Draft status; editing an active or archived version is
 * prohibited by design.
 */
class DassQuestionService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * List all questions for a version, ordered for questionnaire display.
     */
    public function listForVersion(QuestionnaireVersion $version): Collection
    {
        return $version->questions()->orderBy('display_order')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws QuestionnaireVersionLockedException if the parent version is not Draft.
     */
    public function create(QuestionnaireVersion $version, array $data): DassQuestion
    {
        $this->assertVersionEditable($version);

        return $this->database->transaction(fn (): DassQuestion => $version->questions()->create($data));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws QuestionnaireVersionLockedException if the parent version is not Draft.
     */
    public function update(DassQuestion $question, array $data): DassQuestion
    {
        $this->assertVersionEditable($question->questionnaireVersion);

        return $this->database->transaction(function () use ($question, $data): DassQuestion {
            $question->update($data);

            return $question->refresh();
        });
    }

    /**
     * @throws QuestionnaireVersionLockedException if the parent version is not Draft.
     */
    public function delete(DassQuestion $question): void
    {
        $this->assertVersionEditable($question->questionnaireVersion);

        $this->database->transaction(static function () use ($question): void {
            $question->delete();
        });
    }

    /**
     * @throws QuestionnaireVersionLockedException if the version is not Draft.
     */
    private function assertVersionEditable(QuestionnaireVersion $version): void
    {
        if (! $version->isEditable()) {
            throw new QuestionnaireVersionLockedException(
                'Questions cannot be modified because the parent questionnaire version is not in Draft status.'
            );
        }
    }
}
