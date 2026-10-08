{{--
    Live page, for a draft where the student fills in Step 1 on the device:
    the details as the student sent them (or as corrected here), the
    duplicate-student panel when the name matches a student (shown only
    here, never on the device), and the correction form. The answers below
    stay read-only; this form only ever sends the Step 1 fields.
--}}
@php
    $identityErrors = $errors->getBag('identity');
@endphp

<div class="space-y-4" data-remote-identity>
    @if ($identity === null)
        <div class="rounded-lg border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" data-identity-waiting>
            <p class="font-semibold text-body dark:text-slate-100">Student details</p>
            <p class="mt-1">Waiting for the student’s details. The student types them on the device after acknowledging the privacy notice; they appear here as soon as they are sent.</p>
        </div>
    @else
        @if ($duplicate)
            <div data-identity-duplicate="{{ $duplicate['kind'] }}">
                @if ($draft->isHeld())
                    <p class="mb-2 text-sm font-semibold text-red-700 dark:text-red-400" data-held-reason>
                        The student device was stopped because the name the student entered matches an existing active student. The student sees only the generic “not available” message. Use Take Again for that student (the retake needs a new code), or correct the name below if it was mistyped.
                    </p>
                @endif
                @include('assessments.create._duplicate-student', ['duplicate' => $duplicate, 'controls' => false])
            </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-semibold text-body dark:text-slate-100">Student details</p>
                <p class="text-xs text-slate-500 dark:text-slate-400" data-identity-source>
                    Entered by the student at {{ $draft->identity_submitted_at->format('g:i A') }}@if ($draft->identity_corrected_at) · corrected by you at {{ $draft->identity_corrected_at->format('g:i A') }}@endif
                </p>
            </div>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Check these with the student in person and correct any typo before submitting. The answers can’t be changed here.</p>

            <form method="POST" action="{{ route('assessments.create.remote.identity') }}" class="mt-4" novalidate data-identity-form>
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['first_name' => 'First Name', 'middle_name' => 'Middle Name', 'last_name' => 'Last Name'] as $field => $label)
                        <div class="relative" x-data="{ show: {{ $identityErrors->has($field) ? 'true' : 'false' }} }">
                            <x-input-label :for="'identity_'.$field" :value="$label" />
                            <x-text-input :id="'identity_'.$field" :name="$field" type="text" class="mt-1 block w-full" :value="$identityValue($field)" :invalid="$identityErrors->has($field)" autocomplete="off" @input="show = false" />
                            <x-field-error-tooltip :message="$identityErrors->first($field)" />
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="relative" x-data="{ show: {{ $identityErrors->has('gender') ? 'true' : 'false' }} }">
                        <x-input-label for="identity_gender" :value="__('Gender')" />
                        <x-select id="identity_gender" name="gender" class="mt-1 block w-full" :invalid="$identityErrors->has('gender')" @change="show = false">
                            <option value="">Select gender</option>
                            @foreach (\App\Http\Requests\AssessmentStudentRequest::GENDERS as $genderOption)
                                <option value="{{ $genderOption }}" @selected($identityValue('gender') === $genderOption)>{{ $genderOption }}</option>
                            @endforeach
                        </x-select>
                        <x-field-error-tooltip :message="$identityErrors->first('gender')" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $identityErrors->has('course_id') ? 'true' : 'false' }} }">
                        <x-input-label for="identity_course_id" :value="__('Course')" />
                        <x-select id="identity_course_id" name="course_id" class="mt-1 block w-full" :invalid="$identityErrors->has('course_id')" @change="show = false">
                            <option value="">Select a course</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected($identityValue('course_id') === (string) $course->id)>{{ $course->course_code }} - {{ $course->course_name }}</option>
                            @endforeach
                        </x-select>
                        <x-field-error-tooltip :message="$identityErrors->first('course_id')" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $identityErrors->has('year_level_id') ? 'true' : 'false' }} }">
                        <x-input-label for="identity_year_level_id" :value="__('Year Level')" />
                        <x-select id="identity_year_level_id" name="year_level_id" class="mt-1 block w-full" :invalid="$identityErrors->has('year_level_id')" @change="show = false">
                            <option value="">Select a year level</option>
                            @foreach ($yearLevels as $yearLevel)
                                <option value="{{ $yearLevel->id }}" @selected($identityValue('year_level_id') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                            @endforeach
                        </x-select>
                        <x-field-error-tooltip :message="$identityErrors->first('year_level_id')" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $identityErrors->has('section_id') ? 'true' : 'false' }} }">
                        <x-input-label for="identity_section_id" :value="__('Section')" />
                        <x-select id="identity_section_id" name="section_id" class="mt-1 block w-full" :invalid="$identityErrors->has('section_id')" @change="show = false">
                            <option value="">Select a section</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" @selected($identityValue('section_id') === (string) $section->id)>{{ $section->section_name }}</option>
                            @endforeach
                        </x-select>
                        <x-field-error-tooltip :message="$identityErrors->first('section_id')" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-secondary-button type="submit">{{ __('Save corrections') }}</x-secondary-button>
                </div>
            </form>
        </div>
    @endif
</div>
