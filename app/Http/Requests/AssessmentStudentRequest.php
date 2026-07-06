<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * Get the validation rules that apply to the request.
     *
     * When `student_id` is present, an existing student is being
     * confirmed for this assessment. Otherwise, a new student is being
     * registered inline.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if ($this->filled('student_id')) {
            return [
                'student_id' => ['required', 'integer', 'exists:students,id'],
                'privacy_consent' => ['required', 'accepted'],
            ];
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'privacy_consent' => ['required', 'accepted'],
        ];
    }
}
