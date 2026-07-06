<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\QuestionnaireVersionLockedException;
use App\Http\Requests\QuestionnaireVersionFormRequest;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Services\DassQuestionService;
use App\Services\QuestionnaireVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages Questionnaire Version records: creation, editing while in Draft,
 * deletion of unused Draft versions, and Active/Archived lifecycle
 * transitions.
 */
class QuestionnaireVersionController extends Controller
{
    public function __construct(
        private readonly QuestionnaireVersionService $versionService,
        private readonly DassQuestionService $questionService,
    ) {
    }

    public function create(Questionnaire $questionnaire): View
    {
        return view('questionnaires.versions.create', [
            'questionnaire' => $questionnaire,
        ]);
    }

    public function store(QuestionnaireVersionFormRequest $request, Questionnaire $questionnaire): RedirectResponse
    {
        $version = $this->versionService->create($questionnaire, $request->validated());

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Questionnaire version created successfully.');
    }

    public function show(Questionnaire $questionnaire, QuestionnaireVersion $version): View
    {
        return view('questionnaires.versions.show', [
            'questionnaire' => $questionnaire,
            'version' => $version,
            'questions' => $this->questionService->listForVersion($version),
        ]);
    }

    public function edit(Questionnaire $questionnaire, QuestionnaireVersion $version): View
    {
        return view('questionnaires.versions.edit', [
            'questionnaire' => $questionnaire,
            'version' => $version,
        ]);
    }

    public function update(
        QuestionnaireVersionFormRequest $request,
        Questionnaire $questionnaire,
        QuestionnaireVersion $version
    ): RedirectResponse {
        try {
            $this->versionService->update($version, $request->validated());
        } catch (QuestionnaireVersionLockedException $exception) {
            return back()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Questionnaire version updated successfully.');
    }

    public function destroy(Questionnaire $questionnaire, QuestionnaireVersion $version): RedirectResponse
    {
        try {
            $this->versionService->delete($version);
        } catch (QuestionnaireVersionLockedException $exception) {
            return back()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.show', $questionnaire)
            ->with('status', 'Questionnaire version deleted successfully.');
    }

    public function activate(Questionnaire $questionnaire, QuestionnaireVersion $version): RedirectResponse
    {
        try {
            $this->versionService->activate($version);
        } catch (QuestionnaireVersionLockedException $exception) {
            return back()->withErrors(['version' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Questionnaire version activated successfully.');
    }

    public function archive(Questionnaire $questionnaire, QuestionnaireVersion $version): RedirectResponse
    {
        $this->versionService->archive($version);

        return redirect()->route('questionnaires.versions.show', [$questionnaire, $version])
            ->with('status', 'Questionnaire version archived successfully.');
    }
}
