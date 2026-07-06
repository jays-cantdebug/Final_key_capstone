<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

class QuestionnaireSeeder extends Seeder
{
    /**
     * Seed the canonical DASS-21 questionnaire template.
     */
    public function run(): void
    {
        Questionnaire::query()->updateOrCreate(
            ['title' => 'DASS-21'],
            [
                'description' => 'Official 21-item Depression, Anxiety and Stress Scale (DASS-21) assessment questionnaire.',
                'status' => Questionnaire::STATUS_ACTIVE,
            ]
        );
    }
}
