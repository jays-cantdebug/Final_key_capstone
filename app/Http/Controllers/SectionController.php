<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\LookupRecordInUseException;
use App\Http\Requests\SectionFormRequest;
use App\Models\Section;
use App\Services\SectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages Section lookup records. Psychometrician-only (see
 * routes/web.php); no dedicated Policy since there is no per-record
 * authorization nuance beyond role.
 */
class SectionController extends Controller
{
    public function __construct(private readonly SectionService $sectionService)
    {
    }

    public function create(): View
    {
        return view('sections.create');
    }

    public function store(SectionFormRequest $request): RedirectResponse
    {
        $this->sectionService->create($request->validated());

        return redirect()->route('settings.records')->with('status', 'Section created successfully.');
    }

    public function edit(Section $section): View
    {
        return view('sections.edit', ['section' => $section]);
    }

    public function update(SectionFormRequest $request, Section $section): RedirectResponse
    {
        $this->sectionService->update($section, $request->validated());

        return redirect()->route('settings.records')->with('status', 'Section updated successfully.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        try {
            $this->sectionService->delete($section);
        } catch (LookupRecordInUseException $exception) {
            return back()->withErrors(['section' => $exception->getMessage()]);
        }

        return redirect()->route('settings.records')->with('status', 'Section archived successfully.');
    }
}
