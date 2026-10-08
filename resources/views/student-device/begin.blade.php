@extends('layouts.student-device')

{{-- Opening the link only shows this page; pressing Begin (a POST)
     claims the device, so a link preview or scanner can't use up the
     token. The form has no action attribute: it posts back to this page's
     own address, so the token appears nowhere in the HTML. --}}
@section('content')
    <form method="POST" class="mx-auto mt-16 max-w-md rounded-lg border border-slate-200 bg-white px-6 py-8 text-center shadow-sm">
        <h1 class="text-xl font-semibold text-body">{{ __('student_device.begin_heading') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('student_device.begin_help') }}</p>

        <button type="submit" class="mt-6 w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            {{ __('student_device.begin_submit') }}
        </button>
    </form>
@endsection
