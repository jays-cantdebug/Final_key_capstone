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

        return $rules;
    }
}
