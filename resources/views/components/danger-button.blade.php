<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-md bg-[#B3261E] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#8F1E18] focus:outline-none focus:ring-2 focus:ring-[#B3261E] focus:ring-offset-2 disabled:opacity-50 dark:bg-[#9B4A44] dark:hover:bg-[#7E3B36] dark:focus:ring-[#9B4A44] dark:focus:ring-offset-slate-900']) }}>
    {{ $slot }}
</button>
