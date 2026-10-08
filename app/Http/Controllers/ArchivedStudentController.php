<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DuplicateStudentException;
use App\Http\Requests\StudentRestoreRequest;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * The Students page's "Archived" tab: archived students, and restoring one
 * (Psychometrician only). Archiving never erased anything, so restoring
 * only clears `deleted_at`; the student keeps their number and history.
 */
class ArchivedStudentController extends Controller
{
    public function __construct(private readonly StudentService $studentService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $search = $request->get('search');
        $students = $this->studentService->paginateArchived($search);

        $data = [
            'students' => $students,
            'search' => $search,
            'activeMatches' => $this->studentService->activeNameMatches($students->items()),
        ];

        if ($request->header('X-Live-Search') === 'true') {
            return view('students.archived._table', $data);
        }

        return view('students.archived.index', $data);
    }

    public function restore(StudentRestoreRequest $request, Student $student): RedirectResponse
    {
        Gate::authorize('restore', $student);

        if (! $student->trashed()) {
            return redirect()->route('students.archived.index')
                ->with('status', "{$student->full_name} ({$student->student_number}) is already active.");
        }

        try {
            $this->studentService->restore($student, $request->validated('reason'));
        } catch (DuplicateStudentException $exception) {
            $numbers = Student::query()->whereIn('id', $exception->studentIds)->orderBy('student_number')->pluck('student_number')->all();

            return back()->withErrors([
                'restore' => "{$student->full_name} ({$student->student_number}) can't be restored: an active student with the same name already exists ("
                    .Arr::join($numbers, ', ', ' and ')
                    .'). Use that record instead — records are not merged.',
            ]);
        }

        return redirect()->route('students.archived.index')->with(
            'status',
            "{$student->full_name} ({$student->student_number}) has been restored, with the same student number. They return to their original place in the Students list, which is ordered by registration date."
        );
    }
}
