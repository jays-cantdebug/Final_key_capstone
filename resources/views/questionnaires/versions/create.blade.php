<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $questionnaire->title }}</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Create Version</h2>
            </div>
            <x-secondary-button :href="route('questionnaires.show', $questionnaire)">
                Back to questionnaire
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('questionnaires.versions.store', $questionnaire) }}">
            @csrf

            @include('questionnaires.versions._form', ['buttonLabel' => __('Create Version')])
        </form>
    </x-card>
</x-app-layout>
