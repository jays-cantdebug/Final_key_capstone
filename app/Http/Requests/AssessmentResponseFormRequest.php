<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\QuestionnaireVersion;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AssessmentResponseFormRequest extends FormRequest
{
    public const QUESTIONNAIRE_CHANGED_MESSAGE = 'The active questionnaire changed while you were answering. Please answer the questions below.';

    private ?QuestionnaireVersion $activeVersion = null;

    private bool $activeVersionLoaded = false;

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
     * The form carries the id of the version it was built from
     * (`questionnaire_version_id`). If another version has been activated
     * since, the answers belong to the old version's questions, so instead
     * of one "required" error per new question the request fails with a
     * single QUESTIONNAIRE_CHANGED_MESSAGE. A form without the field (a
     * page rendered before it existed) is validated against the active
     * version as before.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $version = $this->questionnaireVersion();

        if ($this->questionnaireChanged()) {
            return [
                'questionnaire_version_id' => [
                    fn (string $attribute, mixed $value, Closure $fail) => $fail(self::QUESTIONNAIRE_CHANGED_MESSAGE),
                ],
            ];
        }

        $rules = self::rulesFor($version);

        if ($this->session()->has('assessment_wizard.existing_student_id')) {
            $rules['privacy_consent'] = ['required', 'accepted'];
        }

        return $rules;
    }

    /**
     * The answer rules for a version's questions, shared by this request
     * (answers given on this device) and RemoteAssessmentController::submit()
     * (answers given on a student device), so both are validated the same.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(?QuestionnaireVersion $version): array
    {
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

        return $rules;
    }

    /**
     * The version this request validates against: the one Active when the
     * request arrived. The wizard pins exactly this version at Step 2, so
     * the answers saved in session always match the version pinned.
     */
    public function questionnaireVersion(): ?QuestionnaireVersion
    {
        if (! $this->activeVersionLoaded) {
            $this->activeVersion = QuestionnaireVersion::query()
                ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
                ->with('questions')
                ->first();
            $this->activeVersionLoaded = true;
        }

        return $this->activeVersion;
    }

    private function questionnaireChanged(): bool
    {
        $submitted = $this->input('questionnaire_version_id');

        return filled($submitted) && (int) $submitted !== $this->questionnaireVersion()?->id;
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
