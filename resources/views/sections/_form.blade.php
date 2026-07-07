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
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (['Active', 'Inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $section?->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Only Active sections appear in the New Assessment / Student Information dropdowns.</p>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Section') }}
    </x-primary-button>

    <a href="{{ route('settings.records') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
        {{ __('Cancel') }}
    </a>
</div>
