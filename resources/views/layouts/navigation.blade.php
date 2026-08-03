<aside class="fixed inset-y-0 left-0 z-40 hidden md:flex md:w-20 md:flex-col lg:w-72">
    <div class="flex h-full flex-col gap-y-6 overflow-y-auto bg-primary px-3 pb-4 pt-6 text-white shadow-2xl shadow-emerald-950/25 lg:px-6">
        <div class="flex items-center justify-center gap-3 lg:justify-start">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <x-application-logo class="h-12 w-12 flex-shrink-0 text-white lg:h-20 lg:w-20" />
                <div class="hidden lg:block">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.35em] text-white/65">{{ app(\App\Services\SystemSettingService::class)->systemName() }}</p>
                    <p class="text-sm font-semibold">Assessment Portal</p>
                </div>
            </a>
        </div>

        @include('layouts.partials.sidebar-nav', [
            'labelClass' => 'hidden lg:inline',
            'userCardClass' => 'hidden lg:block',
            'linkJustifyClass' => 'justify-center lg:justify-start',
        ])
    </div>
</aside>

<div class="md:hidden">
    <div class="sticky top-0 z-40 flex items-center gap-x-6 bg-primary px-4 py-4 shadow-lg shadow-emerald-950/20 sm:px-6">
        <button type="button" class="-m-2.5 rounded-md p-2.5 text-white" @click="open = true">
            <span class="sr-only">Open sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 6h16.5m-16.5 6h16.5" /></svg>
        </button>
        <div class="flex-1 text-sm font-semibold text-white">{{ app(\App\Services\SystemSettingService::class)->systemName() }}</div>
    </div>

    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 bg-body/60" @click="open = false"></div>

    <aside x-show="open" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-50 w-full max-w-xs">
        <div class="flex h-full flex-col gap-y-6 overflow-y-auto bg-primary px-6 pb-4 pt-6 text-white shadow-2xl shadow-emerald-950/25">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-16 w-16 text-white" />
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.35em] text-white/65">{{ app(\App\Services\SystemSettingService::class)->systemName() }}</p>
                        <p class="text-sm font-semibold">Assessment Portal</p>
                    </div>
                </a>
                <button type="button" class="ml-auto inline-flex rounded-lg p-2 text-white/80 hover:bg-white/10" @click="open = false">
                    <span class="sr-only">Close sidebar</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                </button>
            </div>

            @include('layouts.partials.sidebar-nav')
        </div>
    </aside>
</div>
