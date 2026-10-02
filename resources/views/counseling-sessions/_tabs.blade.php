@php
    $tabs = [
        'counseling-sessions.index' => 'All Sessions',
        'counseling-sessions.students.index' => 'By Student',
    ];
@endphp

<div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 dark:border-slate-700">
    @foreach ($tabs as $routeName => $label)
        <a
            href="{{ route($routeName) }}"
            class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $active === $routeName ? 'border-primary text-primary dark:border-primary-soft dark:text-primary-soft' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}"
        >
            {{ $label }}
        </a>
    @endforeach
</div>
