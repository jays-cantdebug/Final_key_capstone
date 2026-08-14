@php
    $versionStatusColors = [
        'Draft' => 'slate',
        'Active' => 'green',
        'Archived' => 'amber',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Questionnaire Management</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $questionnaire->title }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-primary-button :href="route('questionnaires.edit', $questionnaire)">
                    Edit questionnaire
                </x-primary-button>
                <x-secondary-button :href="route('questionnaires.index')">
                    Back to list
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    @error('version')
        <x-alert type="error" class="mb-6">{{ $message }}</x-alert>
    @enderror

    <x-card class="mb-6">
        <div class="flex items-start justify-between gap-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $questionnaire->description }}</p>
            <div class="text-right">
                <x-badge :color="$questionnaire->status === 'Active' ? 'green' : 'slate'">{{ $questionnaire->status }}</x-badge>
                <p class="mt-1 max-w-[14rem] text-[11px] leading-snug text-slate-500 dark:text-slate-400">Template status &mdash; controls whether this questionnaire can be selected at all.</p>
            </div>
        </div>
    </x-card>

    <x-table>
        <x-slot:header>
            <div>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Questionnaire Versions</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Version status &mdash; controls which version is currently used for new assessments. Independent from the template status above.</p>
            </div>
            <x-primary-button :href="route('questionnaires.versions.create', $questionnaire)">
                Create version
            </x-primary-button>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Version</x-table.th>
            <x-table.th>Effective Date</x-table.th>
            <x-table.th>Questions</x-table.th>
            <x-table.th>Version Status</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($versions as $version)
            <tr>
                <x-table.td class="font-medium text-body dark:text-slate-100">v{{ $version->version_number }}</x-table.td>
                <x-table.td>{{ $version->effective_date->format('M d, Y') }}</x-table.td>
                <x-table.td>{{ $version->questions_count }}</x-table.td>
                <x-table.td><x-badge :color="$versionStatusColors[$version->status] ?? 'slate'">{{ $version->status }}</x-badge></x-table.td>
                <x-table.td align="right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                        <a href="{{ route('questionnaires.versions.show', [$questionnaire, $version]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View</a>

                        @if ($version->isEditable())
                            <a href="{{ route('questionnaires.versions.edit', [$questionnaire, $version]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">Edit</a>

                            <form method="POST" action="{{ route('questionnaires.versions.activate', [$questionnaire, $version]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-md border border-emerald-200 px-3 py-1.5 font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/40">Activate</button>
                            </form>

                            <form id="delete-version-form-{{ $version->id }}" method="POST" action="{{ route('questionnaires.versions.destroy', [$questionnaire, $version]) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this draft version?', message: 'This action cannot be undone.', confirmLabel: 'Delete', formId: 'delete-version-form-{{ $version->id }}' })"
                                class="rounded-md border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50"
                            >Delete</button>
                        @elseif ($version->status === 'Active')
                            <form method="POST" action="{{ route('questionnaires.versions.archive', [$questionnaire, $version]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-md border border-amber-200 px-3 py-1.5 font-medium text-amber-700 transition hover:bg-amber-50">Archive</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('questionnaires.versions.activate', [$questionnaire, $version]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-md border border-emerald-200 px-3 py-1.5 font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/40">Activate</button>
                            </form>
                        @endif
                    </div>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="5">No versions have been created yet.</x-table.empty>
        @endforelse
    </x-table>

    <x-confirm-modal />
</x-app-layout>
