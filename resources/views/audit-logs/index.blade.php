<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Audit Logs</p>
            <h2 class="text-2xl font-semibold text-body">Audit Logs</h2>
        </div>
    </x-slot>

    <x-table>
        <x-slot:header>
            <form method="GET" action="{{ route('audit-logs.index') }}" class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="module" :value="__('Module')" />
                    <x-select id="module" name="module" class="mt-1 block w-full">
                        <option value="">All modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <x-input-label for="action" :value="__('Action')" />
                    <x-select id="action" name="action" class="mt-1 block w-full">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </x-select>
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
                    <x-secondary-button :href="route('audit-logs.index')">
                        {{ __('Clear') }}
                    </x-secondary-button>
                </div>
            </form>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Timestamp</x-table.th>
            <x-table.th>User</x-table.th>
            <x-table.th>Module</x-table.th>
            <x-table.th>Action</x-table.th>
            <x-table.th>Record #</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($logs as $log)
            <tr>
                <x-table.td>{{ $log->created_at->format('M d, Y g:i A') }}</x-table.td>
                <x-table.td>{{ $log->user?->name ?? 'System' }}</x-table.td>
                <x-table.td>{{ $log->module }}</x-table.td>
                <x-table.td class="font-medium text-body">{{ $log->action }}</x-table.td>
                <x-table.td>{{ $log->record_id ?? '—' }}</x-table.td>
                <x-table.td align="right">
                    <a href="{{ route('audit-logs.show', $log) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="6">No audit log entries found.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $logs->links() }}
        </x-slot:footer>
    </x-table>
</x-app-layout>
