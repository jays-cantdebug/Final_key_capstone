<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <x-card>
        {{ __("You're logged in!") }}
    </x-card>
</x-app-layout>
