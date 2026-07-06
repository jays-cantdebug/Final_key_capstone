@php
    $versionStatusClasses = [
        'Draft' => 'bg-slate-200 text-slate-600',
        'Active' => 'bg-emerald-100 text-emerald-700',
        'Archived' => 'bg-amber-100 text-amber-700',
    ];

    $subscaleClasses = [
        'Depression' => 'bg-blue-100 text-blue-700',
        'Anxiety' => 'bg-purple-100 text-purple-700',
        'Stress' => 'bg-orange-100 text-orange-700',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $questionnaire->title }}</p>
                <h2 class="text-2xl font-semibold text-slate-900">Version v{{ $version->version_number }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($version->isEditable())
                    <a href="{{ route('questionnaires.versions.edit', [$questionnaire, $version]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                        Edit version
                    </a>
                @endif
                <a href="{{ route('questionnaires.show', $questionnaire) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Back to questionnaire
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @error('version')
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $message }}
        </div>
    @enderror

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Effective Date</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $version->effective_date->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Questions</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $questions->count() }}</dd>
                </div>
            </dl>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $versionStatusClasses[$version->status] ?? 'bg-slate-200 text-slate-600' }}">
                {{ $version->status }}
            </span>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-slate-900">Questions</h3>
            <div class="flex flex-wrap gap-2">
                @if ($version->isEditable())
                    <a href="{{ route('questionnaires.versions.questions.create', [$questionnaire, $version]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                        Add question
                    </a>
                    <form method="POST" action="{{ route('questionnaires.versions.activate', [$questionnaire, $version]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                            Activate version
                        </button>
                    </form>
                @elseif ($version->status === 'Active')
                    <form method="POST" action="{{ route('questionnaires.versions.archive', [$questionnaire, $version]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-50">
                            Archive version
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Question Text</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Subscale</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Required</th>
                        @if ($version->isEditable())
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($questions as $question)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $question->item_number }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $question->question_text }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $subscaleClasses[$question->subscale] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $question->subscale }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $question->display_order }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $question->is_required ? 'Yes' : 'No' }}</td>
                            @if ($version->isEditable())
                                <td class="px-6 py-4 text-right text-sm">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('questionnaires.versions.questions.edit', [$questionnaire, $version, $question]) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('questionnaires.versions.questions.destroy', [$questionnaire, $version, $question]) }}" onsubmit="return confirm('Delete this question?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $version->isEditable() ? 6 : 5 }}" class="px-6 py-12 text-center text-sm text-slate-500">
                                No questions have been added yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
