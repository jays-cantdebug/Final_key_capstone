{{--
    The student device's own layout: no navigation, no sidebar, no links,
    no app name, no user, no CSRF token, no theme script (inline script is
    blocked by the CSP; StudentDeviceHeaders), always light. Only the
    student-device script runs (resources/js/student-device.js), not app.js.
--}}
<!DOCTYPE html>
{{-- scroll-pb-32: a field the browser scrolls to (autofocus, Tab) stays clear of the questionnaire's fixed bottom bar. --}}
<html lang="en" class="scroll-pb-32">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="same-origin">
        <meta name="color-scheme" content="light">

        <title>{{ __('student_device.title') }}</title>

        {{-- The staff layouts' favicon: a static same-origin file (CSP img-src 'self'), served by the web server without Laravel, so no cookie or session. --}}
        <link rel="icon" type="image/png" href="{{ asset('images/normi-logo-favicon.png') }}?v={{ filemtime(public_path('images/normi-logo-favicon.png')) }}">

        @include('layouts.partials.font-preloads', ['weights' => [400, 500, 600]])

        @vite(['resources/css/app.css', 'resources/js/student-device.js'])
    </head>
    <body class="bg-page font-sans text-body antialiased">
        <main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
            @yield('content')
        </main>
    </body>
</html>
