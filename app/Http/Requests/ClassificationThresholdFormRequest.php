<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ClassificationThresholdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Every score from 0 to 42 must land in exactly one band per subscale
     * (see ClassificationThresholdService::coverageProblems()), so a save
     * can never leave a score that Step 3 of the New Assessment can't
     * classify. Skipped when a field rule already failed.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(ClassificationThresholdService $thresholdService): array
    {
        return [
            function (Validator $validator) use ($thresholdService): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($thresholdService->coverageProblems($this->input('thresholds')) as $problem) {
                    $validator->errors()->add('thresholds', $problem);
                }
            },
        ];
    }
}
