<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StudentFormRequest;
use App\Models\Student;
use App\Services\AssessmentHistoryService;
use App\Services\StudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $studentService,
        private readonly AssessmentHistoryService $historyService,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $search = $request->get('search');

        return view('students.index', [
            'students' => $this->studentService->paginate($search),
            'search' => $search,
        ]);
    }

    public function show(Student $student): View
    {
        Gate::authorize('view', $student);

        $student->load(['course', 'yearLevel', 'section']);

        return view('students.show', [
            'student' => $student,
            'assessments' => $this->historyService->paginate(null, $student->student_number),
        ]);
    }

    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        $student->load(['course', 'yearLevel', 'section']);

        return view('students.edit', array_merge([
            'student' => $student,
        ], $this->studentService->formData()));
    }

    public function update(StudentFormRequest $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $this->studentService->update($student, $request->validated());

        return redirect()->route('students.show', $student)->with('status', 'Student record updated successfully.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        $this->studentService->delete($student);

        return redirect()->route('students.index')->with('status', 'Student record archived successfully.');
    }
}