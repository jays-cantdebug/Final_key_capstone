<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Step 1: Student Information</h2>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 1])

    @error('student')
        <x-toast type="error">{{ $message }}</x-toast>
    @enderror

    <x-card>
        <p class="text-sm text-slate-600 dark:text-slate-400">Every assessment begins with the student's information for this encounter.</p>

        <form
            method="POST"
            action="{{ route('assessments.create.student') }}"
            class="mt-6"
            novalidate
            x-init="$nextTick(() => { const first = $el.querySelector('[data-field-invalid]'); if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); } })"
        >
            @csrf

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2 grid gap-6 sm:grid-cols-3">
                    <div class="relative" x-data="{ show: {{ $errors->has('first_name') ? 'true' : 'false' }} }">
                        <x-input-label for="first_name" :value="__('First Name')" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" :invalid="$errors->has('first_name')" autofocus @input="show = false" />
                        <x-field-error-tooltip :message="$errors->first('first_name')" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $errors->has('middle_name') ? 'true' : 'false' }} }">
                        <x-input-label for="middle_name" :value="__('Middle Name')" />
                        <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full" :value="old('middle_name')" :invalid="$errors->has('middle_name')" @input="show = false" />
                        <x-field-error-tooltip :message="$errors->first('middle_name')" />
                    </div>

                    <div class="relative" x-data="{ show: {{ $errors->has('last_name') ? 'true' : 'false' }} }">
                        <x-input-label for="last_name" :value="__('Last Name')" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" :invalid="$errors->has('last_name')" @input="show = false" />
                        <x-field-error-tooltip :message="$errors->first('last_name')" />
                    </div>
                </div>

                <div class="relative" x-data="{ show: {{ $errors->has('gender') ? 'true' : 'false' }} }">
                    <x-input-label for="gender" :value="__('Gender')" />
                    <x-select id="gender" name="gender" class="mt-1 block w-full" :invalid="$errors->has('gender')" @change="show = false">
                        <option value="">Select gender</option>
                        @foreach (['Male', 'Female', 'Prefer not to say'] as $genderOption)
                            <option value="{{ $genderOption }}" @selected(old('gender') === $genderOption)>{{ $genderOption }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$errors->first('gender')" />
                </div>

                <div class="relative" x-data="{ show: {{ $errors->has('course_id') ? 'true' : 'false' }} }">
                    <x-input-label for="course_id" :value="__('Course')" />
                    <x-select id="course_id" name="course_id" class="mt-1 block w-full" :invalid="$errors->has('course_id')" @change="show = false">
                        <option value="">Select a course</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->course_code }} - {{ $course->course_name }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$errors->first('course_id')" />
                </div>

                <div class="relative" x-data="{ show: {{ $errors->has('year_level_id') ? 'true' : 'false' }} }">
                    <x-input-label for="year_level_id" :value="__('Year Level')" />
                    <x-select id="year_level_id" name="year_level_id" class="mt-1 block w-full" :invalid="$errors->has('year_level_id')" @change="show = false">
                        <option value="">Select a year level</option>
                        @foreach ($yearLevels as $yearLevel)
                            <option value="{{ $yearLevel->id }}" @selected((string) old('year_level_id') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$errors->first('year_level_id')" />
                </div>

                <div class="relative" x-data="{ show: {{ $errors->has('section_id') ? 'true' : 'false' }} }">
                    <x-input-label for="section_id" :value="__('Section')" />
                    <x-select id="section_id" name="section_id" class="mt-1 block w-full" :invalid="$errors->has('section_id')" @change="show = false">
                        <option value="">Select a section</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((string) old('section_id') === (string) $section->id)>{{ $section->section_name }}</option>
                        @endforeach
                    </x-select>
                    <x-field-error-tooltip :message="$errors->first('section_id')" />
                </div>
            </div>

            <div class="relative mt-6" x-data="{ show: {{ $errors->has('privacy_consent') ? 'true' : 'false' }} }">
                <label class="flex items-start gap-2">
                    <x-checkbox name="privacy_consent" value="1" class="mt-1" :invalid="$errors->has('privacy_consent')" @change="show = false" />
                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                </label>
                <x-field-error-tooltip :message="$errors->first('privacy_consent')" />
            </div>

            <div class="mt-6 flex justify-center">
                <x-primary-button>{{ __('Continue to Questionnaire') }}</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
