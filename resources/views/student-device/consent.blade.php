@extends('layouts.student-device')

{{-- The approved exception to "questionnaire only": the student's own
     acknowledgment of the privacy notice, before the first question. The
     text is a placeholder (lang/en/student_device.php). --}}
@section('content')
    <div class="mx-auto mt-10 max-w-xl rounded-lg border border-slate-200 bg-white px-6 py-8 shadow-sm">
        <h1 class="text-xl font-semibold text-body">{{ __('student_device.consent_heading') }}</h1>
        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gold">{{ __('student_device.consent_placeholder_marker') }}</p>

        <div class="mt-4 space-y-3 text-sm leading-relaxed text-slate-700">
            @foreach (__('student_device.consent_paragraphs') as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row-reverse">
            <form method="POST" action="{{ route('student-device.consent') }}" class="sm:flex-1">
                <button type="submit" class="w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    {{ __('student_device.consent_accept') }}
                </button>
            </form>
            <form method="POST" action="{{ route('student-device.decline') }}" class="sm:flex-1">
                <button type="submit" class="w-full rounded-md border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    {{ __('student_device.consent_decline') }}
                </button>
            </form>
        </div>
    </div>
@endsection
