@php
    $tabs = [
        'general' => ['label' => 'General', 'route' => route('settings.edit')],
        'users' => ['label' => 'Users', 'route' => route('users.index')],
        'records' => ['label' => 'Records', 'route' => route('settings.records')],
        'thresholds' => ['label' => 'Classification Thresholds', 'route' => route('settings.classification-thresholds')],
    ];
@endphp

<div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200">
    @foreach ($tabs as $key => $tab)
        <a
            href="{{ $tab['route'] }}"
            class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $active === $key ? 'border-primary text-primary dark:border-primary-soft dark:text-primary-soft' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
