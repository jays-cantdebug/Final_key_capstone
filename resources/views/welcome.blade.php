<x-guest-layout>
    <div class="rounded-lg bg-white p-8 shadow-[0_20px_60px_rgba(31,107,58,0.12)] ring-1 ring-black/5">
        <div class="flex items-center gap-4">
            <x-application-logo class="h-14 w-14" />
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-[#1F6B3A]">NORMI</p>
                <h1 class="mt-1 text-2xl font-bold text-[#2C2C2A]">Student DASS Assessment System</h1>
            </div>
        </div>

        <p class="mt-6 max-w-2xl text-sm leading-6 text-slate-600">
            Secure, role-based access for institutional assessment workflows. Public registration is disabled.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('login') }}" class="rounded-md bg-[#1F6B3A] px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-[#1F6B3A]/20 hover:bg-[#185828]">
                Sign in
            </a>
        </div>
    </div>
</x-guest-layout>
