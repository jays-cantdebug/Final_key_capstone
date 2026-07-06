@php
    $versionStatusClasses = [
        'Draft' => 'bg-slate-200 text-slate-600',
        'Active' => 'bg-emerald-100 text-emerald-700',
        'Archived' => 'bg-amber-100 text-amber-700',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Questionnaire Management</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $questionnaire->title }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('questionnaires.edit', $questionnaire) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Edit questionnaire
                </a>
                <a href="{{ route('questionnaires.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Back to list
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
            <div>
                <p class="text-sm text-slate-500">{{ $questionnaire->description }}</p>
            </div>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $questionnaire->status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                {{ $questionnaire->status }}
            </span>
        </div>

        <div class="mt-6 flex items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-slate-900">Questionnaire Versions</h3>
            <a href="{{ route('questionnaires.versions.create', $questionnaire) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                Create version
            </a>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Effective Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Questions</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($versions as $version)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">v{{ $version->version_number }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $version->effective_date->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $version->questions_count }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $versionStatusClasses[$version->status] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $version->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('questionnaires.versions.show', [$questionnaire, $version]) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>

                                    @if ($version->isEditable())
                                        <a href="{{ route('questionnaires.versions.edit', [$questionnaire, $version]) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>

                                        <form method="POST" action="{{ route('questionnaires.versions.activate', [$questionnaire, $version]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1.5 font-medium text-emerald-700 transition hover:bg-emerald-50">Activate</button>
                                        </form>

                                        <form method="POST" action="{{ route('questionnaires.versions.destroy', [$questionnaire, $version]) }}" onsubmit="return confirm('Delete this draft version?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                        </form>
                                    @elseif ($version->status === 'Active')
                                        <form method="POST" action="{{ route('questionnaires.versions.archive', [$questionnaire, $version]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-full border border-amber-200 px-3 py-1.5 font-medium text-amber-700 transition hover:bg-amber-50">Archive</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                No versions have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
