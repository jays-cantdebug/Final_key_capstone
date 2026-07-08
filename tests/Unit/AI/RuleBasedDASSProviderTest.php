<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\DTOs\AIClassificationResult;
use App\AI\DTOs\AssessmentPayload;
use App\AI\Providers\RuleBasedDASSProvider;
use App\Models\ClassificationThreshold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class RuleBasedDASSProviderTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOfficialThresholds();
    }

    /**
     * @dataProvider depressionBoundaries
     */
    public function test_classifies_depression_boundaries(int $score, string $expectedLevel): void
    {
        $result = $this->classify(depression: $score);

        $this->assertSame($expectedLevel, $result->depressionLevel);
    }

    public static function depressionBoundaries(): array
    {
        return [
            'Normal upper bound' => [9, ClassificationThreshold::SEVERITY_NORMAL],
            'Mild lower bound' => [10, ClassificationThreshold::SEVERITY_MILD],
            'Moderate lower bound' => [14, ClassificationThreshold::SEVERITY_MODERATE],
            'Severe lower bound' => [21, ClassificationThreshold::SEVERITY_SEVERE],
            'Extremely Severe lower bound' => [28, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE],
            'Extremely Severe upper bound' => [42, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE],
        ];
    }

    public function test_classifies_anxiety_and_stress_independently_of_each_other(): void
    {
        // Anxiety Severe (15-19) but Stress Normal (0-14) at the same time.
        $result = $this->classify(anxiety: 15, stress: 14);

        $this->assertSame(ClassificationThreshold::SEVERITY_SEVERE, $result->anxietyLevel);
        $this->assertSame(ClassificationThreshold::SEVERITY_NORMAL, $result->stressLevel);
    }

    public function test_provider_name_is_recorded_on_the_result(): void
    {
        $result = $this->classify();

        $this->assertSame('rule_based', $result->provider);
    }

    public function test_throws_when_no_threshold_covers_the_given_score(): void
    {
        ClassificationThreshold::query()->where('subscale', ClassificationThreshold::SUBSCALE_DEPRESSION)->delete();

        $this->expectException(RuntimeException::class);

        $this->classify(depression: 5);
    }

    private function classify(int $depression = 0, int $anxiety = 0, int $stress = 0): AIClassificationResult
    {
        return (new RuleBasedDASSProvider)->classify(new AssessmentPayload(
            assessmentId: 1,
            depressionFinalScore: $depression,
            anxietyFinalScore: $anxiety,
            stressFinalScore: $stress,
        ));
    }
}
