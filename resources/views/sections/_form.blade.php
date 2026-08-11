@php
    /** @var \App\Models\Section|null $section */
    $section = $section ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="section_name" :value="__('Section Name')" />
        <x-text-input id="section_name" name="section_name" type="text" class="mt-1 block w-full" :value="old('section_name', $section?->section_name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('section_name')" />
    </div>

    <div>
        <x-input-label for="capacity" :value="__('Capacity (optional)')" />
        <x-text-input id="capacity" name="capacity" type="number" min="1" class="mt-1 block w-full" :value="old('capacity', $section?->capacity)" />
        <x-input-error class="mt-2" :messages="$errors->get('capacity')" />
    </div>

    <div>
        <x-input-label for="status" :value="__('Status')" />
        <x-select id="status" name="status" class="mt-1 block w-full">
            @foreach (['Active', 'Inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $section?->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only Active sections appear in the New Assessment / Student Information dropdowns.</p>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Section') }}
    </x-primary-button>

    <x-secondary-button :href="route('settings.records')">
        {{ __('Cancel') }}
    </x-secondary-button>
</div>
