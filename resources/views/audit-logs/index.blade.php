<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Audit Logs</p>
            <h2 class="text-2xl font-semibold text-slate-900">Audit Logs</h2>
        </div>
    </x-slot>

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-input-label for="module" :value="__('Module')" />
                <select id="module" name="module" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="action" :value="__('Action')" />
                <select id="action" name="action" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="date_from" :value="__('Date From')" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" value="{{ $filters['date_from'] ?? '' }}" />
            </div>

            <div>
                <x-input-label for="date_to" :value="__('Date To')" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" value="{{ $filters['date_to'] ?? '' }}" />
            </div>

            <div class="flex items-end gap-3 lg:col-span-4">
                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                <a href="{{ route('audit-logs.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    {{ __('Clear') }}
                </a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Module</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Record #</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $log->module }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $log->action }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $log->record_id ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('audit-logs.show', $log) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                No audit log entries found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
