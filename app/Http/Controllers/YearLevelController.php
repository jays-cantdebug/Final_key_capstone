<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\LookupRecordInUseException;
use App\Http\Requests\YearLevelFormRequest;
use App\Models\YearLevel;
use App\Services\YearLevelService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages Year Level lookup records. Psychometrician-only (see
 * routes/web.php); no dedicated Policy since there is no per-record
 * authorization nuance beyond role.
 */
class YearLevelController extends Controller
{
    public function __construct(private readonly YearLevelService $yearLevelService) {}

    public function create(): View
    {
        return view('year-levels.create', [
            'suggestedDisplayOrder' => $this->yearLevelService->nextDisplayOrder(),
        ]);
    }

    public function store(YearLevelFormRequest $request): RedirectResponse
    {
        $this->yearLevelService->create($request->validated());

        return redirect()->route('settings.records')->with('status', 'Year level created successfully.');
    }

    public function edit(YearLevel $yearLevel): View
    {
        return view('year-levels.edit', ['yearLevel' => $yearLevel]);
    }

    public function update(YearLevelFormRequest $request, YearLevel $yearLevel): RedirectResponse
    {
        $this->yearLevelService->update($yearLevel, $request->validated());

        return redirect()->route('settings.records')->with('status', 'Year level updated successfully.');
    }

    public function destroy(YearLevel $yearLevel): RedirectResponse
    {
        try {
            $this->yearLevelService->delete($yearLevel);
        } catch (LookupRecordInUseException $exception) {
            return back()->withErrors(['year_level' => $exception->getMessage()]);
        }

        return redirect()->route('settings.records')->with('status', 'Year level archived successfully.');
    }
}
