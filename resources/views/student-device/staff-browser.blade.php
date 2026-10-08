@extends('layouts.student-device')

@section('content')
    @include('student-device._message', ['heading' => __('student_device.staff_heading'), 'body' => __('student_device.staff_body')])
@endsection
