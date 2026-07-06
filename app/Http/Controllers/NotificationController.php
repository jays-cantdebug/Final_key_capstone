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
    public function __construct(private readonly SystemNotificationService $notificationService)
    {
    }

    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $this->notificationService->paginateForUser($request->user()),
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
}
