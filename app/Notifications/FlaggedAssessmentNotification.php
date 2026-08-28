<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FlaggedCase;
use App\Models\SystemNotification;
use App\Notifications\Channels\SystemNotificationChannel;
use Illuminate\Notifications\Notification;

/**
 * Notifies a Guidance Counselor of a newly-created Flagged Case: either a
 * Counseling Endorsement (severe stress) or an Awareness Notification
 * (severe depression and/or anxiety).
 */
class FlaggedAssessmentNotification extends Notification
{
    public function __construct(private readonly FlaggedCase $flaggedCase) {}

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
     * @return array{assessment_id: int, flagged_case_id: int, notification_type: string, title: string, message: string}
     */
    public function toSystemNotification(mixed $notifiable): array
    {
        $assessment = $this->flaggedCase->assessment;
        $student = $assessment->student;
        $result = $assessment->result;

        $isEndorsement = $this->flaggedCase->flag_type === FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT;

        $severityBySubscale = [
            FlaggedCase::SUBSCALE_DEPRESSION => $result->depression_level,
            FlaggedCase::SUBSCALE_ANXIETY => $result->anxiety_level,
            FlaggedCase::SUBSCALE_STRESS => $result->stress_level,
        ];

        return [
            'assessment_id' => $assessment->id,
            'flagged_case_id' => $this->flaggedCase->id,
            'notification_type' => $isEndorsement
                ? SystemNotification::TYPE_COUNSELING_ENDORSEMENT
                : SystemNotification::TYPE_AWARENESS_NOTIFICATION,
            'title' => $isEndorsement ? 'Counseling Endorsement' : 'Awareness Notification',
            'message' => sprintf(
                '%s (%s) was assessed with %s %s on %s.',
                $student->full_name,
                $student->student_number,
                $severityBySubscale[$this->flaggedCase->triggering_subscale],
                ucfirst($this->flaggedCase->triggering_subscale),
                $assessment->submitted_at->format('M d, Y')
            ),
        ];
    }
}
