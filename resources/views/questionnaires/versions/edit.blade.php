<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $questionnaire->title }}</p>
                <h2 class="text-2xl font-semibold text-slate-900">Edit Version v{{ $version->version_number }}</h2>
            </div>
            <a href="{{ route('questionnaires.versions.show', [$questionnaire, $version]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                View version
            </a>
        </div>
    </x-slot>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('questionnaires.versions.update', [$questionnaire, $version]) }}">
            @csrf
            @method('PUT')

            @include('questionnaires.versions._form', ['buttonLabel' => __('Update Version')])
        </form>
    </div>
</x-app-layout>
