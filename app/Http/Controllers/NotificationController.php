<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Services\SystemNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Manages a Guidance Counselor's own notification inbox.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly SystemNotificationService $notificationService) {}

    public function index(Request $request): View
    {
        $showArchived = $request->boolean('archived');

        return view('notifications.index', [
            'notifications' => $showArchived
                ? $this->notificationService->paginateArchivedForUser($request->user())
                : $this->notificationService->paginateForUser($request->user()),
            'showArchived' => $showArchived,
        ]);
    }

    /**
     * Mark a notification as read without navigating away.
     */
    public function markAsRead(SystemNotification $notification): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $this->notificationService->markAsRead($notification);

        return back()->with('status', 'Notification marked as read.');
    }

    /**
     * Mark a notification as read and open the full assessment details.
     */
    public function view(SystemNotification $notification): RedirectResponse
    {
        Gate::authorize('view', $notification);

        $this->notificationService->markAsRead($notification);

        return redirect()->route('assessments.show', $notification->assessment_id);
    }

    /**
     * Archive a notification. The row is never deleted, only hidden from
     * the default inbox for accountability purposes.
     */
    public function archive(SystemNotification $notification): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $this->notificationService->archive($notification);

        return back()->with('status', 'Notification archived.');
    }

    /**
     * Unarchive a notification, restoring it to the default inbox.
     */
    public function unarchive(SystemNotification $notification): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $this->notificationService->unarchive($notification);

        return back()->with('status', 'Notification restored.');
    }
}
