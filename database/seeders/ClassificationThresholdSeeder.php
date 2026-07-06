<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassificationThreshold;
use Illuminate\Database\Seeder;

class ClassificationThresholdSeeder extends Seeder
{
    /**
     * Seed the official, published DASS-21 severity classification cutoffs
     * (final scores, i.e. raw subscale score already multiplied by two).
     */
    public function run(): void
    {
        foreach ($this->officialThresholds() as $threshold) {
            ClassificationThreshold::query()->updateOrCreate(
                [
                    'subscale' => $threshold['subscale'],
                    'severity_level' => $threshold['severity_level'],
                ],
                [
                    'severity_rank' => $threshold['severity_rank'],
                    'min_score' => $threshold['min_score'],
                    'max_score' => $threshold['max_score'],
                ]
            );
        }
    }

    /**
     * @return array<int, array{subscale: string, severity_level: string, severity_rank: int, min_score: int, max_score: int}>
     */
    private function officialThresholds(): array
    {
        return [
            // Depression
            ['subscale' => ClassificationThreshold::SUBSCALE_DEPRESSION, 'severity_level' => ClassificationThreshold::SEVERITY_NORMAL, 'severity_rank' => 0, 'min_score' => 0, 'max_score' => 9],
            ['subscale' => ClassificationThreshold::SUBSCALE_DEPRESSION, 'severity_level' => ClassificationThreshold::SEVERITY_MILD, 'severity_rank' => 1, 'min_score' => 10, 'max_score' => 13],
            ['subscale' => ClassificationThreshold::SUBSCALE_DEPRESSION, 'severity_level' => ClassificationThreshold::SEVERITY_MODERATE, 'severity_rank' => 2, 'min_score' => 14, 'max_score' => 20],
            ['subscale' => ClassificationThreshold::SUBSCALE_DEPRESSION, 'severity_level' => ClassificationThreshold::SEVERITY_SEVERE, 'severity_rank' => 3, 'min_score' => 21, 'max_score' => 27],
            ['subscale' => ClassificationThreshold::SUBSCALE_DEPRESSION, 'severity_level' => ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, 'severity_rank' => 4, 'min_score' => 28, 'max_score' => 42],

            // Anxiety
            ['subscale' => ClassificationThreshold::SUBSCALE_ANXIETY, 'severity_level' => ClassificationThreshold::SEVERITY_NORMAL, 'severity_rank' => 0, 'min_score' => 0, 'max_score' => 7],
            ['subscale' => ClassificationThreshold::SUBSCALE_ANXIETY, 'severity_level' => ClassificationThreshold::SEVERITY_MILD, 'severity_rank' => 1, 'min_score' => 8, 'max_score' => 9],
            ['subscale' => ClassificationThreshold::SUBSCALE_ANXIETY, 'severity_level' => ClassificationThreshold::SEVERITY_MODERATE, 'severity_rank' => 2, 'min_score' => 10, 'max_score' => 14],
            ['subscale' => ClassificationThreshold::SUBSCALE_ANXIETY, 'severity_level' => ClassificationThreshold::SEVERITY_SEVERE, 'severity_rank' => 3, 'min_score' => 15, 'max_score' => 19],
            ['subscale' => ClassificationThreshold::SUBSCALE_ANXIETY, 'severity_level' => ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, 'severity_rank' => 4, 'min_score' => 20, 'max_score' => 42],

            // Stress
            ['subscale' => ClassificationThreshold::SUBSCALE_STRESS, 'severity_level' => ClassificationThreshold::SEVERITY_NORMAL, 'severity_rank' => 0, 'min_score' => 0, 'max_score' => 14],
            ['subscale' => ClassificationThreshold::SUBSCALE_STRESS, 'severity_level' => ClassificationThreshold::SEVERITY_MILD, 'severity_rank' => 1, 'min_score' => 15, 'max_score' => 18],
            ['subscale' => ClassificationThreshold::SUBSCALE_STRESS, 'severity_level' => ClassificationThreshold::SEVERITY_MODERATE, 'severity_rank' => 2, 'min_score' => 19, 'max_score' => 25],
            ['subscale' => ClassificationThreshold::SUBSCALE_STRESS, 'severity_level' => ClassificationThreshold::SEVERITY_SEVERE, 'severity_rank' => 3, 'min_score' => 26, 'max_score' => 33],
            ['subscale' => ClassificationThreshold::SUBSCALE_STRESS, 'severity_level' => ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, 'severity_rank' => 4, 'min_score' => 34, 'max_score' => 42],
        ];
    }
}
