<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\Seeder;

class QuestionnaireVersionSeeder extends Seeder
{
    /**
     * Seed the default active version (v1) of the DASS-21 questionnaire.
     */
    public function run(): void
    {
        $questionnaire = Questionnaire::query()->where('title', 'DASS-21')->firstOrFail();

        QuestionnaireVersion::query()->updateOrCreate(
            [
                'questionnaire_id' => $questionnaire->id,
                'version_number' => 1,
            ],
            [
                'status' => QuestionnaireVersion::STATUS_ACTIVE,
                'effective_date' => now()->toDateString(),
            ]
        );
    }
}
