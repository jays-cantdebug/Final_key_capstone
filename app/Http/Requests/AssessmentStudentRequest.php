<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Section;
use App\Models\YearLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * New Assessment Step 1 (Student Information) intake. A submission
 * registers a new student at final save — unless a student with the same
 * name already exists, which AssessmentWizardController checks after this
 * request validates (see StudentDuplicateService).
 */
class AssessmentStudentRequest extends FormRequest
{
    /**
     * The Step 1 fields: the same on this form, on the student device
     * (StudentDeviceController::identity()) and in the Psychometrician's
     * correction of the details typed there (RemoteAssessmentController).
     */
    public const IDENTITY_FIELDS = ['first_name', 'middle_name', 'last_name', 'gender', 'course_id', 'year_level_id', 'section_id'];

    public const GENDERS = ['Male', 'Female', 'Prefer not to say'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Collapse stray whitespace in the three name parts ("Dela  Cruz " ->
     * "Dela Cruz"), so the staged — and eventually saved — name is
     * consistent with how duplicates are matched. Then normalize the
     * middle initial to uppercase before it's validated, so "p." is
     * accepted and staged as "P." rather than rejected outright — the
     * format is what matters, not the case the Psychometrician typed.
     *
     * The middle initial is also normalized to Unicode NFC first: some
     * keyboards type Ñ as "N" plus a combining tilde (two characters),
     * which NFC turns into the single letter Ñ the format rule and the
     * duplicate check expect (see composeToNfc()).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(self::normalizedNameParts($this->all()));
    }

    /**
     * The normalized name parts of `$input` (see prepareForValidation()),
     * to merge over it: squished names, and the middle initial in NFC and
     * uppercase when filled. Non-string values are left for validation to
     * reject.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalizedNameParts(array $input): array
    {
        $normalized = [];

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $normalized[$field] = Str::squish($input[$field]);
            }
        }

        if (($normalized['middle_name'] ?? '') !== '') {
            $normalized['middle_name'] = Str::upper(self::composeToNfc($normalized['middle_name']));
        }

        return $normalized;
    }

    /**
     * Unicode NFC normalization via the intl extension's `Normalizer`. When
     * intl isn't installed the value is returned unchanged rather than
     * crashing — a decomposed Ñ then fails the format rule with the usual
     * message, while every precomposed letter (including Ñ) still works. A
     * string intl can't normalize (invalid UTF-8) is likewise returned
     * unchanged for validation to reject.
     *
     * `$intlAvailable` is only passed by tests, to exercise the fallback;
     * null means "detect it".
     */
    public static function composeToNfc(string $value, ?bool $intlAvailable = null): string
    {
        if (! ($intlAvailable ?? class_exists(\Normalizer::class))) {
            return $value;
        }

        $composed = \Normalizer::normalize($value, \Normalizer::FORM_C);

        return $composed === false ? $value : $composed;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...self::identityRules(),
            'privacy_consent' => ['required', 'accepted'],
            // Only sent from the archived-match warning: whether it was
            // on screen, and whether its confirm box was ticked.
            'archived_warning_shown' => ['nullable', 'boolean'],
            'confirm_archived_match' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom messages for the required fields so the app shows consistent,
     * app-styled copy that names the specific missing field, instead of
     * Laravel's default "The x field is required." phrasing.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...self::identityMessages(),
            'privacy_consent.required' => 'Please check the privacy consent box to continue.',
            'privacy_consent.accepted' => 'Please check the privacy consent box to continue.',
        ];
    }

    /**
     * Step 1's rules for the student's details (IDENTITY_FIELDS), applied
     * after normalizedNameParts().
     *
     * @return array<string, array<int, mixed>>
     */
    public static function identityRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            // A middle initial only (e.g. "P.", or "Ñ." — a letter of the
            // Filipino alphabet), not a full middle name — normalized to
            // uppercase in prepareForValidation() above (Str::upper is
            // multibyte-safe, so "ñ." becomes "Ñ."). `u` makes the pattern
            // match Ñ as one character rather than two bytes.
            'middle_name' => ['required', 'string', 'regex:/^[A-ZÑ]\.$/u'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(self::GENDERS)],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ];
    }

    /**
     * The student device's rules: Step 1's, except that the course, year
     * level and section must be Active, unarchived ones (the only ones its
     * lists show), so an inactive or archived id fails exactly like one
     * that doesn't exist.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function studentDeviceIdentityRules(): array
    {
        return [
            ...self::identityRules(),
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where('status', Course::STATUS_ACTIVE)->withoutTrashed()],
            'year_level_id' => ['required', 'integer', Rule::exists('year_levels', 'id')->where('status', YearLevel::STATUS_ACTIVE)->withoutTrashed()],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('status', Section::STATUS_ACTIVE)->withoutTrashed()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function identityMessages(): array
    {
        return [
            'first_name.required' => 'Please fill out the First Name field.',
            'middle_name.required' => 'Please fill out the Middle Name field.',
            'middle_name.regex' => "Middle Name must be a single letter followed by a period, e.g., 'P.'",
            'last_name.required' => 'Please fill out the Last Name field.',
            'gender.required' => 'Please select a Gender.',
            'course_id.required' => 'Please select a Course.',
            'year_level_id.required' => 'Please select a Year Level.',
            'section_id.required' => 'Please select a Section.',
        ];
    }

    /**
     * The student device's messages: Step 1's, plus one plain message per
     * field for every other failure, so no reply names a rule, a table or
     * an id.
     *
     * @return array<string, string>
     */
    public static function studentDeviceIdentityMessages(): array
    {
        return [
            ...self::identityMessages(),
            'first_name.*' => 'Please check the First Name field.',
            'middle_name.*' => "Middle Name must be a single letter followed by a period, e.g., 'P.'",
            'last_name.*' => 'Please check the Last Name field.',
            'gender.*' => 'Please select a Gender.',
            'course_id.*' => 'Please select a Course.',
            'year_level_id.*' => 'Please select a Year Level.',
            'section_id.*' => 'Please select a Section.',
        ];
    }
}
