@extends('layouts.student-device')

{{-- Locked after Done: the message only, no answers. The script polls the
     state so the page reopens for editing only if staff return it. --}}
@section('content')
    <div data-student-device="locked" data-state-url="{{ route('student-device.state') }}" data-page-url="{{ route('student-device.show') }}">
        @include('student-device._message', ['heading' => __('student_device.thanks_heading'), 'body' => __('student_device.thanks_body')])
    </div>
@endsection
