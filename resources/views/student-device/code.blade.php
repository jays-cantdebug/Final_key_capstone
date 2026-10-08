@extends('layouts.student-device')

@section('content')
    <form method="POST" action="{{ route('student-device.code') }}" class="mx-auto mt-16 max-w-md rounded-lg border border-slate-200 bg-white px-6 py-8 shadow-sm">
        <h1 class="text-xl font-semibold text-body">{{ __('student_device.code_heading') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('student_device.code_help') }}</p>

        <label for="code" class="mt-6 block text-sm font-medium text-slate-700">{{ __('student_device.code_label') }}</label>
        <input
            id="code"
            name="code"
            type="text"
            inputmode="text"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            maxlength="12"
            required
            autofocus
            class="mt-1 block w-full rounded-md border-slate-300 text-center font-mono text-2xl uppercase tracking-[0.3em] shadow-sm focus:border-primary focus:ring-primary"
        >

        <button type="submit" class="mt-6 w-full rounded-md bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            {{ __('student_device.code_submit') }}
        </button>
    </form>
@endsection
