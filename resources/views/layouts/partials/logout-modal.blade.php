{{-- Logout confirmation (both roles). The sidebar's Logout buttons (desktop
     and mobile) only open this; the one logout POST form lives here. Cancel
     comes first, so it gets the focus and an accidental Enter never logs out.
     On close the focus goes back to the Logout button, or (mobile drawer,
     closed by then) to the button that opens the drawer. A click on the
     backdrop does not close it; Cancel and Escape do. --}}
<x-modal name="confirm-logout" maxWidth="md" :close-on-backdrop="false" focusable restore-focus="[data-sidebar-open]">
    <div class="p-6" role="dialog" aria-modal="true" aria-labelledby="confirm-logout-title" aria-describedby="confirm-logout-message">
        <h2 id="confirm-logout-title" class="text-lg font-semibold text-body dark:text-slate-100">Log out?</h2>
        <p id="confirm-logout-message" class="mt-3 text-sm text-slate-600 dark:text-slate-400">You will need to sign in again to continue.</p>
        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                data-logout-cancel
                @click="$dispatch('close-modal', 'confirm-logout')"
                class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:ring-primary-soft"
            >
                Cancel
            </button>
            <form method="POST" action="{{ route('logout') }}" data-logout-form>
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-primary-soft dark:hover:bg-primary-soft/90 dark:focus:ring-primary-soft"
                >
                    Log out
                </button>
            </form>
        </div>
    </div>
</x-modal>
