<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DassScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class DassScoringServiceTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_sums_raw_responses_per_subscale_independently(): void
    {
        $version = $this->createActiveQuestionnaireVersion();
        $responses = $this->buildResponses($version, depressionRaw: 5, anxietyRaw: 3, stressRaw: 7);

        $scores = (new DassScoringService)->score($version, $responses);

        $this->assertSame(5, $scores['depression_raw_score']);
        $this->assertSame(3, $scores['anxiety_raw_score']);
        $this->assertSame(7, $scores['stress_raw_score']);
    }

    public function test_final_score_is_always_double_the_raw_score(): void
    {
        $version = $this->createActiveQuestionnaireVersion();
        $responses = $this->buildResponses($version, depressionRaw: 5, anxietyRaw: 3, stressRaw: 7);

        $scores = (new DassScoringService)->score($version, $responses);

        $this->assertSame(10, $scores['depression_final_score']);
        $this->assertSame(6, $scores['anxiety_final_score']);
        $this->assertSame(14, $scores['stress_final_score']);
    }

    public function test_all_zero_responses_yield_zero_for_every_subscale(): void
    {
        $version = $this->createActiveQuestionnaireVersion();
        $responses = $this->buildResponses($version, depressionRaw: 0, anxietyRaw: 0, stressRaw: 0);

        $scores = (new DassScoringService)->score($version, $responses);

        $this->assertSame(0, $scores['depression_raw_score']);
        $this->assertSame(0, $scores['anxiety_raw_score']);
        $this->assertSame(0, $scores['stress_raw_score']);
        $this->assertSame(0, $scores['depression_final_score']);
    }

    public function test_maximum_responses_yield_the_maximum_possible_score(): void
    {
        $version = $this->createActiveQuestionnaireVersion(perSubscale: 7);
        $responses = $this->buildResponses($version, depressionRaw: 21, anxietyRaw: 21, stressRaw: 21);

        $scores = (new DassScoringService)->score($version, $responses);

        $this->assertSame(21, $scores['depression_raw_score']);
        $this->assertSame(42, $scores['depression_final_score']);
        $this->assertSame(42, $scores['stress_final_score']);
    }

    public function test_missing_response_for_a_question_throws(): void
    {
        $version = $this->createActiveQuestionnaireVersion();
        $responses = $this->buildResponses($version, depressionRaw: 5, anxietyRaw: 3, stressRaw: 7);

        $firstQuestionId = array_key_first($responses);
        unset($responses[$firstQuestionId]);

        $this->expectException(\RuntimeException::class);

        (new DassScoringService)->score($version, $responses);
    }
}
