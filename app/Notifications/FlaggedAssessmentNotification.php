<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Assessment;
use App\Notifications\Channels\SystemNotificationChannel;
use Illuminate\Notifications\Notification;

/**
 * Notifies a Guidance Counselor that a newly-submitted assessment met or
 * exceeded the Notification Severity Threshold.
 */
class FlaggedAssessmentNotification extends Notification
{
    public function __construct(private readonly Assessment $assessment)
    {
    }

    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [SystemNotificationChannel::class];
    }

    /**
     * Build the data persisted by SystemNotificationChannel.
     *
     * @return array{assessment_id: int, title: string, message: string}
     */
    public function toSystemNotification(mixed $notifiable): array
    {
        $student = $this->assessment->student;
        $result = $this->assessment->result;

        return [
            'assessment_id' => $this->assessment->id,
            'title' => 'New Flagged Assessment',
            'message' => sprintf(
                '%s (%s) was assessed with a highest severity of %s on %s.',
                $student->full_name,
                $student->student_number,
                $result->overall_status,
                $this->assessment->submitted_at->format('M d, Y')
            ),
        ];
    }
}
