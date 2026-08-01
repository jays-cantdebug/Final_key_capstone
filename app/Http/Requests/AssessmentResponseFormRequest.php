<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\QuestionnaireVersion;
use Illuminate\Foundation\Http\FormRequest;

class AssessmentResponseFormRequest extends FormRequest
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
     * Rules are built dynamically from the currently active
     * questionnaire version's questions, so future versions with a
     * different question count or required/optional mix are supported
     * without any code change.
     *
     * `privacy_consent` is only required in "Take Again" retake mode
     * (flagged by `assessment_wizard.existing_student_id` in session) —
     * Step 1, where the regular flow captures consent, is skipped for a
     * retake, so this step doubles as the consent screen instead. The
     * regular flow never has that session key, so it never sees this rule.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $version = QuestionnaireVersion::query()
            ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
            ->with('questions')
            ->first();

        $rules = [
            'responses' => ['required', 'array'],
        ];

        foreach ($version?->questions ?? [] as $question) {
            $rules["responses.{$question->id}"] = [
                $question->is_required ? 'required' : 'nullable',
                'integer',
                'between:0,3',
            ];
        }

        if ($this->session()->has('assessment_wizard.existing_student_id')) {
            $rules['privacy_consent'] = ['required', 'accepted'];
        }

        return $rules;
    }

    /**
     * Custom messages so an unanswered question shows consistent,
     * app-styled copy instead of Laravel's default "The responses.14
     * field is required." phrasing (which leaks the raw question id).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responses.required' => 'Please answer at least one question before submitting.',
            'responses.*.required' => 'Please select an answer for this question.',
            'privacy_consent.required' => 'Please check the privacy consent box to continue.',
            'privacy_consent.accepted' => 'Please check the privacy consent box to continue.',
        ];
    }
}
