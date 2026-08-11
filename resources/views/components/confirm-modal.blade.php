@props([
    'name' => 'confirm-modal',
])

<div
    x-data="{
        title: '',
        message: '',
        confirmLabel: 'Confirm',
        variant: 'danger',
        formId: null,
    }"
    x-on:open-confirm.window="
        if ($event.detail.name === '{{ $name }}') {
            title = $event.detail.title;
            message = $event.detail.message ?? '';
            confirmLabel = $event.detail.confirmLabel ?? 'Confirm';
            variant = $event.detail.variant ?? 'danger';
            formId = $event.detail.formId;
            $dispatch('open-modal', '{{ $name }}');
        }
    "
>
    <x-modal :name="$name" :show="false" maxWidth="md" :closeable="false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-body dark:text-slate-100" x-text="title"></h3>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400" x-show="message" x-text="message"></p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="$dispatch('close-modal', '{{ $name }}')"
                    class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:ring-primary-soft"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="$dispatch('close-modal', '{{ $name }}'); document.getElementById(formId).submit()"
                    :class="variant === 'danger'
                        ? 'bg-[#B3261E] hover:bg-[#8F1E18] focus:ring-[#B3261E] dark:bg-[#9B4A44] dark:hover:bg-[#7E3B36] dark:focus:ring-[#9B4A44]'
                        : 'bg-primary hover:bg-primary-dark focus:ring-primary dark:bg-primary-soft dark:hover:bg-primary-soft/90 dark:focus:ring-primary-soft'"
                    class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                >
                    <span x-text="confirmLabel"></span>
                </button>
            </div>
        </div>
    </x-modal>
</div>
