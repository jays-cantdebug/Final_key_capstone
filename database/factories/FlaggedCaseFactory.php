<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\FlaggedCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlaggedCase>
 */
class FlaggedCaseFactory extends Factory
{
    protected $model = FlaggedCase::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
            'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION,
            'status' => FlaggedCase::STATUS_OPEN,
            'flagged_at' => now(),
        ];
    }

    public function endorsement(): static
    {
        return $this->state(fn (): array => [
            'flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT,
            'triggering_subscale' => FlaggedCase::SUBSCALE_STRESS,
        ]);
    }
}
