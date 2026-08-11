@props(['question', 'selected' => null, 'invalid' => false])

<div
    x-data="{ show: {{ $invalid ? 'true' : 'false' }} }"
    {{ $attributes->merge(['class' => $invalid
        ? 'relative rounded-lg border-2 border-red-500 bg-white p-6 shadow-sm dark:border-red-400 dark:bg-slate-800'
        : 'relative rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800']) }}
>
    <div class="flex items-start justify-between gap-4">
        <p class="text-sm font-medium text-body dark:text-slate-100">{{ $question->item_number }}. {{ $question->question_text }}</p>
        <span class="shrink-0 rounded-md bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $question->subscale }}</span>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ([0, 1, 2, 3] as $value)
            <label class="cursor-pointer">
                <input
                    type="radio"
                    name="responses[{{ $question->id }}]"
                    value="{{ $value }}"
                    class="peer sr-only"
                    @checked((string) $selected === (string) $value)
                    @change="show = false"
                    @if ($invalid && $value === 0) data-field-invalid @endif
                />
                <div class="rounded-md border-2 border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-600 transition peer-checked:border-primary peer-checked:bg-tint peer-checked:text-primary dark:border-slate-600 dark:text-slate-300 dark:peer-checked:border-primary-soft dark:peer-checked:bg-primary-soft/15 dark:peer-checked:text-primary-soft">
                    {{ $value }}
                </div>
            </label>
        @endforeach
    </div>

    <x-field-error-tooltip :message="$errors->first('responses.' . $question->id)" align="right" />
</div>
