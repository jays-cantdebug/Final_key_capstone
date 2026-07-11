<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Questionnaire Management</p>
                <h2 class="text-2xl font-semibold text-body">Questionnaires</h2>
            </div>
            <x-primary-button :href="route('questionnaires.create')">
                Add questionnaire
            </x-primary-button>
        </div>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <x-table>
        <x-slot:header>
            <p class="text-sm text-slate-600">Manage assessment questionnaire templates and their released versions.</p>
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
                <x-table.td class="font-medium text-body">{{ $questionnaire->title }}</x-table.td>
                <x-table.td>{{ \Illuminate\Support\Str::limit($questionnaire->description, 60) }}</x-table.td>
                <x-table.td>{{ $questionnaire->versions_count }}</x-table.td>
                <x-table.td>
                    <x-badge :color="$questionnaire->status === 'Active' ? 'green' : 'slate'">{{ $questionnaire->status }}</x-badge>
                </x-table.td>
                <x-table.td align="right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                        <a href="{{ route('questionnaires.show', $questionnaire) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                        <a href="{{ route('questionnaires.edit', $questionnaire) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
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
</x-app-layout>
