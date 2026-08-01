<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\DTOs\AIClassificationResult;
use App\AI\DTOs\AssessmentPayload;
use App\AI\Providers\ClaudeAIProvider;
use App\AI\Providers\RuleBasedDASSProvider;
use App\Models\ClassificationThreshold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class ClaudeAIProviderTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOfficialThresholds();
    }

    public function test_uses_the_claude_result_when_it_agrees_with_the_rule_based_classification(): void
    {
        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Severe',
                'anxiety_level' => 'Moderate',
                'stress_level' => 'Extremely Severe',
            ])),
        ]);

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('Severe', $result->depressionLevel);
        $this->assertSame('Moderate', $result->anxietyLevel);
        $this->assertSame('Extremely Severe', $result->stressLevel);
        $this->assertSame('claude', $result->provider);
    }

    public function test_falls_back_to_rule_based_when_claude_disagrees(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $message) => str_contains($message, 'disagreed')
        );

        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Moderate', // wrong: the deterministic answer is Severe
                'anxiety_level' => 'Moderate',
                'stress_level' => 'Extremely Severe',
            ])),
        ]);

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('Severe', $result->depressionLevel);
        $this->assertSame('Moderate', $result->anxietyLevel);
        $this->assertSame('Extremely Severe', $result->stressLevel);
        $this->assertSame('rule_based', $result->provider);
    }

    public function test_falls_back_to_rule_based_when_claude_returns_an_unknown_severity_value(): void
    {
        Log::shouldReceive('warning')->once();

        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Critical', // not one of the 5 official tiers
                'anxiety_level' => 'Moderate',
                'stress_level' => 'Extremely Severe',
            ])),
        ]);

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('rule_based', $result->provider);
        $this->assertSame('Severe', $result->depressionLevel);
    }

    public function test_falls_back_to_rule_based_when_the_response_has_no_tool_use_block(): void
    {
        Log::shouldReceive('warning')->once();

        Http::fake([
            '*' => Http::response(['content' => [['type' => 'text', 'text' => 'I cannot comply.']]]),
        ]);

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('rule_based', $result->provider);
        $this->assertSame('Severe', $result->depressionLevel);
    }

    public function test_falls_back_to_rule_based_when_the_http_request_fails(): void
    {
        Log::shouldReceive('warning')->once();

        Http::fake([
            '*' => Http::response(['error' => 'server error'], 500),
        ]);

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('rule_based', $result->provider);
        $this->assertSame('Severe', $result->depressionLevel);
    }

    public function test_never_throws_even_when_the_request_connection_fails(): void
    {
        Log::shouldReceive('warning')->once();

        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('rule_based', $result->provider);
    }

    public function test_sends_thresholds_pulled_from_the_database_rather_than_hardcoded_values(): void
    {
        // Override Depression's Severe row to a clearly non-official range,
        // proving the outgoing payload reflects the live table, not a
        // hardcoded copy of the published DASS-21 cutoffs.
        ClassificationThreshold::query()
            ->where('subscale', ClassificationThreshold::SUBSCALE_DEPRESSION)
            ->where('severity_level', ClassificationThreshold::SEVERITY_SEVERE)
            ->update(['min_score' => 99, 'max_score' => 100]);

        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Normal',
                'anxiety_level' => 'Normal',
                'stress_level' => 'Normal',
            ])),
        ]);

        $this->classify(depression: 0, anxiety: 0, stress: 0);

        Http::assertSent(function ($request) {
            $body = json_decode($request['messages'][0]['content'], true);

            $depression = $body['official_thresholds']['depression'];

            $this->assertSame([99, 100], $depression['severe']);
            // The top tier's upper bound must be reported as unbounded (null),
            // matching official DASS-21 semantics, not the table's numeric cap.
            $this->assertSame(0, $depression['normal'][0]);
            $this->assertNull($depression['extremely_severe'][1]);

            $this->assertSame('classify_dass_subscales', $request['tools'][0]['name']);
            $this->assertSame(
                ['type' => 'tool', 'name' => 'classify_dass_subscales'],
                $request['tool_choice']
            );
            $this->assertTrue($request->hasHeader('x-api-key'));

            return true;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function toolUseResponse(array $input): array
    {
        return [
            'content' => [
                [
                    'type' => 'tool_use',
                    'name' => 'classify_dass_subscales',
                    'input' => $input,
                ],
            ],
        ];
    }

    private function classify(int $depression, int $anxiety, int $stress): AIClassificationResult
    {
        return (new ClaudeAIProvider(new RuleBasedDASSProvider))->classify(new AssessmentPayload(
            assessmentId: 1,
            depressionFinalScore: $depression,
            anxietyFinalScore: $anxiety,
            stressFinalScore: $stress,
        ));
    }
}
