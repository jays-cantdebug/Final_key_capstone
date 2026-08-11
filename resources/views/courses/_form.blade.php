@php
    /** @var \App\Models\Course|null $course */
    $course = $course ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="course_code" :value="__('Course Code')" />
        <x-text-input id="course_code" name="course_code" type="text" class="mt-1 block w-full" :value="old('course_code', $course?->course_code)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('course_code')" />
    </div>

    <div>
        <x-input-label for="course_name" :value="__('Course Name')" />
        <x-text-input id="course_name" name="course_name" type="text" class="mt-1 block w-full" :value="old('course_name', $course?->course_name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('course_name')" />
    </div>

    <div>
        <x-input-label for="status" :value="__('Status')" />
        <x-select id="status" name="status" class="mt-1 block w-full">
            @foreach (['Active', 'Inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $course?->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only Active courses appear in the New Assessment / Student Information dropdowns.</p>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Course') }}
    </x-primary-button>

    <x-secondary-button :href="route('settings.records')">
        {{ __('Cancel') }}
    </x-secondary-button>
</div>
