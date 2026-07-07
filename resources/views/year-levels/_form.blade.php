@php
    /** @var \App\Models\YearLevel|null $yearLevel */
    $yearLevel = $yearLevel ?? null;
    $suggestedDisplayOrder = $suggestedDisplayOrder ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="label" :value="__('Year Level Label')" />
        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label', $yearLevel?->label)" placeholder="e.g. 1st Year" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('label')" />
    </div>

    <div>
        <x-input-label for="display_order" :value="__('Display Order')" />
        <x-text-input id="display_order" name="display_order" type="number" min="1" class="mt-1 block w-full" :value="old('display_order', $yearLevel?->display_order ?? $suggestedDisplayOrder)" required />
        <p class="mt-1 text-xs text-slate-500">Controls sort order in dropdowns; must be unique.</p>
        <x-input-error class="mt-2" :messages="$errors->get('display_order')" />
    </div>

    <div>
        <x-input-label for="status" :value="__('Status')" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (['Active', 'Inactive'] as $status)
                <option value="{{ $status }}" @selected(old('status', $yearLevel?->status ?? 'Active') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Only Active year levels appear in the New Assessment / Student Information dropdowns.</p>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Year Level') }}
    </x-primary-button>

    <a href="{{ route('settings.records') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
        {{ __('Cancel') }}
    </a>
</div>
