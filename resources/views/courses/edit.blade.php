<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Edit Course</h2>
            <x-secondary-button :href="route('settings.records')">
                Back to Records
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('courses.update', $course) }}">
            @csrf
            @method('PUT')
            @include('courses._form', ['buttonLabel' => __('Update Course')])
        </form>
    </x-card>
</x-app-layout>
