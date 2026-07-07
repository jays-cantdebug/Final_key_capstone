<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;

/**
 * Generates system student numbers. Student Number is never typed by the
 * Psychometrician — it exists purely so assessments, counseling sessions,
 * and reporting can reference a stable student identifier internally.
 */
class StudentNumberGeneratorService
{
    /**
     * Generate a unique, year-prefixed sequential student number
     * (e.g. "2026-00001").
     */
    public function generate(): string
    {
        $year = now()->year;

        do {
            $sequence = Student::withTrashed()->whereYear('created_at', $year)->count() + 1;
            $candidate = sprintf('%d-%05d', $year, $sequence);
        } while (Student::withTrashed()->where('student_number', $candidate)->exists());

        return $candidate;
    }
}
