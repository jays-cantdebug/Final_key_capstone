<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DassQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DassQuestionFormRequest extends FormRequest
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
        $version = $this->route('version');
        $question = $this->route('question');

        return [
            'item_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('dass_questions', 'item_number')
                    ->where('questionnaire_version_id', $version?->id)
                    ->ignore($question?->id),
            ],
            'question_text' => ['required', 'string'],
            'question_type' => ['required', Rule::in([DassQuestion::TYPE_LIKERT_SCALE])],
            'subscale' => [
                'required',
                Rule::in([
                    DassQuestion::SUBSCALE_DEPRESSION,
                    DassQuestion::SUBSCALE_ANXIETY,
                    DassQuestion::SUBSCALE_STRESS,
                ]),
            ],
            'display_order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('dass_questions', 'display_order')
                    ->where('questionnaire_version_id', $version?->id)
                    ->ignore($question?->id),
            ],
            'is_required' => ['boolean'],
        ];
    }
}
