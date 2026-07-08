<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\SystemNotification;
use App\Services\FlaggedCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Covers the differentiated flagging rules per CLAUDE.md: Stress
 * Severe/Extremely Severe -> Counseling Endorsement; Depression and/or
 * Anxiety Severe/Extremely Severe -> Awareness Notification, each
 * evaluated independently. A single assessment can produce 0-3 rows.
 */
class FlaggedCaseServiceTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_all_normal_severity_produces_no_flagged_cases_and_no_notifications(): void
    {
        Notification::fake();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_NORMAL)
            ->create(['assessment_id' => $assessment->id]);

        $flagged = app(FlaggedCaseService::class)->evaluateAndFlag($assessment, $result);

        $this->assertCount(0, $flagged);
        $this->assertDatabaseCount('flagged_cases', 0);
        Notification::assertNothingSent();
    }

    public function test_stress_severe_only_produces_a_single_counseling_endorsement_row(): void
    {
        Notification::fake();
        $this->guidanceCounselor();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);

        $flagged = app(FlaggedCaseService::class)->evaluateAndFlag($assessment, $result);

        $this->assertCount(1, $flagged);
        $this->assertSame(FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT, $flagged->first()->flag_type);
        $this->assertSame(FlaggedCase::SUBSCALE_STRESS, $flagged->first()->triggering_subscale);
    }

    public function test_depression_or_anxiety_severe_only_produces_awareness_notification_rows(): void
    {
        Notification::fake();
        $this->guidanceCounselor();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, ClassificationThreshold::SEVERITY_SEVERE, ClassificationThreshold::SEVERITY_NORMAL)
            ->create(['assessment_id' => $assessment->id]);

        $flagged = app(FlaggedCaseService::class)->evaluateAndFlag($assessment, $result);

        $this->assertCount(2, $flagged);
        $this->assertTrue($flagged->every(fn (FlaggedCase $case) => $case->flag_type === FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION));
        $this->assertEqualsCanonicalizing(
            [FlaggedCase::SUBSCALE_DEPRESSION, FlaggedCase::SUBSCALE_ANXIETY],
            $flagged->pluck('triggering_subscale')->all()
        );
    }

    public function test_both_stress_and_depression_anxiety_severe_produces_two_independent_rows(): void
    {
        Notification::fake();
        $this->guidanceCounselor();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_SEVERE, ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);

        $flagged = app(FlaggedCaseService::class)->evaluateAndFlag($assessment, $result);

        $this->assertCount(2, $flagged);
        $this->assertDatabaseHas('flagged_cases', [
            'assessment_id' => $assessment->id,
            'flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT,
            'triggering_subscale' => FlaggedCase::SUBSCALE_STRESS,
        ]);
        $this->assertDatabaseHas('flagged_cases', [
            'assessment_id' => $assessment->id,
            'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
            'triggering_subscale' => FlaggedCase::SUBSCALE_DEPRESSION,
        ]);
    }

    public function test_all_three_subscales_severe_produces_three_rows(): void
    {
        Notification::fake();
        $this->guidanceCounselor();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);

        $flagged = app(FlaggedCaseService::class)->evaluateAndFlag($assessment, $result);

        $this->assertCount(3, $flagged);
    }

    public function test_evaluate_and_flag_is_idempotent_and_does_not_duplicate_rows_on_a_second_call(): void
    {
        Notification::fake();
        $this->guidanceCounselor();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);

        $service = app(FlaggedCaseService::class);
        $service->evaluateAndFlag($assessment, $result);
        $service->evaluateAndFlag($assessment, $result);

        $this->assertDatabaseCount('flagged_cases', 1);
    }

    /**
     * Regression test: notifications for a newly-created flagged case must
     * go out to Guidance Counselors only. The Psychometrician is a
     * deliberate design decision to never receive one — she already sees
     * the result immediately on submission — not merely an absence of
     * behavior, so this is locked in explicitly rather than left implicit.
     */
    public function test_psychometrician_never_receives_a_flagged_case_notification(): void
    {
        $psychometrician = $this->psychometrician();
        $counselor = $this->guidanceCounselor();

        $assessment = Assessment::factory()->create(['psychometrician_id' => $psychometrician->id]);
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE, ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);

        app(FlaggedCaseService::class)->evaluateAndFlag($assessment, $result);

        $this->assertDatabaseMissing('system_notifications', ['user_id' => $psychometrician->id]);

        $counselorNotificationCount = SystemNotification::query()->where('user_id', $counselor->id)->count();
        $this->assertGreaterThan(0, $counselorNotificationCount);
    }

    public function test_only_newly_created_flagged_cases_trigger_a_notification(): void
    {
        $this->guidanceCounselor();

        $assessment = Assessment::factory()->create();
        $result = DassResult::factory()
            ->withLevels(ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_NORMAL, ClassificationThreshold::SEVERITY_SEVERE)
            ->create(['assessment_id' => $assessment->id]);

        $service = app(FlaggedCaseService::class);
        $service->evaluateAndFlag($assessment, $result);
        $this->assertDatabaseCount('system_notifications', 1);

        // Calling again for the same already-flagged assessment must not
        // send a second notification for the same flagged case.
        $service->evaluateAndFlag($assessment, $result);
        $this->assertDatabaseCount('system_notifications', 1);
    }
}
