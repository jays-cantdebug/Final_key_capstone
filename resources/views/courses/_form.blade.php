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
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (['Active', 'Inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $course?->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Only Active courses appear in the New Assessment / Student Information dropdowns.</p>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Course') }}
    </x-primary-button>

    <a href="{{ route('settings.records') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
        {{ __('Cancel') }}
    </a>
</div>
