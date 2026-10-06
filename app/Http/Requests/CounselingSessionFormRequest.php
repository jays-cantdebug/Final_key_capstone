<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CounselingSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CounselingSessionFormRequest extends FormRequest
{
    /**
     * Shared with the form's client-side pre-submit check
     * (counseling-sessions/_form.blade.php) so both say the same thing.
     */
    public const FOLLOW_UP_DATE_REQUIRED_MESSAGE = 'Please choose a follow-up date, or untick Follow-up required.';

    public const FOLLOW_UP_DATE_BEFORE_SESSION_MESSAGE = 'The follow-up date cannot be earlier than the session date.';

    public const COMPLETED_IN_FUTURE_MESSAGE = 'A Completed session cannot be dated after today. Set the status to Scheduled, or correct the date.';

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
     *
     * When Follow-up required is unticked, `follow_up_date` is forced to
     * null: the form only hides the date input, which still submits its
     * last value, so without this an unticked follow-up would keep a stale
     * date on the session. Only an explicit "unticked" counts — a request
     * that leaves `follow_up_required` out entirely leaves the date alone,
     * since the stored flag isn't changed either.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('session_date') && $this->filled('session_time')) {
            $this->merge([
                'session_datetime' => $this->input('session_date').' '.$this->input('session_time'),
            ]);
        }

        if ($this->has('follow_up_required') && ! $this->boolean('follow_up_required')) {
            $this->merge(['follow_up_date' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `student_id` is only validated (and so only saved) on create, and
     * must be an active student there — archived students can't get new
     * sessions, though their existing ones stay editable. The student a
     * session belongs to cannot be changed afterward: on update any
     * submitted `student_id` is ignored, and `assessment_id`, when
     * provided, must belong to the session's own student — never to one
     * named in the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $session = $this->route('counseling_session');
        $studentId = $session !== null ? $session->student_id : $this->input('student_id');

        $rules = [
            'assessment_id' => [
                'nullable',
                'integer',
                Rule::exists('assessments', 'id')->where('student_id', $studentId),
            ],
            'session_date' => [
                'required',
                'date_format:Y-m-d',
                // A session can only be Completed once it has happened:
                // date-level, so a Completed session dated today is fine.
                // Scheduled (and the other statuses) may be in the future.
                Rule::when(
                    $this->input('session_status') === CounselingSession::STATUS_COMPLETED,
                    'before_or_equal:today'
                ),
            ],
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
            'follow_up_date' => [
                'nullable',
                'date',
                'required_if:follow_up_required,1',
                // Only when there is a session date to compare against, so a
                // blank session date doesn't also raise a misleading error here.
                Rule::when($this->filled('session_date'), 'after_or_equal:session_date'),
            ],
            'confidentiality_level' => [
                'required',
                Rule::in([
                    CounselingSession::CONFIDENTIALITY_STANDARD,
                    CounselingSession::CONFIDENTIALITY_RESTRICTED,
                ]),
            ],
        ];

        if ($session === null) {
            $rules['student_id'] = ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'follow_up_date.required_if' => self::FOLLOW_UP_DATE_REQUIRED_MESSAGE,
            'follow_up_date.after_or_equal' => self::FOLLOW_UP_DATE_BEFORE_SESSION_MESSAGE,
            'session_date.before_or_equal' => self::COMPLETED_IN_FUTURE_MESSAGE,
        ];
    }
}
