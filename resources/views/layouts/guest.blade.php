@props(['background' => 'bg-[radial-gradient(circle_at_top_left,_rgba(31,107,58,0.08),_transparent_40%),linear-gradient(180deg,_#FBFAF7_0%,_#F7F5EE_100%)] dark:bg-none dark:bg-slate-900'])

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
    <body class="font-sans antialiased text-body {{ $background }} dark:text-slate-100">
        <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-7xl items-center">
                <div class="w-full">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
