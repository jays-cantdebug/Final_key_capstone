@extends('layouts.student-device')

{{-- The generic page for every reason (wrong, expired or used code or link,
     refused address, lockout, error), so "Try again" is the same for all.
     A GET form rather than a link: the student device has no <a> at all. --}}
@section('content')
    @include('student-device._message', ['heading' => __('student_device.unavailable_heading'), 'body' => __('student_device.unavailable_body')])

    <form method="GET" action="{{ route('student-device.entry') }}" class="mx-auto mt-6 max-w-md text-center">
        <x-primary-button>{{ __('student_device.unavailable_retry') }}</x-primary-button>
    </form>
@endsection
