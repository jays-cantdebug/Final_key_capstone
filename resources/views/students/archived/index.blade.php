<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Students</h2>
    </x-slot>

    @if (session('status'))
        <x-toast type="success" :duration="8000">{{ session('status') }}</x-toast>
    @endif

    @if ($errors->any())
        {{-- Stays until dismissed: a restore conflict names the active record to use instead. --}}
        <x-toast type="error" :duration="0">{{ $errors->first() }}</x-toast>
    @endif

    @include('students._tabs', ['active' => 'students.archived.index'])

    <div
        x-data="{ restoreAction: '', restoreName: '' }"
        x-on:open-restore.window="restoreAction = $event.detail.action; restoreName = $event.detail.name; $dispatch('open-modal', 'restore-student')"
    >
        <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
            <div x-ref="results">
                @include('students.archived._table', ['students' => $students, 'search' => $search, 'activeMatches' => $activeMatches])
            </div>
        </div>

        <x-modal name="restore-student" maxWidth="md">
            <form method="POST" x-bind:action="restoreAction" class="p-6">
                @csrf
                @method('PATCH')

                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Restore <span x-text="restoreName"></span>?</h3>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                    The student returns to the Students list with the same student number and can use Take Again. Their assessments and counseling history were never removed and stay as they are.
                </p>

                <div class="mt-4">
                    <x-input-label for="reason" :value="__('Reason (optional)')" />
                    <x-textarea id="reason" name="reason" rows="3" maxlength="255" class="mt-1 block w-full"></x-textarea>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Up to 255 characters, saved in the Audit Log. Don’t include sensitive personal details (health, family or counseling information).</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'restore-student')">Cancel</x-secondary-button>
                    <x-primary-button>Restore</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
