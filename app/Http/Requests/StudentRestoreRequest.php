<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional reason given when restoring an archived student, stored in
 * the "Restore" audit entry. Authorization is done in the controller via
 * StudentPolicy::restore(), like StudentFormRequest.
 */
class StudentRestoreRequest extends FormRequest
{
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
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'The reason may not be longer than 255 characters.',
        ];
    }
}
