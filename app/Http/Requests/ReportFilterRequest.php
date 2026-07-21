<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared filter validation for all report routes. Not every report uses
 * every field; each report's controller/service reads only the fields
 * relevant to it.
 *
 * `search` (student name) is the user-facing filter carried through from
 * the Flagged Cases and Counseling Sessions listings. `student_number` is
 * only used by the Student Assessment History report, reached via an
 * exact deep link from a specific student's profile rather than typed
 * manually — it's never a search field a user fills in directly.
 */
class ReportFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'student_number' => ['nullable', 'string', 'max:50'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'year_level_id' => ['nullable', 'integer', 'exists:year_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'overall_status' => [
                'nullable',
                Rule::in(['Normal', 'Mild', 'Moderate', 'Severe', 'Extremely Severe']),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
