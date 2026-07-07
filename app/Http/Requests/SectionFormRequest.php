<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionFormRequest extends FormRequest
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
        $section = $this->route('section');

        return [
            'section_name' => [
                'required',
                'string',
                'max:100',
                // Only active (non-archived) sections count toward the
                // uniqueness check — an archived section's name must be
                // reusable, otherwise it's stuck forever.
                Rule::unique('sections', 'section_name')->whereNull('deleted_at')->ignore($section?->id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in([Section::STATUS_ACTIVE, Section::STATUS_INACTIVE])],
        ];
    }
}
