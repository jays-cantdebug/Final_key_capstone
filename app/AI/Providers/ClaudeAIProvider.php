<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\DTOs\AIClassificationResult;
use App\AI\DTOs\AssessmentPayload;
use App\Models\ClassificationThreshold;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Claude API-backed AI provider.
 *
 * Sends the three DASS-21 subscale scores and the official
 * classification_thresholds (pulled fresh from the database, never
 * hardcoded) to Claude as structured JSON, forcing a tool-use response
 * so the reply is schema-guaranteed rather than merely prompted for.
 *
 * Because a threshold lookup is deterministic, every response is
 * cross-checked against RuleBasedDASSProvider's own calculation for the
 * same input before it is trusted. Agreement -> the Claude result is
 * used (provider recorded as "claude"). Any disagreement, malformed
 * response, or request failure -> the discrepancy is logged and the
 * rule-based result is returned instead (provider recorded as
 * "rule_based"), so `dass_results.ai_provider` always truthfully
 * reflects what was actually saved and an incorrect severity tier is
 * never persisted.
 */
class ClaudeAIProvider implements AIProviderInterface
{
    private const PROVIDER_NAME = 'claude';

    private const TOOL_NAME = 'classify_dass_subscales';

    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(private readonly RuleBasedDASSProvider $ruleBasedProvider) {}

    public function classify(AssessmentPayload $payload): AIClassificationResult
    {
        $ruleBasedResult = $this->ruleBasedProvider->classify($payload);

        try {
            $aiResult = $this->classifyViaClaude($payload);

            if ($this->agree($aiResult, $ruleBasedResult)) {
                return $aiResult;
            }

            Log::warning('Claude AI classification disagreed with the deterministic rule-based result; falling back to rule-based.', [
                'assessment_id' => $payload->assessmentId,
                'scores' => [
                    'depression' => $payload->depressionFinalScore,
                    'anxiety' => $payload->anxietyFinalScore,
                    'stress' => $payload->stressFinalScore,
                ],
                'claude_result' => [
                    'depression_level' => $aiResult->depressionLevel,
                    'anxiety_level' => $aiResult->anxietyLevel,
                    'stress_level' => $aiResult->stressLevel,
                ],
                'rule_based_result' => [
                    'depression_level' => $ruleBasedResult->depressionLevel,
                    'anxiety_level' => $ruleBasedResult->anxietyLevel,
                    'stress_level' => $ruleBasedResult->stressLevel,
                ],
            ]);

            return $ruleBasedResult;
        } catch (Throwable $e) {
            Log::warning('Claude AI classification failed; falling back to rule-based classification.', [
                'assessment_id' => $payload->assessmentId,
                'error' => $e->getMessage(),
            ]);

            return $ruleBasedResult;
        }
    }

    private function classifyViaClaude(AssessmentPayload $payload): AIClassificationResult
    {
        $response = Http::withHeaders([
            'x-api-key' => (string) config('ai.providers.claude.api_key'),
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ])
            ->timeout(30)
            ->post((string) config('ai.providers.claude.api_url'), [
                'model' => config('ai.providers.claude.model'),
                'max_tokens' => 512,
                'system' => $this->systemPrompt(),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'assessment' => [
                                'depression_score' => $payload->depressionFinalScore,
                                'anxiety_score' => $payload->anxietyFinalScore,
                                'stress_score' => $payload->stressFinalScore,
                            ],
                            'official_thresholds' => $this->buildOfficialThresholds(),
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
                'tools' => [$this->classificationTool()],
                'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf('Claude API request failed with status %d.', $response->status()));
        }

        $toolInput = $this->extractToolInput($response->json('content', []));

        if ($toolInput === null) {
            throw new RuntimeException('Claude response did not include a valid classify_dass_subscales tool call.');
        }

        return new AIClassificationResult(
            depressionLevel: $toolInput['depression_level'],
            anxietyLevel: $toolInput['anxiety_level'],
            stressLevel: $toolInput['stress_level'],
            provider: self::PROVIDER_NAME,
        );
    }

    /**
     * Build the official_thresholds payload straight from the
     * classification_thresholds table — never hardcoded. The top severity
     * tier's max is reported as null (unbounded), matching the officially
     * published, open-ended DASS-21 semantics, even though the table
     * stores a practical numeric cap for range-query purposes.
     *
     * @return array<string, array<string, array{0: int, 1: int|null}>>
     */
    private function buildOfficialThresholds(): array
    {
        $topSeverityLevel = array_reverse(ClassificationThreshold::severityOrder())[0];

        $thresholds = [];

        foreach (ClassificationThreshold::all() as $threshold) {
            $subscaleKey = strtolower($threshold->subscale);
            $tierKey = strtolower(str_replace(' ', '_', $threshold->severity_level));

            $thresholds[$subscaleKey][$tierKey] = [
                $threshold->min_score,
                $threshold->severity_level === $topSeverityLevel ? null : $threshold->max_score,
            ];
        }

        return $thresholds;
    }

    /**
     * @return array{name: string, description: string, input_schema: array}
     */
    private function classificationTool(): array
    {
        $levelSchema = [
            'type' => 'string',
            'enum' => ClassificationThreshold::severityOrder(),
        ];

        return [
            'name' => self::TOOL_NAME,
            'description' => 'Report the classified DASS-21 severity tier for each subscale, determined strictly by looking up each score against the official_thresholds ranges provided in the user message.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'depression_level' => $levelSchema,
                    'anxiety_level' => $levelSchema,
                    'stress_level' => $levelSchema,
                ],
                'required' => ['depression_level', 'anxiety_level', 'stress_level'],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a strict classification lookup engine for a DASS-21 (Depression, Anxiety, Stress Scale) mental health assessment system.

            Your ONLY task is to classify three subscale scores (depression, anxiety, stress) into their official severity tier by looking up which range in the "official_thresholds" object of the user's message contains each score. A score belongs to a tier when it falls within that tier's inclusive [min, max] range; a null max means the range is unbounded upward.

            Rules you must follow exactly:
            - Use ONLY the threshold ranges provided in the user message. Do not use any outside knowledge of DASS-21 cutoffs, even if it seems to conflict with the provided ranges.
            - Do not guess, estimate, round, or reason clinically about the scores. This is a literal lookup, not a clinical judgment.
            - The keys in "official_thresholds" use snake_case tier names (e.g. "extremely_severe"). Report your classification using the Title Case form of that same tier name (e.g. "Extremely Severe").
            - You must report your classification by calling the classify_dass_subscales tool. Do not respond with any other text.
            PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contentBlocks
     * @return array{depression_level: string, anxiety_level: string, stress_level: string}|null
     */
    private function extractToolInput(array $contentBlocks): ?array
    {
        $validLevels = ClassificationThreshold::severityOrder();

        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? null) !== 'tool_use' || ($block['name'] ?? null) !== self::TOOL_NAME) {
                continue;
            }

            $input = $block['input'] ?? null;

            if (! is_array($input)) {
                return null;
            }

            foreach (['depression_level', 'anxiety_level', 'stress_level'] as $key) {
                if (! in_array($input[$key] ?? null, $validLevels, true)) {
                    return null;
                }
            }

            return $input;
        }

        return null;
    }

    private function agree(AIClassificationResult $a, AIClassificationResult $b): bool
    {
        return $a->depressionLevel === $b->depressionLevel
            && $a->anxietyLevel === $b->anxietyLevel
            && $a->stressLevel === $b->stressLevel;
    }
}
