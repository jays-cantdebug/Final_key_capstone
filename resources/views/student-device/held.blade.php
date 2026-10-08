@extends('layouts.student-device')

{{-- Held (the Psychometrician has to look at something first): exactly the
     generic "not available" text, so this never says why. The script polls
     the state and reopens the page once the draft continues. --}}
@section('content')
    <div data-student-device="held" data-state-url="{{ route('student-device.state') }}" data-page-url="{{ route('student-device.show') }}">
        @include('student-device._message', ['heading' => __('student_device.unavailable_heading'), 'body' => __('student_device.unavailable_body')])
    </div>
@endsection
