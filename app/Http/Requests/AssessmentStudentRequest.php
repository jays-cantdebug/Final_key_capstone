<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * Split the single "Full Name" field into first_name/middle_name/
     * last_name before validation, per the approved UI's single combined
     * name field.
     *
     * - 2 words: first word -> first_name, second word -> last_name, no
     *   middle_name.
     * - 3+ words: first word -> first_name, last word -> last_name,
     *   everything in between -> middle_name.
     * - Fewer than 2 words is left alone here and rejected by the
     *   `full_name` validation rule below — last_name is a required,
     *   non-nullable column, so a single word is never guessed/duplicated
     *   into it.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('full_name')) {
            return;
        }

        $words = preg_split('/\s+/', trim($this->string('full_name')->toString()), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 2) {
            return;
        }

        $firstName = array_shift($words);
        $lastName = array_pop($words);
        $middleName = $words !== [] ? implode(' ', $words) : null;

        $this->merge([
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:191'],
            // first_name/last_name are only present when prepareForValidation()
            // successfully split full_name into 2+ words; "sometimes" avoids a
            // redundant "required" error stacking on top of the full_name
            // check below when it wasn't split (e.g. a single-word entry).
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'privacy_consent' => ['required', 'accepted'],
        ];
    }

    /**
     * Reject a full name that doesn't contain at least a first and last
     * word, rather than letting the derived `first_name`/`last_name`
     * rules fail with a confusing "required" message.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $words = preg_split('/\s+/', trim((string) $this->input('full_name')), -1, PREG_SPLIT_NO_EMPTY);

            if ($this->filled('full_name') && count($words) < 2) {
                $validator->errors()->add('full_name', 'Please enter both first and last name.');
            }
        });
    }
}
