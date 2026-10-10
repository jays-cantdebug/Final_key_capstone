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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class ClaudeAIProviderTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    /**
     * Every line of the approved plain-text system prompt (commit f4e0d12),
     * in order. The JSON prompt must carry exactly these, unchanged.
     */
    private const ORIGINAL_PROMPT_LINES = [
        'You are a strict classification lookup engine for a DASS-21 (Depression, Anxiety, Stress Scale) mental health assessment system.',
        'Background (context only; it does not change your task):',
        '- The DASS-21 is a 21-item self-report questionnaire with 7 items for each of three subscales: Depression, Anxiety and Stress. Each item is answered on a 0-3 scale.',
        '- A subscale\'s score is the sum of its 7 answers multiplied by 2, giving a final score from 0 to 42.',
        '- The scores in the user message are already these final, doubled scores. Use them exactly as given; do not halve, double or otherwise recompute them.',
        '- The five severity tiers, from least to most severe, are: Normal, Mild, Moderate, Severe, Extremely Severe.',
        '- The DASS-21 is a screening instrument, not a diagnosis. Your classification is a screening result that a qualified professional reviews.',
        'Your ONLY task is to classify three subscale scores (depression, anxiety, stress) into their official severity tier by looking up which range in the "official_thresholds" object of the user\'s message contains each score. A score belongs to a tier when it falls within that tier\'s inclusive [min, max] range; a null max means the range is unbounded upward.',
        'Rules you must follow exactly:',
        '- Use ONLY the threshold ranges provided in the user message. Do not use any outside knowledge of DASS-21 cutoffs, even if it seems to conflict with the provided ranges.',
        '- Do not guess, estimate, round, or reason clinically about the scores. This is a literal lookup, not a clinical judgment.',
        '- The keys in "official_thresholds" use snake_case tier names (e.g. "extremely_severe"). Report your classification using the Title Case form of that same tier name (e.g. "Extremely Severe").',
        '- You must report your classification by calling the classify_dass_subscales tool. Do not respond with any other text.',
    ];

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

    public function test_system_prompt_is_json_carrying_every_original_line_unchanged(): void
    {
        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Normal',
                'anxiety_level' => 'Normal',
                'stress_level' => 'Normal',
            ])),
        ]);

        $this->classify(depression: 0, anxiety: 0, stress: 0);

        Http::assertSent(function ($request) {
            $decoded = json_decode($request['system'], true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(['role', 'background', 'task', 'rules'], array_keys($decoded));

            // Each original line appears verbatim as a value...
            $values = [];
            array_walk_recursive($decoded, function (mixed $value) use (&$values): void {
                $values[] = $value;
            });
            foreach (self::ORIGINAL_PROMPT_LINES as $line) {
                $this->assertContains($line, $values);
            }
            // ...in the original order, with nothing added.
            $this->assertSame(self::ORIGINAL_PROMPT_LINES, $values);

            return true;
        });
    }

    public function test_system_prompt_gives_dass21_background_but_no_cutoff_numbers(): void
    {
        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Normal',
                'anxiety_level' => 'Normal',
                'stress_level' => 'Normal',
            ])),
        ]);

        $this->classify(depression: 0, anxiety: 0, stress: 0);

        Http::assertSent(function ($request) {
            // Phrase checks run on the decoded text (the raw JSON escapes
            // quotes); the number checks below run on the raw JSON, so they
            // also cover the keys and anything else sent.
            $raw = $request['system'];
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $prompt = implode("\n", array_merge(
                [$decoded['role']], $decoded['background'], [$decoded['task']], $decoded['rules']
            ));

            // The DASS-21 background context.
            foreach ([
                '21-item',
                '7 items for each of three subscales: Depression, Anxiety and Stress',
                '0-3 scale',
                'multiplied by 2',
                'final score from 0 to 42',
                'already these final, doubled scores',
                'do not halve, double or otherwise recompute them',
                'Normal, Mild, Moderate, Severe, Extremely Severe',
                'screening instrument, not a diagnosis',
            ] as $expected) {
                $this->assertStringContainsString($expected, $prompt);
            }

            // The safety rules are unchanged.
            $this->assertStringContainsString('Use ONLY the threshold ranges provided in the user message.', $prompt);
            $this->assertStringContainsString('This is a literal lookup, not a clinical judgment.', $prompt);

            // No cutoffs: every number must be structural (21 items, 7 per
            // subscale, 0-3 answers, x2, 0-42 range). Several of these
            // coincide with real cutoffs (e.g. Anxiety Normal ends at 7,
            // Depression Severe starts at 21), so instead of banning all
            // threshold values, allow only these and ban every other one.
            $structural = [0, 2, 3, 7, 21, 42];
            preg_match_all('/\d+/', $raw, $numbers);
            $this->assertSame([], array_values(array_diff(array_map('intval', $numbers[0]), $structural)));

            $cutoffs = collect(ClassificationThreshold::officialValues())
                ->flatMap(fn (array $row) => [$row['min_score'], $row['max_score']])
                ->unique()
                ->diff($structural);
            $this->assertNotEmpty($cutoffs);
            foreach ($cutoffs as $cutoff) {
                $this->assertDoesNotMatchRegularExpression('/\b'.$cutoff.'\b/', $raw);
            }

            // No range expressions other than the two structural ones.
            preg_match_all('/\d+\s*(?:-|–|to)\s*\d+|\[\s*\d+\s*,\s*\d+\s*\]/', $raw, $ranges);
            $this->assertSame(['0-3', '0 to 42'], $ranges[0]);

            return true;
        });
    }

    public function test_falls_back_to_rule_based_without_throwing_when_the_request_times_out(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Claude AI classification failed; falling back to rule-based classification.'
                && str_contains($context['error'], 'cURL error 28'));

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 12001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.anthropic.com/v1/messages');
        });

        $result = $this->classify(depression: 25, anxiety: 12, stress: 40);

        $this->assertSame('rule_based', $result->provider);
        $this->assertSame('Severe', $result->depressionLevel);
        $this->assertSame('Moderate', $result->anxietyLevel);
        $this->assertSame('Extremely Severe', $result->stressLevel);
    }

    public function test_passes_the_configured_timeouts_to_the_http_client(): void
    {
        config([
            'ai.providers.claude.timeout' => '9',
            'ai.providers.claude.connect_timeout' => '3',
        ]);

        $this->assertSame(['timeout' => 9.0, 'connect_timeout' => 3.0], $this->sentTimeouts());
    }

    public function test_uses_12_and_4_seconds_when_the_timeouts_are_missing_or_blank(): void
    {
        config(['ai.providers.claude' => Arr::except(config('ai.providers.claude'), ['timeout', 'connect_timeout'])]);
        $this->assertSame(['timeout' => 12.0, 'connect_timeout' => 4.0], $this->sentTimeouts());

        // A blank CLAUDE_TIMEOUT= in .env must not become a 0 (or 1) second timeout.
        config([
            'ai.providers.claude.timeout' => '',
            'ai.providers.claude.connect_timeout' => 'abc',
        ]);
        $this->assertSame(['timeout' => 12.0, 'connect_timeout' => 4.0], $this->sentTimeouts());
    }

    public function test_a_total_timeout_above_the_cap_is_reduced_to_20_seconds(): void
    {
        config([
            'ai.providers.claude.timeout' => '45',
            'ai.providers.claude.connect_timeout' => '30',
        ]);

        $this->assertSame(['timeout' => 20.0, 'connect_timeout' => 20.0], $this->sentTimeouts());
    }

    /**
     * Runs one classification and returns the timeout options the request
     * actually reached Guzzle with (a global middleware sits outside the
     * fake's stub handler, so it sees the real options).
     *
     * @return array{timeout: float, connect_timeout: float}
     */
    private function sentTimeouts(): array
    {
        $sent = [];

        Http::globalMiddleware(function (callable $handler) use (&$sent): callable {
            return function ($request, array $options) use ($handler, &$sent) {
                $sent = ['timeout' => $options['timeout'], 'connect_timeout' => $options['connect_timeout']];

                return $handler($request, $options);
            };
        });

        Http::fake([
            '*' => Http::response($this->toolUseResponse([
                'depression_level' => 'Severe',
                'anxiety_level' => 'Moderate',
                'stress_level' => 'Extremely Severe',
            ])),
        ]);

        $this->assertSame('claude', $this->classify(depression: 25, anxiety: 12, stress: 40)->provider);

        return $sent;
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
