<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CounselingSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CounselingSessionFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Combine the form's separate Date and Time inputs into a single
     * `session_datetime` value before validation, per the approved UI's
     * split fields (a native datetime-local input's combined date+time
     * segments were confusing to interact with). Only combines when both
     * are present so each field's own `required` rule still fires with a
     * precise per-field message if one is left blank.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('session_date') && $this->filled('session_time')) {
            $this->merge([
                'session_datetime' => $this->input('session_date').' '.$this->input('session_time'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `student_id` is only required on create; the student a session
     * belongs to cannot be changed afterward. `assessment_id`, when
     * provided, must belong to that same student.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $session = $this->route('counseling_session');
        $studentId = $this->input('student_id') ?? $session?->student_id;

        $rules = [
            'assessment_id' => [
                'nullable',
                'integer',
                Rule::exists('assessments', 'id')->where('student_id', $studentId),
            ],
            'session_date' => ['required', 'date_format:Y-m-d'],
            'session_time' => ['required', 'date_format:H:i'],
            'session_datetime' => ['required', 'date'],
            'session_notes' => ['required', 'string'],
            'session_status' => [
                'required',
                Rule::in([
                    CounselingSession::STATUS_SCHEDULED,
                    CounselingSession::STATUS_COMPLETED,
                    CounselingSession::STATUS_CANCELLED,
                    CounselingSession::STATUS_NO_SHOW,
                ]),
            ],
            'follow_up_required' => ['boolean'],
            'follow_up_date' => ['nullable', 'date', 'required_if:follow_up_required,1'],
            'confidentiality_level' => [
                'required',
                Rule::in([
                    CounselingSession::CONFIDENTIALITY_STANDARD,
                    CounselingSession::CONFIDENTIALITY_RESTRICTED,
                ]),
            ],
        ];

        if ($session === null) {
            $rules['student_id'] = ['required', 'integer', 'exists:students,id'];
        }

        return $rules;
    }
}
