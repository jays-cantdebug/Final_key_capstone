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
                    <div class="relative inline-block h-20 w-20 flex-shrink-0">
                        <template x-if="preview">
                            <img :src="preview" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover">
                        </template>
                        <template x-if="!preview">
                            <x-avatar :user="$user" size="xl" />
                        </template>

                        <label class="absolute bottom-0 right-0 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-primary text-white ring-2 ring-white shadow-sm transition hover:bg-primary-dark dark:ring-slate-800" aria-label="{{ __('Change profile photo') }}">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.5 3.5A1.5 1.5 0 0 1 7.87 2.5h4.26a1.5 1.5 0 0 1 1.37 1l.3.9h1.7A2.5 2.5 0 0 1 18 6.9v7.1a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 2 14V6.9a2.5 2.5 0 0 1 2.5-2.5h1.7l.3-.9Zm3.5 3a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm0 1.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z" />
                            </svg>
                            <input
                                type="file"
                                name="avatar"
                                accept="image/png,image/jpeg,image/webp"
                                class="hidden"
                                @change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
                            >
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
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
