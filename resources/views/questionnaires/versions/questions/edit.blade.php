<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $questionnaire->title }} &mdash; v{{ $version->version_number }}</p>
                <h2 class="text-2xl font-semibold text-body">Edit Question #{{ $question->item_number }}</h2>
            </div>
            <x-secondary-button :href="route('questionnaires.versions.show', [$questionnaire, $version])">
                Back to version
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('questionnaires.versions.questions.update', [$questionnaire, $version, $question]) }}">
            @csrf
            @method('PUT')

            @include('questionnaires.versions.questions._form', ['buttonLabel' => __('Update Question')])
        </form>
    </x-card>
</x-app-layout>
