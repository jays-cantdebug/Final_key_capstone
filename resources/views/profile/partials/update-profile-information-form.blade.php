<section>
    <header>
        <h2 class="text-lg font-semibold text-body">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __("Update your account's profile information.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6" x-data="{ preview: null }">
        @csrf
        @method('patch')

        <div>
            <x-input-label :value="__('Profile Photo')" />
            <div class="mt-2 rounded-lg bg-slate-50 p-4">
                <div class="flex items-center gap-5">
                    <template x-if="preview">
                        <img :src="preview" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover">
                    </template>
                    <template x-if="!preview">
                        <x-avatar :user="$user" size="xl" />
                    </template>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex w-fit cursor-pointer items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            <span>{{ __('Choose Photo') }}</span>
                            <input
                                type="file"
                                name="avatar"
                                accept="image/png,image/jpeg,image/webp"
                                class="hidden"
                                @change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
                            >
                        </label>

                        @if ($user->avatar_path)
                            <label class="inline-flex w-fit cursor-pointer items-center rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50">
                                <input type="checkbox" name="remove_avatar" value="1" class="mr-2 rounded border-red-300 text-red-600 focus:ring-red-500">
                                {{ __('Remove Photo') }}
                            </label>
                        @endif
                    </div>
                </div>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
