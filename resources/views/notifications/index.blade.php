<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $showArchived ? 'Archived Notifications' : 'Notifications' }}</h2>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    <div class="mb-4 text-sm">
        @if ($showArchived)
            <a href="{{ route('notifications.index') }}" class="font-semibold text-primary hover:underline dark:text-primary-soft">&larr; Back to Notifications</a>
        @else
            <a href="{{ route('notifications.index', ['archived' => 1]) }}" class="font-semibold text-primary hover:underline dark:text-primary-soft">View Archived</a>
        @endif
    </div>

    <div class="space-y-4">
        @forelse ($notifications as $notification)
            @php
                $student = $notification->assessment->student;
            @endphp
            <div class="rounded-lg border {{ $notification->is_read ? 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800' : 'border-primary/30 bg-tint dark:border-primary-soft/30 dark:bg-primary-soft/10' }} p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            @unless ($notification->is_read)
                                <span class="h-2 w-2 rounded-full bg-primary dark:bg-primary-soft"></span>
                            @endunless
                            <p class="text-sm font-semibold text-body dark:text-slate-100">{{ $student->full_name }}</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Student #: {{ $student->student_number }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Assessment Date: {{ $notification->assessment->submitted_at->format('M d, Y g:i A') }}</p>
                    </div>
                    <x-flag-badge :type="$notification->notification_type" />
                </div>

                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">{{ $notification->message }}</p>

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

                    @unless ($showArchived)
                        <form id="archive-notification-form-{{ $notification->id }}" method="POST" action="{{ route('notifications.archive', $notification) }}" class="hidden">
                            @csrf
                            @method('PATCH')
                        </form>
                        <x-secondary-button
                            type="button"
                            @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Archive this notification?', message: 'You can restore it later from View Archived.', confirmLabel: 'Archive', variant: 'primary', formId: 'archive-notification-form-{{ $notification->id }}' })"
                        >
                            Archive
                        </x-secondary-button>
                    @else
                        <form id="unarchive-notification-form-{{ $notification->id }}" method="POST" action="{{ route('notifications.unarchive', $notification) }}" class="hidden">
                            @csrf
                            @method('PATCH')
                        </form>
                        <x-secondary-button
                            type="button"
                            @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Unarchive this notification?', message: 'It will move back to your main Notifications list.', confirmLabel: 'Unarchive', variant: 'primary', formId: 'unarchive-notification-form-{{ $notification->id }}' })"
                        >
                            Unarchive
                        </x-secondary-button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white dark:bg-slate-800 p-12 text-center text-sm text-slate-500 dark:text-slate-400 shadow-sm">
                {{ $showArchived ? 'No archived notifications.' : 'No notifications yet.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>

    <x-confirm-modal />
</x-app-layout>
