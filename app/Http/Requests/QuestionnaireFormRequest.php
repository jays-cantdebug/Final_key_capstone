<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Questionnaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionnaireFormRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => [
                'required',
                Rule::in([
                    Questionnaire::STATUS_ACTIVE,
                    Questionnaire::STATUS_INACTIVE,
                    Questionnaire::STATUS_ARCHIVED,
                ]),
            ],
        ];
    }
}
