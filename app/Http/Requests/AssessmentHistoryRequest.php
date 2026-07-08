<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentHistoryRequest extends FormRequest
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
     * `search` is the user-facing name search on this page. `student_number`
     * is not exposed as a search field (it's system-generated and never
     * typed by the Psychometrician) — it's only used when this page is
     * reached via a deep link from a specific student's profile, so the
     * results stay precisely scoped to that one student.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'student_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
