<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DassQuestion;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DassQuestion>
 */
class DassQuestionFactory extends Factory
{
    protected $model = DassQuestion::class;

    public function definition(): array
    {
        return [
            'questionnaire_version_id' => QuestionnaireVersion::factory(),
            'item_number' => $this->faker->unique()->numberBetween(1, 21),
            'question_text' => $this->faker->sentence(),
            'question_type' => DassQuestion::TYPE_LIKERT_SCALE,
            'subscale' => DassQuestion::SUBSCALE_DEPRESSION,
            'display_order' => 1,
            'is_required' => true,
        ];
    }

    public function depression(): static
    {
        return $this->state(fn (): array => ['subscale' => DassQuestion::SUBSCALE_DEPRESSION]);
    }

    public function anxiety(): static
    {
        return $this->state(fn (): array => ['subscale' => DassQuestion::SUBSCALE_ANXIETY]);
    }

    public function stress(): static
    {
        return $this->state(fn (): array => ['subscale' => DassQuestion::SUBSCALE_STRESS]);
    }
}
