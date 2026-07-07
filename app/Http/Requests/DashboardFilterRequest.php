<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        return [
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'all'])],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'year_level_id' => ['nullable', 'integer', 'exists:year_levels,id'],
            'severity_subscale' => ['nullable', Rule::in(['stress', 'anxiety', 'depression'])],
        ];
    }
}
