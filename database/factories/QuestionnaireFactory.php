<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Questionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Questionnaire>
 */
class QuestionnaireFactory extends Factory
{
    protected $model = Questionnaire::class;

    public function definition(): array
    {
        return [
            'title' => 'DASS-21 Student Assessment',
            'description' => 'Standard 21-item Depression, Anxiety and Stress Scale.',
            'status' => Questionnaire::STATUS_ACTIVE,
        ];
    }
}
