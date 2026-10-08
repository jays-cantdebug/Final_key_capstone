<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuestionnaireVersionLockedException;
use App\Models\DassQuestion;
use App\Models\DassResponse;
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
     * Permanently remove a Draft question, freeing its item number and
     * display order so the Draft can be given a new question with them.
     *
     * A soft delete kept the row, and with it the version's
     * (item_number) and (display_order) unique slots, so a Draft that lost
     * item 4 could never get an item 4 again and could never be activated.
     * A hard delete is safe here: a version only moves Draft -> Active ->
     * Archived and never back to Draft, and responses are only ever saved
     * against the Active version, so a Draft question has no responses.
     * The check below (and dass_responses' restrictOnDelete foreign key
     * behind it) refuses the delete if one ever did. The audit log keeps
     * the deleted question's snapshot.
     *
     * @throws QuestionnaireVersionLockedException if the parent version is not Draft, or the question has responses.
     */
    public function delete(DassQuestion $question): void
    {
        $this->assertVersionEditable($question->questionnaireVersion);

        if (DassResponse::query()->where('dass_question_id', $question->id)->exists()) {
            throw new QuestionnaireVersionLockedException(
                'This question cannot be deleted because it has been answered in an assessment.'
            );
        }

        $this->database->transaction(static function () use ($question): void {
            $question->forceDelete();
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
