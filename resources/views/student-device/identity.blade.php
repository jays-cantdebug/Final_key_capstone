@extends('layouts.student-device')

{{--
    The student's own Step 1 details, after the privacy notice. The lists
    are the Active lookup rows only (courses, year levels, sections), never
    anything from a student record. $old and $fieldErrors are only the input
    this device just sent, shown back when it failed validation. Autocomplete
    is off, so a shared PC doesn't offer the previous student's name.
--}}
@section('content')
    @php
        $inputClass = 'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary';
        $invalidClass = 'border-red-500';
        $selected = fn (string $field, mixed $value): bool => ($old[$field] ?? '') === (string) $value;
    @endphp

    <form
        method="POST"
        action="{{ route('student-device.identity') }}"
        autocomplete="off"
        novalidate
        data-student-device="identity"
        class="mx-auto mt-10 max-w-xl rounded-lg border border-slate-200 bg-white px-6 py-8 shadow-sm"
    >
        <h1 class="text-xl font-semibold text-body">{{ __('student_device.identity_heading') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('student_device.identity_help') }}</p>

        <div class="mt-6 grid gap-5 sm:grid-cols-3">
            @foreach (['first_name' => 'identity_first_name', 'middle_name' => 'identity_middle_name', 'last_name' => 'identity_last_name'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ __('student_device.'.$label) }}</label>
                    <input
                        id="{{ $field }}"
                        name="{{ $field }}"
                        type="text"
                        value="{{ $old[$field] ?? '' }}"
                        autocomplete="off"
                        autocapitalize="words"
                        spellcheck="false"
                        maxlength="{{ $field === 'middle_name' ? 4 : 100 }}"
                        required
                        @if ($fieldErrors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                        class="{{ $inputClass }} {{ $fieldErrors->has($field) ? $invalidClass : '' }}"
                    >
                    @if ($field === 'middle_name')
                        <p class="mt-1 text-xs text-slate-500">{{ __('student_device.identity_middle_name_help') }}</p>
                    @endif
                    @if ($fieldErrors->has($field))
                        <p id="{{ $field }}-error" class="mt-1 text-xs font-medium text-red-600">{{ $fieldErrors->first($field) }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="gender" class="block text-sm font-medium text-slate-700">{{ __('student_device.identity_gender') }}</label>
                <select id="gender" name="gender" autocomplete="off" required @if ($fieldErrors->has('gender')) aria-invalid="true" aria-describedby="gender-error" @endif class="{{ $inputClass }} {{ $fieldErrors->has('gender') ? $invalidClass : '' }}">
                    <option value="">{{ __('student_device.identity_gender_placeholder') }}</option>
                    @foreach ($genders as $gender)
                        <option value="{{ $gender }}" @selected($selected('gender', $gender))>{{ $gender }}</option>
                    @endforeach
                </select>
                @if ($fieldErrors->has('gender'))
                    <p id="gender-error" class="mt-1 text-xs font-medium text-red-600">{{ $fieldErrors->first('gender') }}</p>
                @endif
            </div>

            <div>
                <label for="course_id" class="block text-sm font-medium text-slate-700">{{ __('student_device.identity_course') }}</label>
                <select id="course_id" name="course_id" autocomplete="off" required @if ($fieldErrors->has('course_id')) aria-invalid="true" aria-describedby="course_id-error" @endif class="{{ $inputClass }} {{ $fieldErrors->has('course_id') ? $invalidClass : '' }}">
                    <option value="">{{ __('student_device.identity_course_placeholder') }}</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected($selected('course_id', $course->id))>{{ $course->course_code }} - {{ $course->course_name }}</option>
                    @endforeach
                </select>
                @if ($fieldErrors->has('course_id'))
                    <p id="course_id-error" class="mt-1 text-xs font-medium text-red-600">{{ $fieldErrors->first('course_id') }}</p>
                @endif
            </div>

            <div>
                <label for="year_level_id" class="block text-sm font-medium text-slate-700">{{ __('student_device.identity_year_level') }}</label>
                <select id="year_level_id" name="year_level_id" autocomplete="off" required @if ($fieldErrors->has('year_level_id')) aria-invalid="true" aria-describedby="year_level_id-error" @endif class="{{ $inputClass }} {{ $fieldErrors->has('year_level_id') ? $invalidClass : '' }}">
                    <option value="">{{ __('student_device.identity_year_level_placeholder') }}</option>
                    @foreach ($yearLevels as $yearLevel)
                        <option value="{{ $yearLevel->id }}" @selected($selected('year_level_id', $yearLevel->id))>{{ $yearLevel->label }}</option>
                    @endforeach
                </select>
                @if ($fieldErrors->has('year_level_id'))
                    <p id="year_level_id-error" class="mt-1 text-xs font-medium text-red-600">{{ $fieldErrors->first('year_level_id') }}</p>
                @endif
            </div>

            <div>
                <label for="section_id" class="block text-sm font-medium text-slate-700">{{ __('student_device.identity_section') }}</label>
                <select id="section_id" name="section_id" autocomplete="off" required @if ($fieldErrors->has('section_id')) aria-invalid="true" aria-describedby="section_id-error" @endif class="{{ $inputClass }} {{ $fieldErrors->has('section_id') ? $invalidClass : '' }}">
                    <option value="">{{ __('student_device.identity_section_placeholder') }}</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected($selected('section_id', $section->id))>{{ $section->section_name }}</option>
                    @endforeach
                </select>
                @if ($fieldErrors->has('section_id'))
                    <p id="section_id-error" class="mt-1 text-xs font-medium text-red-600">{{ $fieldErrors->first('section_id') }}</p>
                @endif
            </div>
        </div>

        <button type="submit" class="mt-8 w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            {{ __('student_device.identity_submit') }}
        </button>
    </form>
@endsection
