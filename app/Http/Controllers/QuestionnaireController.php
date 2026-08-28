<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\LookupRecordInUseException;
use App\Http\Requests\QuestionnaireFormRequest;
use App\Models\Questionnaire;
use App\Services\QuestionnaireService;
use App\Services\QuestionnaireVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages Questionnaire template records (the assessment container that
 * Questionnaire Versions are released under).
 */
class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly QuestionnaireService $questionnaireService,
        private readonly QuestionnaireVersionService $versionService,
    ) {}

    public function index(): View
    {
        return view('questionnaires.index', [
            'questionnaires' => $this->questionnaireService->paginate(),
        ]);
    }

    public function create(): View
    {
        return view('questionnaires.create');
    }

    public function store(QuestionnaireFormRequest $request): RedirectResponse
    {
        $questionnaire = $this->questionnaireService->create($request->validated());

        return redirect()->route('questionnaires.show', $questionnaire)
            ->with('status', 'Questionnaire created successfully.');
    }

    public function show(Questionnaire $questionnaire): View
    {
        return view('questionnaires.show', [
            'questionnaire' => $questionnaire,
            'versions' => $this->versionService->listForQuestionnaire($questionnaire),
        ]);
    }

    public function edit(Questionnaire $questionnaire): View
    {
        return view('questionnaires.edit', [
            'questionnaire' => $questionnaire,
        ]);
    }

    public function update(QuestionnaireFormRequest $request, Questionnaire $questionnaire): RedirectResponse
    {
        $this->questionnaireService->update($questionnaire, $request->validated());

        return redirect()->route('questionnaires.show', $questionnaire)
            ->with('status', 'Questionnaire updated successfully.');
    }

    public function destroy(Questionnaire $questionnaire): RedirectResponse
    {
        try {
            $this->questionnaireService->delete($questionnaire);
        } catch (LookupRecordInUseException $exception) {
            return back()->withErrors(['questionnaire' => $exception->getMessage()]);
        }

        return redirect()->route('questionnaires.index')->with('status', 'Questionnaire archived successfully.');
    }
}
