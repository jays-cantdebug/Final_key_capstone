<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\YearLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class YearLevelFormRequest extends FormRequest
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
        $yearLevel = $this->route('year_level');

        return [
            'label' => ['required', 'string', 'max:100'],
            'display_order' => [
                'required',
                'integer',
                'min:1',
                // Only active (non-archived) year levels count toward the
                // uniqueness check — an archived year level's display
                // order must be reusable, otherwise it's stuck forever.
                Rule::unique('year_levels', 'display_order')->whereNull('deleted_at')->ignore($yearLevel?->id),
            ],
            'status' => ['required', Rule::in([YearLevel::STATUS_ACTIVE, YearLevel::STATUS_INACTIVE])],
        ];
    }
}
