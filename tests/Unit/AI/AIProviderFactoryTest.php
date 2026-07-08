<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\DTOs\AssessmentPayload;
use App\AI\Exceptions\AIProviderNotImplementedException;
use App\AI\Factories\AIProviderFactory;
use App\AI\Providers\ClaudeAIProvider;
use App\AI\Providers\RuleBasedDASSProvider;
use InvalidArgumentException;
use Tests\TestCase;

class AIProviderFactoryTest extends TestCase
{
    public function test_resolves_rule_based_provider_by_default_config(): void
    {
        config(['ai.provider' => 'rule_based']);

        $provider = (new AIProviderFactory)->make();

        $this->assertInstanceOf(RuleBasedDASSProvider::class, $provider);
    }

    public function test_resolves_claude_provider_when_configured(): void
    {
        config(['ai.provider' => 'claude']);

        $provider = (new AIProviderFactory)->make();

        $this->assertInstanceOf(ClaudeAIProvider::class, $provider);
    }

    public function test_throws_for_an_unknown_configured_provider(): void
    {
        config(['ai.provider' => 'not_a_real_provider']);

        $this->expectException(InvalidArgumentException::class);

        (new AIProviderFactory)->make();
    }

    public function test_claude_provider_scaffold_throws_not_implemented(): void
    {
        $this->expectException(AIProviderNotImplementedException::class);

        (new ClaudeAIProvider)->classify(new AssessmentPayload(
            assessmentId: 1,
            depressionFinalScore: 0,
            anxietyFinalScore: 0,
            stressFinalScore: 0,
        ));
    }
}
