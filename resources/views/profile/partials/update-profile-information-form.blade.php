<section>
    <header>
        <h2 class="text-lg font-semibold text-body dark:text-slate-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            {{ __("Update your account's profile information.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6" x-data="{ preview: null, removeAvatar: false }">
        @csrf
        @method('patch')

        <div>
            <x-input-label :value="__('Profile Photo')" />
            <div class="mt-2 rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
                <div class="flex items-center gap-5">
                    <template x-if="preview">
                        <img :src="preview" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover">
                    </template>
                    <template x-if="!preview">
                        <x-avatar :user="$user" size="xl" />
                    </template>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex w-fit cursor-pointer items-center rounded-md border border-slate-300 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 shadow-sm transition hover:bg-slate-50 dark:hover:bg-slate-700">
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
                            <input type="hidden" name="remove_avatar" :value="removeAvatar ? 1 : 0">
                            <button
                                type="button"
                                @click="removeAvatar = !removeAvatar"
                                :aria-pressed="removeAvatar"
                                :class="removeAvatar
                                    ? 'border-red-600 bg-red-600 text-white hover:bg-red-700'
                                    : 'border-red-200 bg-white text-red-600 hover:bg-red-50 dark:bg-slate-800'"
                                class="inline-flex w-fit items-center rounded-md border px-4 py-2 text-sm font-semibold shadow-sm transition"
                            >
                                <span x-text="removeAvatar ? '{{ __('Photo will be removed') }}' : '{{ __('Remove Photo') }}'"></span>
                            </button>
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
