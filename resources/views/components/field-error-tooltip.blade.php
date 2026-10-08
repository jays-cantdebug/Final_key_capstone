@props(['message' => null, 'align' => 'left', 'field' => null, 'id' => null])

{{--
    Two modes:
    - static (default): renders the server-side `message`; visibility follows
      the enclosing Alpine scope's `show` flag.
    - `field`: message and visibility come from the enclosing Alpine scope's
      `errors` / `showsTooltip()` (see resources/js/session-form.js), so
      client-side checks can raise the same tooltip without a page reload.
    `id` (optional) lets the field point at the message with
    aria-describedby (assessments/create/_student-fields).
--}}
@if ($message || $field)
    <div
        @if ($id) id="{{ $id }}" @endif
        @if ($field)
            x-show="showsTooltip(@js($field))"
            style="display: none;"
        @else
            x-show="show"
        @endif
        x-transition
        class="pointer-events-none absolute top-full z-20 mt-2 w-max max-w-[220px] sm:max-w-xs {{ $align === 'right' ? 'right-0' : 'left-0' }}"
    >
        <div class="absolute -top-1 h-2 w-2 rotate-45 bg-red-600 dark:bg-red-500 {{ $align === 'right' ? 'right-4' : 'left-4' }}"></div>
        <div class="relative rounded-md bg-red-600 px-3 py-2 text-xs font-medium text-white shadow-lg dark:bg-red-500" @if ($field) x-text="errors[@js($field)]" @endif>
            {{ $message }}
        </div>
    </div>
@endif
