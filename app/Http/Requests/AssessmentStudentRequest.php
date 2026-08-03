<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * New Assessment Step 1 (Student Information) intake. Every submission
 * always registers a brand-new student — there is no search for or
 * reuse of an existing student record here.
 */
class AssessmentStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the middle initial to uppercase before it's validated, so
     * "p." is accepted and staged as "P." rather than rejected outright —
     * the format is what matters, not the case the Psychometrician typed.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('middle_name')) {
            $this->merge(['middle_name' => Str::upper((string) $this->input('middle_name'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            // A middle initial only (e.g. "P."), not a full middle name —
            // normalized to uppercase in prepareForValidation() above.
            'middle_name' => ['required', 'string', 'regex:/^[A-Z]\.$/'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'privacy_consent' => ['required', 'accepted'],
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
            'first_name.required' => 'Please fill out the First Name field.',
            'middle_name.required' => 'Please fill out the Middle Name field.',
            'middle_name.regex' => "Middle Name must be a single letter followed by a period, e.g., 'P.'",
            'last_name.required' => 'Please fill out the Last Name field.',
            'gender.required' => 'Please select a Gender.',
            'course_id.required' => 'Please select a Course.',
            'year_level_id.required' => 'Please select a Year Level.',
            'section_id.required' => 'Please select a Section.',
            'privacy_consent.required' => 'Please check the privacy consent box to continue.',
            'privacy_consent.accepted' => 'Please check the privacy consent box to continue.',
        ];
    }
}
