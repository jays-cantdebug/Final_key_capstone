<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\SystemNotification;
use Illuminate\Notifications\Notification;

/**
 * Custom Laravel notification channel that writes into the
 * system_notifications table (our canonical schema) instead of the
 * framework's default polymorphic `notifications` table.
 *
 * Uses updateOrCreate keyed by (user_id, flagged_case_id), matching the
 * unique constraint on system_notifications and satisfying "a unique
 * notification shall exist for each (user, flagged_case) pair."
 */
class SystemNotificationChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSystemNotification')) {
            return;
        }

        $data = $notification->toSystemNotification($notifiable);

        SystemNotification::query()->updateOrCreate(
            [
                'user_id' => $notifiable->id,
                'flagged_case_id' => $data['flagged_case_id'],
            ],
            [
                'assessment_id' => $data['assessment_id'],
                'notification_type' => $data['notification_type'],
                'title' => $data['title'],
                'message' => $data['message'],
            ]
        );
    }
}
