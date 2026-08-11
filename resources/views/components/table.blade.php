@props(['fixed' => false])

<div {{ $attributes->merge(['class' => 'w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800']) }}>
    @isset($header)
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-700">
            {{ $header }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        <table @class(['min-w-full divide-y divide-slate-200 dark:divide-slate-700', 'table-fixed' => $fixed])>
            @isset($head)
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        {{ $head }}
                    </tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-800">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-700">
            {{ $footer }}
        </div>
    @endisset
</div>
