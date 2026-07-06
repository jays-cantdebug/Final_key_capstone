<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Audit Logs</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $log->action }}</h2>
            </div>
            <a href="{{ route('audit-logs.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Back to list
            </a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Old Values</h3>
                @if ($log->old_values)
                    <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-50 p-4 text-xs text-slate-700">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                @else
                    <p class="mt-4 text-sm text-slate-500">No previous values recorded.</p>
                @endif
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">New Values</h3>
                @if ($log->new_values)
                    <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-50 p-4 text-xs text-slate-700">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                @else
                    <p class="mt-4 text-sm text-slate-500">No new values recorded.</p>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Details</h3>
            <dl class="mt-4 space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">User</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $log->user?->name ?? 'System' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Module</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $log->module }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Action</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $log->action }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Record ID</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $log->record_id ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">IP Address</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $log->ip_address ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">User Agent</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $log->user_agent ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Timestamp</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $log->created_at->format('M d, Y g:i:s A') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
