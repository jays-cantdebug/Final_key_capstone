@extends('layouts.student-device')

{{-- Held (the Psychometrician has to look at something first): a neutral
     "please wait" that never says why (no record, match or duplicate). The
     script polls the state and reopens the page once the draft continues. --}}
@section('content')
    <div data-student-device="held" data-state-url="{{ route('student-device.state') }}" data-page-url="{{ route('student-device.show') }}">
        @include('student-device._message', ['heading' => __('student_device.held_heading'), 'body' => __('student_device.held_body')])
    </div>
@endsection
