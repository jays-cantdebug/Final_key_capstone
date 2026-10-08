@extends('layouts.student-device')

@section('content')
    @include('student-device._message', ['heading' => __('student_device.unavailable_heading'), 'body' => __('student_device.unavailable_body')])
@endsection
