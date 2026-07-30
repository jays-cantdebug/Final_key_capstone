<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    @if ($errors->updatePassword->any())
        <x-alert type="error" class="mb-6">{{ $errors->updatePassword->first() }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>
    </div>
</x-app-layout>
