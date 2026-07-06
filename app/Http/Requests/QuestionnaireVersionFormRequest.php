<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionnaireVersionFormRequest extends FormRequest
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
     * Status is intentionally not accepted here: new versions always start
     * as Draft, and later transitions happen only through the dedicated
     * activate/archive actions, never through a raw form edit.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $questionnaire = $this->route('questionnaire');
        $version = $this->route('version');

        return [
            'version_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('questionnaire_versions', 'version_number')
                    ->where('questionnaire_id', $questionnaire?->id)
                    ->ignore($version?->id),
            ],
            'effective_date' => ['required', 'date'],
        ];
    }
}
