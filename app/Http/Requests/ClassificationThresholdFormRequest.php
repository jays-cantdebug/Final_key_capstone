<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassificationThresholdFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bulk-validates all 15 threshold rows submitted from the Override
     * Mode form.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'thresholds' => ['required', 'array'],
            'thresholds.*.id' => ['required', 'integer', 'exists:classification_thresholds,id'],
            'thresholds.*.min_score' => ['required', 'integer', 'min:0'],
            'thresholds.*.max_score' => ['required', 'integer', 'gte:thresholds.*.min_score'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'thresholds.*.max_score.gte' => 'The max score must be greater than or equal to the min score for each threshold.',
        ];
    }
}
