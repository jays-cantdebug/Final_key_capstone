{{-- Shown under every new-password field; matches Password::defaults() in AppServiceProvider. --}}
<p {{ $attributes->merge(['class' => 'mt-1 text-xs text-slate-500 dark:text-slate-400']) }}>{{ __('At least 12 characters, with upper- and lowercase letters and a number. Avoid common words such as "password".') }}</p>
