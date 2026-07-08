<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\FlaggedCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_flag_prefers_counseling_endorsement_over_awareness_notification(): void
    {
        $assessment = Assessment::factory()->create();
        FlaggedCase::factory()->create(['assessment_id' => $assessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION, 'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION]);
        $endorsement = FlaggedCase::factory()->endorsement()->create(['assessment_id' => $assessment->id]);

        $assessment->load('flaggedCases');

        $this->assertTrue($assessment->priorityFlag()->is($endorsement));
    }

    public function test_secondary_flag_count_counts_rows_beyond_the_priority_flag(): void
    {
        $assessment = Assessment::factory()->create();
        FlaggedCase::factory()->endorsement()->create(['assessment_id' => $assessment->id]);
        FlaggedCase::factory()->create(['assessment_id' => $assessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION, 'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION]);
        FlaggedCase::factory()->create(['assessment_id' => $assessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION, 'triggering_subscale' => FlaggedCase::SUBSCALE_ANXIETY]);

        $assessment->load('flaggedCases');

        $this->assertSame(2, $assessment->secondaryFlagCount());
    }

    public function test_priority_flag_is_null_and_secondary_count_is_zero_when_no_flags_exist(): void
    {
        $assessment = Assessment::factory()->create();
        $assessment->load('flaggedCases');

        $this->assertNull($assessment->priorityFlag());
        $this->assertSame(0, $assessment->secondaryFlagCount());
    }
}
