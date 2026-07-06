@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Notifications</p>
            <h2 class="text-2xl font-semibold text-slate-900">Notifications</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($notifications as $notification)
            @php
                $student = $notification->assessment->student;
                $result = $notification->assessment->result;
            @endphp
            <div class="rounded-3xl border {{ $notification->is_read ? 'border-slate-200 bg-white' : 'border-[#1F6B3A]/30 bg-[#EAF3EC]' }} p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            @unless ($notification->is_read)
                                <span class="h-2 w-2 rounded-full bg-[#1F6B3A]"></span>
                            @endunless
                            <p class="text-sm font-semibold text-slate-900">{{ $student->full_name }}</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Student #: {{ $student->student_number }}</p>
                        <p class="mt-1 text-xs text-slate-500">Assessment Date: {{ $notification->assessment->submitted_at->format('M d, Y g:i A') }}</p>
                    </div>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$result?->overall_status] ?? 'bg-slate-200 text-slate-600' }}">
                        {{ $result?->overall_status ?? 'N/A' }}
                    </span>
                </div>

                <p class="mt-4 text-sm text-slate-600">{{ $notification->message }}</p>

                <div class="mt-4 flex items-center gap-3">
                    <a href="{{ route('notifications.view', $notification) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                        View Assessment
                    </a>

                    @unless ($notification->is_read)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Mark as Read
                            </button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500 shadow-sm">
                No notifications yet.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
</x-app-layout>
