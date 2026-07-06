<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SystemNotification;
use App\Models\User;

class SystemNotificationPolicy
{
    /**
     * Determine whether the user may view (and thereby mark as read) the
     * given notification.
     */
    public function view(User $user, SystemNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    /**
     * Determine whether the user may mark the given notification as read.
     */
    public function update(User $user, SystemNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
