<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\QuestionnaireVersionLockedException;
use App\Http\Requests\DassQuestionFormRequest;
use App\Models\DassQuestion;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Services\DassQuestionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages DASS Question records belonging to a Questionnaire Version.
 * Creation, editing, and deletion are only permitted while the parent
 * version is in Draft status.
 */
class DassQuestionController extends Controller
{
    public function __construct(private readonly DassQuestionService $questionService) {}

    public function create(Questionnaire $questionnaire, QuestionnaireVersion $version): View
    {
        return view('questionnaires.versions.questions.create', [
            'questionnaire' => $questionnaire,
            'version' => $version,
        ]);
    }

    public function store(
        DassQuestionFormRequest $request,
        Questionnaire $questionnaire,
        QuestionnaireVersion $version
    ): RedirectResponse {
        try {
            $this->questionService->create($version, $request->validated());
        } catch (QuestionnaireVersionLockedException $exception) {
            return back()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Question added successfully.');
    }

    public function edit(Questionnaire $questionnaire, QuestionnaireVersion $version, DassQuestion $question): View
    {
        return view('questionnaires.versions.questions.edit', [
            'questionnaire' => $questionnaire,
            'version' => $version,
            'question' => $question,
        ]);
    }

    public function update(
        DassQuestionFormRequest $request,
        Questionnaire $questionnaire,
        QuestionnaireVersion $version,
        DassQuestion $question
    ): RedirectResponse {
        try {
            $this->questionService->update($question, $request->validated());
        } catch (QuestionnaireVersionLockedException $exception) {
            return back()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Question updated successfully.');
    }

    public function destroy(
        Questionnaire $questionnaire,
        QuestionnaireVersion $version,
        DassQuestion $question
    ): RedirectResponse {
        try {
            $this->questionService->delete($question);
        } catch (QuestionnaireVersionLockedException $exception) {
            return back()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Question deleted successfully.');
    }
}
