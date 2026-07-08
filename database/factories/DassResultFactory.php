<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\DassResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DassResult>
 */
class DassResultFactory extends Factory
{
    protected $model = DassResult::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'depression_raw_score' => 0,
            'anxiety_raw_score' => 0,
            'stress_raw_score' => 0,
            'depression_final_score' => 0,
            'anxiety_final_score' => 0,
            'stress_final_score' => 0,
            'depression_level' => ClassificationThreshold::SEVERITY_NORMAL,
            'anxiety_level' => ClassificationThreshold::SEVERITY_NORMAL,
            'stress_level' => ClassificationThreshold::SEVERITY_NORMAL,
            'ai_provider' => 'rule_based',
            'used_non_official_thresholds' => false,
        ];
    }

    public function withLevels(string $depression, string $anxiety, string $stress): static
    {
        return $this->state(fn (): array => [
            'depression_level' => $depression,
            'anxiety_level' => $anxiety,
            'stress_level' => $stress,
        ]);
    }
}
