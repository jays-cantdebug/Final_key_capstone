<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Questionnaire Management</p>
                <h2 class="text-2xl font-semibold text-body">Edit Questionnaire</h2>
            </div>
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
