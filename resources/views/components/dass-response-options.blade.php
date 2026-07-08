@props(['question', 'selected' => null])

<div {{ $attributes->merge(['class' => 'rounded-3xl border border-slate-200 bg-white p-6 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-4">
        <p class="text-sm font-medium text-body">{{ $question->item_number }}. {{ $question->question_text }}</p>
        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $question->subscale }}</span>
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
                    required
                />
                <div class="rounded-xl border-2 border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-600 transition peer-checked:border-primary peer-checked:bg-tint peer-checked:text-primary">
                    {{ $value }}
                </div>
            </label>
        @endforeach
    </div>

    <x-input-error class="mt-2" :messages="$errors->get('responses.' . $question->id)" />
</div>
