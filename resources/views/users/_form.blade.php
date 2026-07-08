@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user?->email)" required />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="role_id" :value="__('Role')" />
        <x-select id="role_id" name="role_id" class="mt-1 block w-full">
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $user?->role_id) === (string) $role->id)>{{ $role->display_name }}</option>
            @endforeach
        </x-select>
        <x-input-error class="mt-2" :messages="$errors->get('role_id')" />
    </div>
</div>
