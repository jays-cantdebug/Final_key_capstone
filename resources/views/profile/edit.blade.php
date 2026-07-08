<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>
    </div>
</x-app-layout>
