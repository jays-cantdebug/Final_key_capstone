<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Detects whether a student being registered through the New Assessment
 * wizard already has a record, so a returning student is sent to Take
 * Again (keeping their assessment history together) instead of being
 * registered a second time.
 *
 * Two students are "the same" when their first name, middle initial and
 * last name match after trimming, collapsing internal whitespace, and
 * ignoring case. Only the first letter of the middle name is compared
 * ("D.", "D" and "Dela" all match "D."); a stored record with no middle
 * name never matches an entered initial. Course, year level, section and
 * gender are deliberately not compared — they change over time (a
 * returning student is usually in a later year level), and a mistyped
 * gender would otherwise hide a real duplicate.
 *
 * The final comparison happens in PHP, after a broad SQL pre-filter on
 * LOWER(REPLACE(name, ' ', '')), so internal whitespace can be collapsed —
 * which SQL can't do portably. MySQL and the test suite's SQLite behave
 * identically for ASCII names and for the Ñ middle initial (compared in
 * PHP only). They differ for an uppercase non-ASCII letter in a stored
 * first or last name (e.g. "PEÑA"): SQLite's LOWER() only lowercases
 * ASCII, so such a record misses the SQLite pre-filter where MySQL matches
 * it. Only MySQL gives the real behaviour for those names.
 */
class StudentDuplicateService
{
    /**
     * Matching students, archived (soft-deleted) ones included, split
     * into active and archived.
     *
     * @return array{active: Collection<int, Student>, archived: Collection<int, Student>}
     */
    public function findMatches(string $firstName, ?string $middleName, string $lastName): array
    {
        $first = self::normalize($firstName);
        $middleInitial = self::middleInitial($middleName);
        $last = self::normalize($lastName);

        $matches = Student::query()
            ->withTrashed()
            ->whereRaw("LOWER(REPLACE(first_name, ' ', '')) = ?", [str_replace(' ', '', $first)])
            ->whereRaw("LOWER(REPLACE(last_name, ' ', '')) = ?", [str_replace(' ', '', $last)])
            ->orderBy('student_number')
            ->get()
            ->filter(fn (Student $student): bool => self::normalize($student->first_name) === $first
                && self::normalize($student->last_name) === $last
                && self::middleInitial($student->middle_name) === $middleInitial)
            ->values();

        return [
            'active' => $matches->reject(fn (Student $student): bool => $student->trashed())->values(),
            'archived' => $matches->filter(fn (Student $student): bool => $student->trashed())->values(),
        ];
    }

    /**
     * Load the given students (archived included) with what the
     * duplicate-student panel shows for each: course, year level,
     * section, assessment count, and latest assessment date.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Student>
     */
    public function describe(array $ids): Collection
    {
        return Student::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->with(['course', 'yearLevel', 'section'])
            ->addSelect([
                'assessments_count' => Assessment::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assessments.student_id', 'students.id'),
                'latest_assessment_at' => Assessment::query()
                    ->selectRaw('MAX(assessments.submitted_at)')
                    ->whereColumn('assessments.student_id', 'students.id'),
            ])
            ->withCasts([
                'assessments_count' => 'integer',
                'latest_assessment_at' => 'datetime',
            ])
            ->orderBy('student_number')
            ->get();
    }

    /**
     * Trim, collapse internal whitespace, and lowercase a name part.
     */
    public static function normalize(?string $value): string
    {
        return mb_strtolower(Str::squish((string) $value));
    }

    /**
     * The normalized first letter of a middle name ("" when there is none).
     */
    public static function middleInitial(?string $value): string
    {
        return mb_substr(self::normalize($value), 0, 1);
    }
}
