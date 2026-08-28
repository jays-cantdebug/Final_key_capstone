<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClassificationThresholdFormRequest;
use App\Services\ClassificationThresholdService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages the guarded Override Mode for DASS-21 classification thresholds.
 * Psychometrician-only (see routes/web.php); no dedicated Policy since
 * there is no per-record authorization nuance beyond role.
 */
class ClassificationThresholdController extends Controller
{
    public function __construct(private readonly ClassificationThresholdService $thresholdService) {}

    public function index(): View
    {
        return view('settings.classification-thresholds', [
            'thresholds' => $this->thresholdService->all(),
            'isOverridden' => $this->thresholdService->isOverridden(),
        ]);
    }

    public function update(ClassificationThresholdFormRequest $request): RedirectResponse
    {
        $this->thresholdService->update($request->validated('thresholds'));

        return redirect()->route('settings.classification-thresholds')
            ->with('status', 'Classification thresholds updated successfully.');
    }

    public function restore(): RedirectResponse
    {
        $this->thresholdService->restoreOfficial();

        return redirect()->route('settings.classification-thresholds')
            ->with('status', 'Classification thresholds restored to official values.');
    }
}
