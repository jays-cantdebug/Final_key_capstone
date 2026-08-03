@props(['message'])

<div
    x-show="show"
    x-transition
    class="pointer-events-none fixed z-20 w-max"
    :style="`left: ${x + 14}px; top: ${y - 12}px;`"
>
    <div class="rounded-md border border-primary/30 bg-tint px-3 py-2 text-xs font-medium text-primary-dark shadow-md">
        {{ $message }}
    </div>
</div>
