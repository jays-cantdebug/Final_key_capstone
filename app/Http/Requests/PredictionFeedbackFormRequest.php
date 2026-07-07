<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClassificationThreshold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PredictionFeedbackFormRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:psychometrician` route
     * middleware group this request is routed under.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $severityLevels = [
            ClassificationThreshold::SEVERITY_NORMAL,
            ClassificationThreshold::SEVERITY_MILD,
            ClassificationThreshold::SEVERITY_MODERATE,
            ClassificationThreshold::SEVERITY_SEVERE,
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
        ];

        return [
            'is_confirmed' => ['required', 'boolean'],
            'corrected_depression_level' => ['nullable', 'string', Rule::in($severityLevels)],
            'corrected_anxiety_level' => ['nullable', 'string', Rule::in($severityLevels)],
            'corrected_stress_level' => ['nullable', 'string', Rule::in($severityLevels)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
