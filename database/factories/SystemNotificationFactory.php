<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\FlaggedCase;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemNotification>
 */
class SystemNotificationFactory extends Factory
{
    protected $model = SystemNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->guidanceCounselor(),
            'assessment_id' => Assessment::factory(),
            'flagged_case_id' => FlaggedCase::factory(),
            'notification_type' => SystemNotification::TYPE_AWARENESS_NOTIFICATION,
            'title' => 'Awareness Notification',
            'message' => $this->faker->sentence(),
            'is_read' => false,
            'read_at' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the notification has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'archived_at' => now(),
        ]);
    }
}
