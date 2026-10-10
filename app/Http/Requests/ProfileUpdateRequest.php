<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /** Largest avatar width and height accepted, in pixels. */
    public const AVATAR_MAX_PIXELS = 4096;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // dimensions: read from the file header, so an oversized image is
            // refused before AvatarService decodes it (a huge-pixel PNG could
            // otherwise exhaust memory). 4096 still fits a 12 MP phone photo.
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192', 'dimensions:max_width='.self::AVATAR_MAX_PIXELS.',max_height='.self::AVATAR_MAX_PIXELS],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.dimensions' => 'The photo is too large: it can be at most '.self::AVATAR_MAX_PIXELS.' × '.self::AVATAR_MAX_PIXELS.' pixels. Please choose a smaller photo.',
        ];
    }
}
