<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClassificationThreshold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsFormRequest extends FormRequest
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
     * `active_questionnaire_version_id` is intentionally not accepted
     * here: it is a read-only display on the Settings page, changed only
     * via Questionnaire Management's Activate action (Module 4).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'system_name' => ['required', 'string', 'max:255'],
            'school_name' => ['required', 'string', 'max:255'],
            'notification_severity_threshold' => [
                'required',
                Rule::in([
                    ClassificationThreshold::SEVERITY_MODERATE,
                    ClassificationThreshold::SEVERITY_SEVERE,
                    ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
                ]),
            ],
            'assessment_availability' => ['required', Rule::in(['Available', 'Unavailable'])],
            'data_retention_period' => ['required', 'string', 'max:255'],
        ];
    }
}
