<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Questionnaires</h2>
            <x-primary-button :href="route('questionnaires.create')">
                Add questionnaire
            </x-primary-button>
        </div>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    @if ($errors->any())
        <x-toast type="error">{{ $errors->first() }}</x-toast>
    @endif

    <style>
        /* Scoped to Questionnaire Management only: keep this table from needing
           horizontal scroll inside its card, matching the Settings > Records fix. */
        .questionnaires-table td,
        .questionnaires-table th {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .questionnaires-table td {
            white-space: normal;
        }
    </style>

    <x-table class="questionnaires-table">
        <x-slot:header>
            <p class="text-sm text-slate-600 dark:text-slate-400">Manage assessment questionnaire templates and their released versions.</p>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Title</x-table.th>
            <x-table.th>Description</x-table.th>
            <x-table.th>Versions</x-table.th>
            <x-table.th>Template Status</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($questionnaires as $questionnaire)
            <tr>
                <x-table.td class="font-medium text-body dark:text-slate-100">{{ $questionnaire->title }}</x-table.td>
                <x-table.td>{{ \Illuminate\Support\Str::limit($questionnaire->description, 60) }}</x-table.td>
                <x-table.td>{{ $questionnaire->versions_count }}</x-table.td>
                <x-table.td>
                    <x-badge :color="$questionnaire->status === 'Active' ? 'green' : 'slate'">{{ $questionnaire->status }}</x-badge>
                </x-table.td>
                <x-table.td align="right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                        <a href="{{ route('questionnaires.show', $questionnaire) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View</a>
                        <a href="{{ route('questionnaires.edit', $questionnaire) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">Edit</a>
                        <form id="delete-questionnaire-form-{{ $questionnaire->id }}" method="POST" action="{{ route('questionnaires.destroy', $questionnaire) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                        <button
                            type="button"
                            @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this questionnaire?', message: 'This is blocked if any of its versions have been used by an assessment.', confirmLabel: 'Delete', formId: 'delete-questionnaire-form-{{ $questionnaire->id }}' })"
                            class="rounded-md border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50"
                        >Delete</button>
                    </div>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="5">No questionnaires found.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $questionnaires->links() }}
        </x-slot:footer>
    </x-table>

    <x-confirm-modal />
</x-app-layout>
