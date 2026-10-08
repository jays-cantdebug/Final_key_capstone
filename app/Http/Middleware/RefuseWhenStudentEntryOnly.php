<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RemoteAssessmentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY on, refuses — before any
 * validation, staging or write — the two ways a Psychometrician could still
 * enter a student's part on their own PC:
 * - `step1`: POST of the manual Step 1 form (student details);
 * - `step2`: POST of the same-device Step 2 answers.
 * The form isn't even shown then; this makes a crafted request fail too.
 * Off: passes everything through, unchanged.
 */
class RefuseWhenStudentEntryOnly
{
    public const STEP_ONE_MESSAGE = 'Student details are entered by the student on the student device. Use New Assessment to start.';

    public const STEP_TWO_MESSAGE = 'The student answers on the student device. Use Send to student device.';

    public function handle(Request $request, Closure $next, string $step): Response
    {
        if (! RemoteAssessmentService::studentEntryOnly()) {
            return $next($request);
        }

        return $step === 'step2'
            ? redirect()->route('assessments.create.questionnaire', status: 303)->withErrors(['questionnaire' => self::STEP_TWO_MESSAGE])
            : redirect()->route('assessments.create', status: 303)->withErrors(['student' => self::STEP_ONE_MESSAGE]);
    }
}
