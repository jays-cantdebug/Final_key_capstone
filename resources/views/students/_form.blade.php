@php
    /** @var \App\Models\Student|null $student */
    $student = $student ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    @if ($student)
        <div>
            <x-input-label for="student_number" :value="__('Student Number')" />
            <x-text-input id="student_number" type="text" class="mt-1 block w-full bg-slate-50" :value="$student->student_number" disabled />
            <p class="mt-1 text-xs text-slate-500">System-generated — cannot be edited.</p>
        </div>
    @endif

    <div>
        <x-input-label for="first_name" :value="__('First Name')" />
        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $student?->first_name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
    </div>

    <div>
        <x-input-label for="middle_name" :value="__('Middle Name')" />
        <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full" :value="old('middle_name', $student?->middle_name)" />
        <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
    </div>

    <div>
        <x-input-label for="last_name" :value="__('Last Name')" />
        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $student?->last_name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
    </div>

    <div>
        <x-input-label for="gender" :value="__('Gender')" />
        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (['Male', 'Female', 'Prefer not to say'] as $genderOption)
                <option value="{{ $genderOption }}" @selected(old('gender', $student?->gender) === $genderOption)>{{ $genderOption }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('gender')" />
    </div>

    <div>
        <x-input-label for="course_id" :value="__('Course')" />
        <select id="course_id" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Select a course</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}" @selected((string) old('course_id', $student?->course_id) === (string) $course->id)>
                    {{ $course->course_code }} - {{ $course->course_name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('course_id')" />
    </div>

    <div>
        <x-input-label for="year_level_id" :value="__('Year Level')" />
        <select id="year_level_id" name="year_level_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Select a year level</option>
            @foreach ($yearLevels as $yearLevel)
                <option value="{{ $yearLevel->id }}" @selected((string) old('year_level_id', $student?->year_level_id) === (string) $yearLevel->id)>
                    {{ $yearLevel->label }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('year_level_id')" />
    </div>

    <div>
        <x-input-label for="section_id" :value="__('Section')" />
        <select id="section_id" name="section_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Select a section</option>
            @foreach ($sections as $section)
                <option value="{{ $section->id }}" @selected((string) old('section_id', $student?->section_id) === (string) $section->id)>
                    {{ $section->section_name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('section_id')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Student') }}
    </x-primary-button>

    <a href="{{ route('students.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
        {{ __('Cancel') }}
    </a>
</div>