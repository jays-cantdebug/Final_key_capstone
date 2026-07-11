<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">New Assessment</p>
            <h2 class="text-2xl font-semibold text-body">Step 1: Student Information</h2>
        </div>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 1])

    @include('assessments.create._response-scale')

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <p class="text-sm text-slate-600">Every assessment begins with the student's information for this encounter.</p>

        <form method="POST" action="{{ route('assessments.create.student') }}" class="mt-6">
            @csrf

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="full_name" :value="__('Full Name')" />
                    <x-text-input id="full_name" name="full_name" type="text" class="mt-1 block w-full" :value="old('full_name')" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('full_name')" />
                </div>

                <div>
                    <x-input-label for="gender" :value="__('Gender')" />
                    <x-select id="gender" name="gender" class="mt-1 block w-full">
                        <option value="">Select gender</option>
                        @foreach (['Male', 'Female', 'Prefer not to say'] as $genderOption)
                            <option value="{{ $genderOption }}" @selected(old('gender') === $genderOption)>{{ $genderOption }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error class="mt-2" :messages="$errors->get('gender')" />
                </div>

                <div>
                    <x-input-label for="course_id" :value="__('Course')" />
                    <x-select id="course_id" name="course_id" class="mt-1 block w-full">
                        <option value="">Select a course</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->course_code }} - {{ $course->course_name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error class="mt-2" :messages="$errors->get('course_id')" />
                </div>

                <div>
                    <x-input-label for="year_level_id" :value="__('Year Level')" />
                    <x-select id="year_level_id" name="year_level_id" class="mt-1 block w-full">
                        <option value="">Select a year level</option>
                        @foreach ($yearLevels as $yearLevel)
                            <option value="{{ $yearLevel->id }}" @selected((string) old('year_level_id') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error class="mt-2" :messages="$errors->get('year_level_id')" />
                </div>

                <div>
                    <x-input-label for="section_id" :value="__('Section')" />
                    <x-select id="section_id" name="section_id" class="mt-1 block w-full">
                        <option value="">Select a section</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((string) old('section_id') === (string) $section->id)>{{ $section->section_name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error class="mt-2" :messages="$errors->get('section_id')" />
                </div>
            </div>

            <label class="mt-6 flex items-start gap-2">
                <x-checkbox name="privacy_consent" value="1" class="mt-1" required />
                <span class="text-sm text-slate-700">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('privacy_consent')" />

            <div class="mt-6 flex justify-center">
                <x-primary-button>{{ __('Next: Questionnaire') }}</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
