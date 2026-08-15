<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Create Questionnaire</h2>
            <x-secondary-button :href="route('questionnaires.index')">
                Back to questionnaires
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('questionnaires.store') }}">
            @csrf

            @include('questionnaires._form', ['buttonLabel' => __('Create Questionnaire')])
        </form>
    </x-card>
</x-app-layout>
