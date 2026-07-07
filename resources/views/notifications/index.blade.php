@php
    $notificationTypeBadgeClasses = [
        'counseling_endorsement' => 'bg-[#E3F4F1] text-[#0F5C50]',
        'awareness_notification' => 'bg-[#F1E9FB] text-[#4A1E82]',
    ];

    $notificationTypeLabels = [
        'counseling_endorsement' => 'Counseling Endorsement',
        'awareness_notification' => 'Awareness Notification',
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
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $notificationTypeBadgeClasses[$notification->notification_type] ?? 'bg-slate-200 text-slate-600' }}">
                        {{ $notificationTypeLabels[$notification->notification_type] ?? $notification->notification_type }}
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
