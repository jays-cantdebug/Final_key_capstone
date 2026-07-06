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
 * Uses updateOrCreate keyed by (user_id, assessment_id), matching the
 * unique constraint on system_notifications and satisfying "duplicate
 * notifications shall not be generated for the same assessment."
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
                'assessment_id' => $data['assessment_id'],
            ],
            [
                'title' => $data['title'],
                'message' => $data['message'],
            ]
        );
    }
}
