<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Edit Questionnaire</h2>
            <x-secondary-button :href="route('questionnaires.show', $questionnaire)">
                View questionnaire
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('questionnaires.update', $questionnaire) }}">
            @csrf
            @method('PUT')

            @include('questionnaires._form', ['buttonLabel' => __('Update Questionnaire')])
        </form>
    </x-card>
</x-app-layout>
