<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ app(\App\Services\SystemSettingService::class)->systemName() }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/normi-logo-favicon.png') }}?v={{ filemtime(public_path('images/normi-logo-favicon.png')) }}">

        <script>
            (function () {
                // Light mode is the unconditional default; dark mode only
                // activates once the user has explicitly toggled it.
                document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark');
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden font-sans antialiased text-body bg-[linear-gradient(180deg,_#FBFAF7_0%,_#F8F6F0_100%)] dark:bg-none dark:bg-slate-900 dark:text-slate-100">
        <div x-data="{ open: false }" class="min-h-screen">
            @include('layouts.navigation')

            <div class="md:pl-20 lg:pl-72">
                @isset($header)
                    <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur dark:border-slate-700/80 dark:bg-slate-800/80">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
