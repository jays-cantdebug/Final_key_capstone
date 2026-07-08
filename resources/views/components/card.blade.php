@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'w-full overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm']) }}>
    @isset($header)
        <div class="border-b border-slate-200 px-6 py-4">
            {{ $header }}
        </div>
    @endisset

    <div @class([$padded ? 'p-6' : ''])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</div>
