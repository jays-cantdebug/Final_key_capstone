<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared filter validation for all report routes. Not every report uses
 * every field; each report's controller/service reads only the fields
 * relevant to it.
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
            'student_number' => ['nullable', 'string', 'max:50'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'year_level_id' => ['nullable', 'integer', 'exists:year_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
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
