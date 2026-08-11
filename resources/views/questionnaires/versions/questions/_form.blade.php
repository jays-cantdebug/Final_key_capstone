@php
    /** @var \App\Models\DassQuestion|null $question */
    $question = $question ?? null;
    $isRequiredChecked = (bool) old('is_required', $question?->is_required ?? true);
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="item_number" :value="__('Item Number')" />
        <x-text-input id="item_number" name="item_number" type="number" min="1" class="mt-1 block w-full" :value="old('item_number', $question?->item_number)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('item_number')" />
    </div>

    <div>
        <x-input-label for="display_order" :value="__('Display Order')" />
        <x-text-input id="display_order" name="display_order" type="number" min="1" class="mt-1 block w-full" :value="old('display_order', $question?->display_order)" required />
        <x-input-error class="mt-2" :messages="$errors->get('display_order')" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="question_text" :value="__('Question Text')" />
        <x-textarea id="question_text" name="question_text" rows="3" class="mt-1 block w-full" required>{{ old('question_text', $question?->question_text) }}</x-textarea>
        <x-input-error class="mt-2" :messages="$errors->get('question_text')" />
    </div>

    <div>
        <x-input-label for="subscale" :value="__('Subscale')" />
        <x-select id="subscale" name="subscale" class="mt-1 block w-full">
            @foreach (['Depression', 'Anxiety', 'Stress'] as $subscale)
                <option value="{{ $subscale }}" @selected(old('subscale', $question?->subscale) === $subscale)>{{ $subscale }}</option>
            @endforeach
        </x-select>
        <x-input-error class="mt-2" :messages="$errors->get('subscale')" />
    </div>

    <div>
        <x-input-label for="question_type" :value="__('Question Type')" />
        <x-select id="question_type" name="question_type" class="mt-1 block w-full">
            <option value="Likert Scale" @selected(old('question_type', $question?->question_type ?? 'Likert Scale') === 'Likert Scale')>Likert Scale</option>
        </x-select>
        <x-input-error class="mt-2" :messages="$errors->get('question_type')" />
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="is_required" value="0" />
            <x-checkbox name="is_required" value="1" :checked="$isRequiredChecked" />
            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('This question is required') }}</span>
        </label>
        <x-input-error class="mt-2" :messages="$errors->get('is_required')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Question') }}
    </x-primary-button>

    <x-secondary-button :href="route('questionnaires.versions.show', [$questionnaire, $version])">
        {{ __('Cancel') }}
    </x-secondary-button>
</div>
