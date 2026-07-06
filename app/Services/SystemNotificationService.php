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
     * Paginate a user's notifications, most recent first.
     */
    public function paginateForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return SystemNotification::query()
            ->where('user_id', $user->id)
            ->with(['assessment.student', 'assessment.result'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Count a user's unread notifications.
     */
    public function unreadCountForUser(User $user): int
    {
        return SystemNotification::query()
            ->where('user_id', $user->id)
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
}
