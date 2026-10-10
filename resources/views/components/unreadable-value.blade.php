{{--
    Shown in place of an encrypted value that can't be decrypted with the
    current APP_KEY (or APP_PREVIOUS_KEYS): the value is kept unchanged in
    the database. Plain markup, so it also reads correctly in the print and
    PDF views.
--}}
<span {{ $attributes->merge(['class' => 'text-sm font-medium italic text-amber-700 dark:text-amber-400']) }} data-unreadable>Unreadable (encrypted with a previous key)</span>
