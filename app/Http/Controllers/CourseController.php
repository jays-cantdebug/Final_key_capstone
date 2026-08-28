<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\LookupRecordInUseException;
use App\Http\Requests\CourseFormRequest;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Manages Course lookup records. Psychometrician-only (see routes/web.php);
 * no dedicated Policy since there is no per-record authorization nuance
 * beyond role, matching the Questionnaire/Settings precedent.
 */
class CourseController extends Controller
{
    public function __construct(private readonly CourseService $courseService) {}

    public function create(): View
    {
        return view('courses.create');
    }

    public function store(CourseFormRequest $request): RedirectResponse
    {
        $this->courseService->create($request->validated());

        return redirect()->route('settings.records')->with('status', 'Course created successfully.');
    }

    public function edit(Course $course): View
    {
        return view('courses.edit', ['course' => $course]);
    }

    public function update(CourseFormRequest $request, Course $course): RedirectResponse
    {
        $this->courseService->update($course, $request->validated());

        return redirect()->route('settings.records')->with('status', 'Course updated successfully.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        try {
            $this->courseService->delete($course);
        } catch (LookupRecordInUseException $exception) {
            return back()->withErrors(['course' => $exception->getMessage()]);
        }

        return redirect()->route('settings.records')->with('status', 'Course archived successfully.');
    }
}
