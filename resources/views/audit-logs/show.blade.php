<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Audit Logs</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $log->action }}</h2>
            </div>
            <x-secondary-button :href="route('audit-logs.index')">
                Back to list
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="min-w-0 space-y-6">
            <x-card>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Old Values</h3>
                @if ($log->old_values)
                    <pre class="mt-4 overflow-x-auto rounded-lg bg-slate-50 dark:bg-slate-800 p-4 text-xs text-slate-700 dark:text-slate-300">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                @else
                    <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">No previous values recorded.</p>
                @endif
            </x-card>

            <x-card>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">New Values</h3>
                @if ($log->new_values)
                    <pre class="mt-4 overflow-x-auto rounded-lg bg-slate-50 dark:bg-slate-800 p-4 text-xs text-slate-700 dark:text-slate-300">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                @else
                    <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">No new values recorded.</p>
                @endif
            </x-card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-card>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Details</h3>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">User</dt>
                        <dd class="mt-1 text-sm font-medium text-body dark:text-slate-100">{{ $log->user?->name ?? 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Module</dt>
                        <dd class="mt-1 text-sm font-medium text-body dark:text-slate-100">{{ $log->module }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Action</dt>
                        <dd class="mt-1 text-sm font-medium text-body dark:text-slate-100">{{ $log->action }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Record ID</dt>
                        <dd class="mt-1 text-sm font-medium text-body dark:text-slate-100">{{ $log->record_id ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">IP Address</dt>
                        <dd class="mt-1 text-sm font-medium text-body dark:text-slate-100">{{ $log->ip_address ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">User Agent</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-body dark:text-slate-100">{{ $log->user_agent ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Timestamp</dt>
                        <dd class="mt-1 text-sm font-medium text-body dark:text-slate-100">{{ $log->created_at->format('M d, Y g:i:s A') }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
