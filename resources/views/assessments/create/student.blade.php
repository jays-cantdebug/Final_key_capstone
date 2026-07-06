<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">New Assessment</p>
            <h2 class="text-2xl font-semibold text-slate-900">Step 1: Student Information</h2>
        </div>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 1])

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-600">Search for an existing student by student number, or register a new student below.</p>

        <form method="GET" action="{{ route('assessments.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="student_number" :value="__('Student Number')" />
                <x-text-input id="student_number" name="student_number" type="text" class="mt-1 block w-full" value="{{ request('student_number') }}" autofocus />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
        </form>
    </div>

    @if ($searched)
        <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($foundStudent)
                <h3 class="text-lg font-semibold text-slate-900">Student Found</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Name</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $foundStudent->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Student Number</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $foundStudent->student_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Course</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $foundStudent->course?->course_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Year Level / Section</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $foundStudent->yearLevel?->label }} &mdash; {{ $foundStudent->section?->section_name }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('assessments.create.student') }}" class="mt-6">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $foundStudent->id }}" />

                    @if ($foundStudent->privacy_consent_at === null)
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="privacy_consent" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" required />
                            <span class="text-sm text-slate-700">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                        </label>
                        <x-input-error class="mt-2" :messages="$errors->get('privacy_consent')" />
                    @else
                        <p class="text-sm text-emerald-700">Privacy consent already on file (acknowledged {{ $foundStudent->privacy_consent_at->format('M d, Y') }}).</p>
                    @endif

                    <div class="mt-4">
                        <x-primary-button>{{ __('Use This Student') }}</x-primary-button>
                    </div>
                </form>
            @else
                <p class="text-sm text-slate-600">No student found with that student number. Register a new student below to continue.</p>

                <form method="POST" action="{{ route('assessments.create.student') }}" class="mt-6">
                    @csrf

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="first_name" :value="__('First Name')" />
                            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                        </div>

                        <div>
                            <x-input-label for="middle_name" :value="__('Middle Name')" />
                            <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full" :value="old('middle_name')" />
                            <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
                        </div>

                        <div>
                            <x-input-label for="last_name" :value="__('Last Name')" />
                            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                        </div>

                        <div>
                            <x-input-label for="student_number_register" :value="__('Student Number')" />
                            <x-text-input id="student_number_register" name="student_number" type="text" class="mt-1 block w-full" :value="old('student_number', request('student_number'))" required />
                            <x-input-error class="mt-2" :messages="$errors->get('student_number')" />
                        </div>

                        <div>
                            <x-input-label for="gender" :value="__('Gender')" />
                            <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (['Male', 'Female', 'Prefer not to say'] as $genderOption)
                                    <option value="{{ $genderOption }}" @selected(old('gender') === $genderOption)>{{ $genderOption }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('gender')" />
                        </div>

                        <div>
                            <x-input-label for="course_id" :value="__('Course')" />
                            <select id="course_id" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select a course</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->course_code }} - {{ $course->course_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('course_id')" />
                        </div>

                        <div>
                            <x-input-label for="year_level_id" :value="__('Year Level')" />
                            <select id="year_level_id" name="year_level_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select a year level</option>
                                @foreach ($yearLevels as $yearLevel)
                                    <option value="{{ $yearLevel->id }}" @selected((string) old('year_level_id') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('year_level_id')" />
                        </div>

                        <div>
                            <x-input-label for="section_id" :value="__('Section')" />
                            <select id="section_id" name="section_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select a section</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}" @selected((string) old('section_id') === (string) $section->id)>{{ $section->section_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('section_id')" />
                        </div>
                    </div>

                    <label class="mt-6 flex items-start gap-2">
                        <input type="checkbox" name="privacy_consent" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" required />
                        <span class="text-sm text-slate-700">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                    </label>
                    <x-input-error class="mt-2" :messages="$errors->get('privacy_consent')" />

                    <div class="mt-6">
                        <x-primary-button>{{ __('Register & Continue') }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    @endif
</x-app-layout>
