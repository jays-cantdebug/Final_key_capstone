<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Notifications</p>
            <h2 class="text-2xl font-semibold text-body">Notifications</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="space-y-4">
        @forelse ($notifications as $notification)
            @php
                $student = $notification->assessment->student;
            @endphp
            <div class="rounded-3xl border {{ $notification->is_read ? 'border-slate-200 bg-white' : 'border-primary/30 bg-tint' }} p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            @unless ($notification->is_read)
                                <span class="h-2 w-2 rounded-full bg-primary"></span>
                            @endunless
                            <p class="text-sm font-semibold text-body">{{ $student->full_name }}</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Student #: {{ $student->student_number }}</p>
                        <p class="mt-1 text-xs text-slate-500">Assessment Date: {{ $notification->assessment->submitted_at->format('M d, Y g:i A') }}</p>
                    </div>
                    <x-flag-badge :type="$notification->notification_type" />
                </div>

                <p class="mt-4 text-sm text-slate-600">{{ $notification->message }}</p>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button :href="route('notifications.view', $notification)">
                        View Assessment
                    </x-primary-button>

                    @unless ($notification->is_read)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <x-secondary-button type="submit">
                                Mark as Read
                            </x-secondary-button>
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
