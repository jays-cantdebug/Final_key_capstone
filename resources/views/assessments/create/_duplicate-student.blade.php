{{--
    Duplicate-student panel for Step 1 (see AssessmentWizardController::confirmStudent()).
    $duplicate['kind']:
      - "active"   — Step 1 refused: an active student already has this name.
      - "conflict" — the final save refused: such a student was registered after Step 1.
      - "archived" — warning only: an archived student has this name; continuing
                     needs the confirm box ticked. Rendered inside the Step 1 form so
                     the checkbox and Continue button submit it.
    Stays on screen until the close button removes it — it never hides on its own.
    Removing it also removes the archived checkbox, so a dismissed warning is simply
    shown again on the next submit rather than raising a hidden checkbox error.
--}}
@php
    $kind = $duplicate['kind'];
    $students = $duplicate['students'];
    $isArchived = $kind === 'archived';
    $single = $students->count() === 1 ? $students->first() : null;

    $variant = $isArchived
        ? ['bg-[#FAEEDA] border-gold/30 text-[#633806] dark:bg-[#3D2F14] dark:border-gold-soft/30 dark:text-[#E0BE7C]', 'M8.485 3.495c.673-1.166 2.357-1.166 3.03 0l6.28 10.875c.673 1.167-.169 2.63-1.515 2.63H3.72c-1.346 0-2.188-1.463-1.515-2.63L8.485 3.495ZM10 7a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0V8a1 1 0 0 1 1-1Zm0 7a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z']
        : ['bg-[#FCEBEB] border-red-200 text-[#791F1F] dark:bg-[#3B1C1C] dark:border-[#5C2A2A] dark:text-[#E39A9A]', 'M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 11H9v2h2v-2Zm0-7H9v5h2V6Z'];

    $history = fn ($student): string => $student->assessments_count > 0
        ? $student->assessments_count.' '.\Illuminate\Support\Str::plural('assessment', $student->assessments_count).', the latest on '.$student->latest_assessment_at->format('M j, Y')
        : 'no assessments yet';

    $placement = fn ($student): string => implode(' · ', array_filter([
        $student->course?->course_code,
        $student->yearLevel?->label,
        $student->section ? 'Section '.$student->section->section_name : null,
    ]));

    $heading = match (true) {
        $isArchived => $single ? 'A student with this name was archived' : 'Archived students with this name were found',
        $kind === 'conflict' => 'This student was registered while you were working',
        default => $single ? 'This student already exists' : 'Students with this name already exist',
    };
@endphp

<div x-data class="mb-6 rounded-lg border px-4 py-4 text-sm {{ $variant[0] }}" role="alert">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="{{ $variant[1] }}" /></svg>

        <div class="min-w-0 flex-1 space-y-3">
            <p class="font-semibold">{{ $heading }}</p>

            @if ($isArchived)
                @if ($single)
                    <p>A student named {{ $duplicate['name'] }} ({{ $single->student_number }}) was archived on {{ $single->deleted_at->format('M j, Y') }}. Archived students can't use Take Again, so continuing will create a new, separate record. Their earlier assessments stay in Assessment History under {{ $single->student_number }}.</p>
                @else
                    <p>{{ $students->count() }} archived students named {{ $duplicate['name'] }} were found: {{ \Illuminate\Support\Arr::join($students->map(fn ($student) => $student->student_number.' (archived '.$student->deleted_at->format('M j, Y').')')->all(), ', ', ' and ') }}. Archived students can't use Take Again, so continuing will create a new, separate record. Their earlier assessments stay in Assessment History under those student numbers.</p>
                @endif

                <div class="flex flex-wrap gap-2">
                    @foreach ($students as $student)
                        <x-secondary-button :href="route('assessments.index', ['student_number' => $student->student_number])">
                            {{ $single ? 'View their assessments' : 'View assessments for '.$student->student_number }}
                        </x-secondary-button>
                    @endforeach
                </div>

                <input type="hidden" name="archived_warning_shown" value="1" />

                <div class="relative" x-data="{ show: {{ $errors->has('confirm_archived_match') ? 'true' : 'false' }} }">
                    <label class="flex items-start gap-2">
                        <x-checkbox name="confirm_archived_match" value="1" class="mt-1" :checked="(bool) old('confirm_archived_match')" :invalid="$errors->has('confirm_archived_match')" @change="show = false" />
                        <span>I understand. Create a new student record.</span>
                    </label>
                    <x-field-error-tooltip :message="$errors->first('confirm_archived_match')" />
                </div>

                <x-primary-button>Continue</x-primary-button>
            @elseif ($single)
                @if ($kind === 'conflict')
                    <p>{{ $single->full_name }} was registered as <span class="font-semibold">{{ $single->student_number }}</span> while this assessment was in progress, so nothing was saved. Please use Take Again for {{ $single->student_number }} and answer the questionnaire again.</p>
                @else
                    <p>{{ $single->full_name }} is already registered as <span class="font-semibold">{{ $single->student_number }}</span> ({{ $placement($single) }}), with {{ $history($single) }}. Please use Take Again so their assessment history stays together. A second record can't be created for the same name.</p>
                @endif

                <div class="flex flex-wrap gap-2">
                    <x-primary-button :href="route('assessments.create.retake', $single)">Take Again</x-primary-button>
                    <x-secondary-button :href="route('students.show', $single)">View student record</x-secondary-button>
                </div>
            @else
                @if ($kind === 'conflict')
                    <p>Students named {{ $duplicate['name'] }} are already registered, so nothing was saved. Choose the right student, use Take Again, and answer the questionnaire again.</p>
                @else
                    <p>{{ $students->count() }} students named {{ $duplicate['name'] }} are already registered. Choose the right student and use Take Again so their assessment history stays together. A new record can't be created for this name.</p>
                @endif

                <ul class="divide-y divide-red-200 rounded-md border border-red-200 dark:divide-[#5C2A2A] dark:border-[#5C2A2A]">
                    @foreach ($students as $student)
                        <li class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <span><span class="font-semibold">{{ $student->student_number }}</span> · {{ $placement($student) }} · {{ $history($student) }}</span>
                            <span class="flex flex-shrink-0 gap-2">
                                <x-primary-button :href="route('assessments.create.retake', $student)">Take Again</x-primary-button>
                                <x-secondary-button :href="route('students.show', $student)">View record</x-secondary-button>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('students.index', ['search' => $duplicate['search']]) }}" class="inline-block font-semibold underline underline-offset-2">Open these in Students</a>
            @endif
        </div>

        <button type="button" x-on:click="$root.remove()" aria-label="Dismiss" class="flex-shrink-0 opacity-60 hover:opacity-100">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
        </button>
    </div>
</div>
