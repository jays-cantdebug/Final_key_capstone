<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentFormRequest extends FormRequest
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
     * Student Number is system-generated (see StudentNumberGeneratorService)
     * and is never accepted as input here, on create or update.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ];
    }
}
