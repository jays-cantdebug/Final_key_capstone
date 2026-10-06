<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClassificationThreshold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PredictionFeedbackFormRequest extends FormRequest
{
    public const CORRECTION_FIELDS = [
        'corrected_depression_level',
        'corrected_anxiety_level',
        'corrected_stress_level',
    ];

    /**
     * Shown under the `corrections` error key. Confirm and Correct are two
     * buttons on one form, so a Confirm can arrive with correction
     * dropdowns still set — refused rather than silently ignoring them
     * (Confirm accepts the AI's levels) or silently applying them.
     */
    public const CORRECTIONS_WITH_CONFIRM_MESSAGE = 'You selected corrections. Use Correct & Save to apply them, or set every subscale back to Unchanged to confirm.';

    /**
     * Shown under the `corrections` error key when Correct is submitted
     * without any subscale set to a level different from the AI's — here
     * when every dropdown is Unchanged, and in AssessmentWizardController
     * when every chosen level equals the AI's own.
     */
    public const NO_CORRECTION_MESSAGE = 'Correct & Save needs at least one subscale set to a level different from the AI\'s. Choose a new level, or use Confirm & Save to accept the AI\'s classification.';

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

    /**
     * Confirm must come with every subscale Unchanged; Correct must come
     * with at least one subscale changed. Skipped when a field rule already
     * failed, so only one message describes the problem.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasCorrections = $this->correctedLevels() !== [];

                if ($this->boolean('is_confirmed') && $hasCorrections) {
                    $validator->errors()->add('corrections', self::CORRECTIONS_WITH_CONFIRM_MESSAGE);
                } elseif (! $this->boolean('is_confirmed') && ! $hasCorrections) {
                    $validator->errors()->add('corrections', self::NO_CORRECTION_MESSAGE);
                }
            },
        ];
    }

    /**
     * The correction dropdowns that were set (anything but Unchanged),
     * keyed by field name.
     *
     * @return array<string, string>
     */
    public function correctedLevels(): array
    {
        return array_filter(
            $this->only(self::CORRECTION_FIELDS),
            fn (mixed $level): bool => filled($level),
        );
    }
}
