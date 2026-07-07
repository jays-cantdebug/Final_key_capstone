<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseFormRequest extends FormRequest
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
        $course = $this->route('course');

        return [
            'course_code' => [
                'required',
                'string',
                'max:20',
                // Only active (non-archived) courses count toward the
                // uniqueness check — an archived course's code must be
                // reusable, otherwise it's stuck forever.
                Rule::unique('courses', 'course_code')->whereNull('deleted_at')->ignore($course?->id),
            ],
            'course_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in([Course::STATUS_ACTIVE, Course::STATUS_INACTIVE])],
        ];
    }
}
