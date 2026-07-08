<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionnaireVersion>
 */
class QuestionnaireVersionFactory extends Factory
{
    protected $model = QuestionnaireVersion::class;

    public function definition(): array
    {
        return [
            'questionnaire_id' => Questionnaire::factory(),
            'version_number' => 1,
            'status' => QuestionnaireVersion::STATUS_DRAFT,
            'effective_date' => now()->toDateString(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => QuestionnaireVersion::STATUS_ACTIVE]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => QuestionnaireVersion::STATUS_ARCHIVED]);
    }
}
