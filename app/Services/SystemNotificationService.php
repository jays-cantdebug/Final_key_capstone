<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Manages a user's own system notification inbox (Guidance Counselor
 * notifications about flagged assessments).
 */
class SystemNotificationService
{
    /**
     * Paginate a user's active (non-archived) notifications, most recent first.
     */
    public function paginateForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return SystemNotification::query()
            ->where('user_id', $user->id)
            ->active()
            ->with(['assessment.student', 'assessment.result', 'flaggedCase'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Paginate a user's archived notifications, most recently archived first.
     */
    public function paginateArchivedForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return SystemNotification::query()
            ->where('user_id', $user->id)
            ->archived()
            ->with(['assessment.student', 'assessment.result', 'flaggedCase'])
            ->orderByDesc('archived_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Count a user's unread, active (non-archived) notifications.
     */
    public function unreadCountForUser(User $user): int
    {
        return SystemNotification::query()
            ->where('user_id', $user->id)
            ->active()
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark a notification as read, if it is not already.
     */
    public function markAsRead(SystemNotification $notification): SystemNotification
    {
        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $notification->refresh();
    }

    /**
     * Archive a notification. The underlying row is never deleted — this
     * only hides it from the default inbox view for accountability.
     */
    public function archive(SystemNotification $notification): SystemNotification
    {
        if ($notification->archived_at === null) {
            $notification->update(['archived_at' => now()]);
        }

        return $notification->refresh();
    }

    /**
     * Unarchive a notification, restoring it to the default inbox view.
     */
    public function unarchive(SystemNotification $notification): SystemNotification
    {
        if ($notification->archived_at !== null) {
            $notification->update(['archived_at' => null]);
        }

        return $notification->refresh();
    }
}
