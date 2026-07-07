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
     *
     * The values themselves live in ClassificationThreshold::officialValues()
     * — the single source of truth also used by the Override Mode feature
     * to detect drift and to power "Restore Official Values".
     */
    public function run(): void
    {
        foreach (ClassificationThreshold::officialValues() as $threshold) {
            ClassificationThreshold::query()->updateOrCreate(
                [
                    'subscale' => $threshold['subscale'],
                    'severity_level' => $threshold['severity_level'],
                ],
                [
                    'min_score' => $threshold['min_score'],
                    'max_score' => $threshold['max_score'],
                ]
            );
        }
    }
}
