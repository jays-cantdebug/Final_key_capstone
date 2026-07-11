@php
    $versionStatusColors = [
        'Draft' => 'slate',
        'Active' => 'green',
        'Archived' => 'amber',
    ];

    $subscaleColors = [
        'Depression' => 'blue',
        'Anxiety' => 'purple',
        'Stress' => 'orange',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $questionnaire->title }}</p>
                <h2 class="text-2xl font-semibold text-body">Version v{{ $version->version_number }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($version->isEditable())
                    <x-primary-button :href="route('questionnaires.versions.edit', [$questionnaire, $version])">
                        Edit version
                    </x-primary-button>
                @endif
                <x-secondary-button :href="route('questionnaires.show', $questionnaire)">
                    Back to questionnaire
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @error('version')
        <x-alert type="error" class="mb-6">{{ $message }}</x-alert>
    @enderror

    <x-card class="mb-6">
        <div class="flex items-start justify-between gap-4">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Effective Date</dt>
                    <dd class="mt-1 text-sm font-medium text-body">{{ $version->effective_date->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Questions</dt>
                    <dd class="mt-1 text-sm font-medium text-body">{{ $questions->count() }}</dd>
                </div>
            </dl>
            <div class="text-right">
                <x-badge :color="$versionStatusColors[$version->status] ?? 'slate'">{{ $version->status }}</x-badge>
                <p class="mt-1 max-w-[14rem] text-[11px] leading-snug text-slate-500">Version status &mdash; controls which version is currently used for new assessments. Independent from the questionnaire's own template status.</p>
            </div>
        </div>
    </x-card>

    <x-table>
        <x-slot:header>
            <h3 class="text-lg font-semibold text-body">Questions</h3>
            <div class="flex flex-wrap gap-2">
                @if ($version->isEditable())
                    <x-primary-button :href="route('questionnaires.versions.questions.create', [$questionnaire, $version])">
                        Add question
                    </x-primary-button>
                    <form method="POST" action="{{ route('questionnaires.versions.activate', [$questionnaire, $version]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                            Activate version
                        </button>
                    </form>
                @elseif ($version->status === 'Active')
                    <form method="POST" action="{{ route('questionnaires.versions.archive', [$questionnaire, $version]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-50">
                            Archive version
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('questionnaires.versions.activate', [$questionnaire, $version]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                            Activate version
                        </button>
                    </form>
                @endif
            </div>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>#</x-table.th>
            <x-table.th>Question Text</x-table.th>
            <x-table.th>Subscale</x-table.th>
            <x-table.th>Order</x-table.th>
            <x-table.th>Required</x-table.th>
            @if ($version->isEditable())
                <x-table.th align="right">Actions</x-table.th>
            @endif
        </x-slot:head>

        @forelse ($questions as $question)
            <tr>
                <x-table.td class="font-medium text-body">{{ $question->item_number }}</x-table.td>
                <x-table.td>{{ $question->question_text }}</x-table.td>
                <x-table.td><x-badge :color="$subscaleColors[$question->subscale] ?? 'slate'">{{ $question->subscale }}</x-badge></x-table.td>
                <x-table.td>{{ $question->display_order }}</x-table.td>
                <x-table.td>{{ $question->is_required ? 'Yes' : 'No' }}</x-table.td>
                @if ($version->isEditable())
                    <x-table.td align="right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <a href="{{ route('questionnaires.versions.questions.edit', [$questionnaire, $version, $question]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                            <form method="POST" action="{{ route('questionnaires.versions.questions.destroy', [$questionnaire, $version, $question]) }}" onsubmit="return confirm('Delete this question?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                            </form>
                        </div>
                    </x-table.td>
                @endif
            </tr>
        @empty
            <x-table.empty :colspan="$version->isEditable() ? 6 : 5">No questions have been added yet.</x-table.empty>
        @endforelse
    </x-table>
</x-app-layout>
