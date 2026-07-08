<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassificationThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassificationThreshold>
 */
class ClassificationThresholdFactory extends Factory
{
    protected $model = ClassificationThreshold::class;

    public function definition(): array
    {
        return [
            'subscale' => ClassificationThreshold::SUBSCALE_DEPRESSION,
            'severity_level' => ClassificationThreshold::SEVERITY_NORMAL,
            'min_score' => 0,
            'max_score' => 9,
        ];
    }
}
