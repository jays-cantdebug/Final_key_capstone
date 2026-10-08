{{--
    The student's details fields — First, Middle and Last Name, Gender,
    Course, Year Level, Section — shared by every form that asks for them,
    so they can never drift apart: Step 1 (assessments/create/student), the
    student device (student-device/_details) and the live page's correction
    form (assessments/create/_remote-identity). The <form>, its button and
    anything else around the fields stay with each caller.

    Parameters:
    - $courses, $yearLevels, $sections: the Active lookup rows (the same
      AssessmentService queries everywhere; never a student record).
    - $fieldValue: fn (string $field): ?string — the value to show back
      (Step 1: old(); the device: only the input it just received).
    - $fieldErrors: has()/first() per field (a MessageBag or $errors).
    - $idPrefix: prefix for the control ids ('' on Step 1).
    - $autofocusField: the field that gets autofocus, or null.
    - $autocompleteOff: adds autocomplete="off" to every control (the
      student device, a shared PC).

    Each error bubble has an id, and its field points at it with
    aria-describedby, so screen readers read the message with the field.
--}}
@php
    $describedBy = fn (string $field): ?string => $fieldErrors->has($field) ? $idPrefix.$field.'-error' : null;
    $autocomplete = $autocompleteOff ? 'off' : null;
@endphp
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2 grid gap-6 sm:grid-cols-3">
                    <div class="relative" x-data="{ show: {{ $fieldErrors->has('first_name') ? 'true' : 'false' }} }">
                        <x-input-label :for="$idPrefix.'first_name'" :value="__('First Name')" />
                        <x-text-input :id="$idPrefix.'first_name'" name="first_name" type="text" class="mt-1 block w-full" :value="$fieldValue('first_name')" :invalid="$fieldErrors->has('first_name')" :autofocus="$autofocusField === 'first_name'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('first_name')" @input="show = false" />
                        <x-field-error-tooltip :message="$fieldErrors->first('first_name')" :id="$idPrefix.'first_name-error'" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $fieldErrors->has('middle_name') ? 'true' : 'false' }} }">
                        <x-input-label :for="$idPrefix.'middle_name'" :value="__('Middle Name')" />
                        <x-text-input :id="$idPrefix.'middle_name'" name="middle_name" type="text" class="mt-1 block w-full" :value="$fieldValue('middle_name')" :invalid="$fieldErrors->has('middle_name')" :autofocus="$autofocusField === 'middle_name'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('middle_name')" @input="show = false" />
                        <x-field-error-tooltip :message="$fieldErrors->first('middle_name')" :id="$idPrefix.'middle_name-error'" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $fieldErrors->has('last_name') ? 'true' : 'false' }} }">
                        <x-input-label :for="$idPrefix.'last_name'" :value="__('Last Name')" />
                        <x-text-input :id="$idPrefix.'last_name'" name="last_name" type="text" class="mt-1 block w-full" :value="$fieldValue('last_name')" :invalid="$fieldErrors->has('last_name')" :autofocus="$autofocusField === 'last_name'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('last_name')" @input="show = false" />
                        <x-field-error-tooltip :message="$fieldErrors->first('last_name')" :id="$idPrefix.'last_name-error'" />
                    </div>
                </div>

                <div class="relative" x-data="{ show: {{ $fieldErrors->has('gender') ? 'true' : 'false' }} }">
                    <x-input-label :for="$idPrefix.'gender'" :value="__('Gender')" />
                    <x-select :id="$idPrefix.'gender'" name="gender" class="mt-1 block w-full" :invalid="$fieldErrors->has('gender')" :autofocus="$autofocusField === 'gender'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('gender')" @change="show = false">
                        <option value="">Select gender</option>
                        @foreach (\App\Http\Requests\AssessmentStudentRequest::GENDERS as $genderOption)
                            <option value="{{ $genderOption }}" @selected($fieldValue('gender') === $genderOption)>{{ $genderOption }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$fieldErrors->first('gender')" :id="$idPrefix.'gender-error'" />
                </div>

                <div class="relative" x-data="{ show: {{ $fieldErrors->has('course_id') ? 'true' : 'false' }} }">
                    <x-input-label :for="$idPrefix.'course_id'" :value="__('Course')" />
                    <x-select :id="$idPrefix.'course_id'" name="course_id" class="mt-1 block w-full" :invalid="$fieldErrors->has('course_id')" :autofocus="$autofocusField === 'course_id'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('course_id')" @change="show = false">
                        <option value="">Select a course</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) $fieldValue('course_id') === (string) $course->id)>{{ $course->course_code }} - {{ $course->course_name }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$fieldErrors->first('course_id')" :id="$idPrefix.'course_id-error'" />
                </div>

                <div class="relative" x-data="{ show: {{ $fieldErrors->has('year_level_id') ? 'true' : 'false' }} }">
                    <x-input-label :for="$idPrefix.'year_level_id'" :value="__('Year Level')" />
                    <x-select :id="$idPrefix.'year_level_id'" name="year_level_id" class="mt-1 block w-full" :invalid="$fieldErrors->has('year_level_id')" :autofocus="$autofocusField === 'year_level_id'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('year_level_id')" @change="show = false">
                        <option value="">Select a year level</option>
                        @foreach ($yearLevels as $yearLevel)
                            <option value="{{ $yearLevel->id }}" @selected((string) $fieldValue('year_level_id') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$fieldErrors->first('year_level_id')" :id="$idPrefix.'year_level_id-error'" />
                </div>

                <div class="relative" x-data="{ show: {{ $fieldErrors->has('section_id') ? 'true' : 'false' }} }">
                    <x-input-label :for="$idPrefix.'section_id'" :value="__('Section')" />
                    <x-select :id="$idPrefix.'section_id'" name="section_id" class="mt-1 block w-full" :invalid="$fieldErrors->has('section_id')" :autofocus="$autofocusField === 'section_id'" :autocomplete="$autocomplete" :aria-describedby="$describedBy('section_id')" @change="show = false">
                        <option value="">Select a section</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((string) $fieldValue('section_id') === (string) $section->id)>{{ $section->section_name }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$fieldErrors->first('section_id')" :id="$idPrefix.'section_id-error'" />
                </div>
            </div>
