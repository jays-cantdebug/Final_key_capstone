<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Student Management</p>
                <h2 class="text-2xl font-semibold text-body">Edit Student</h2>
            </div>
            <x-secondary-button :href="route('students.show', $student)">
                View student
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('students.update', $student) }}">
            @csrf
            @method('PUT')

            @include('students._form', ['buttonLabel' => __('Update Student')])
        </form>
    </x-card>
</x-app-layout>
