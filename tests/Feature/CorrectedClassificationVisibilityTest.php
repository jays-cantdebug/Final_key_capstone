<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\PredictionFeedback;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * The Guidance Counselor sees the Psychometrician's reviewed classification
 * and a "Corrected by Psychometrician" badge — never the AI's raw levels —
 * while the Psychometrician's own screens are unchanged.
 *
 * Stress raw → final: 11 → 22 (Moderate), 14 → 28 (Severe), per the
 * official thresholds.
 */
class CorrectedClassificationVisibilityTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private const BADGE = 'Corrected by Psychometrician';

    /**
     * Save one assessment through the real wizard (Depression and Anxiety
     * Normal, Stress at $stressRaw) with the given Step 3 decision.
     *
     * @param  array<string, mixed>  $decision
     */
    private function saveThroughWizard(User $psychometrician, int $stressRaw, array $decision): Assessment
    {
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();

        $this->actingAs($psychometrician);

        return $this->saveAssessmentThroughWizard($version, depressionRaw: 0, anxietyRaw: 0, stressRaw: $stressRaw, decision: $decision);
    }

    /**
     * An Endorsement-flagged assessment with a counselor notification, built
     * directly — for review shapes the wizard itself refuses (a Confirm with
     * stored corrections, a same-level-only Correct).
     *
     * @param  array<string, mixed>  $review
     */
    private function flaggedAssessmentFor(User $counselor, string $aiStress, array $review): Assessment
    {
        $assessment = Assessment::factory()->create();
        DassResult::factory()->withLevels('Normal', 'Normal', $aiStress)->create(['assessment_id' => $assessment->id]);
        PredictionFeedback::factory()->create(['assessment_id' => $assessment->id, ...$review]);
        $flaggedCase = FlaggedCase::factory()->endorsement()->create(['assessment_id' => $assessment->id]);

        SystemNotification::factory()->create([
            'user_id' => $counselor->id,
            'assessment_id' => $assessment->id,
            'flagged_case_id' => $flaggedCase->id,
            'notification_type' => SystemNotification::TYPE_COUNSELING_ENDORSEMENT,
            'title' => 'Counseling Endorsement',
        ]);

        return $assessment;
    }

    public function test_correction_up_across_the_threshold_notifies_with_the_reviewed_level_and_shows_the_badge(): void
    {
        $counselor = $this->guidanceCounselor();

        $assessment = $this->saveThroughWizard($this->psychometrician(), stressRaw: 11, decision: [
            'is_confirmed' => '0',
            'corrected_stress_level' => 'Severe',
        ]);

        $this->assertSame('Moderate', $assessment->result->stress_level, 'The AI level stays stored for the audit trail.');

        $notification = SystemNotification::query()->where('user_id', $counselor->id)->sole();
        $this->assertStringContainsString('was assessed with Severe Stress on', $notification->message);
        $this->assertStringNotContainsString('Moderate', $notification->message);

        $this->actingAs($counselor)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(self::BADGE)
            ->assertSee('was assessed with Severe Stress on')
            ->assertDontSee('Moderate');

        $this->actingAs($counselor)->get(route('flagged-cases.index'))
            ->assertOk()
            ->assertSee(self::BADGE)
            ->assertDontSee('Moderate');

        $this->actingAs($counselor)->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertSee(self::BADGE)
            ->assertDontSee('Moderate');
    }

    public function test_correction_within_the_flag_triggering_levels_shows_the_badge(): void
    {
        $counselor = $this->guidanceCounselor();

        $assessment = $this->saveThroughWizard($this->psychometrician(), stressRaw: 14, decision: [
            'is_confirmed' => '0',
            'corrected_stress_level' => 'Extremely Severe',
        ]);

        $this->assertSame('Severe', $assessment->result->stress_level);

        $notification = SystemNotification::query()->where('user_id', $counselor->id)->sole();
        $this->assertStringContainsString('was assessed with Extremely Severe Stress on', $notification->message);

        $this->actingAs($counselor)->get(route('notifications.index'))->assertOk()->assertSee(self::BADGE);
        $this->actingAs($counselor)->get(route('flagged-cases.index'))->assertOk()->assertSee(self::BADGE);
        $this->actingAs($counselor)->get(route('assessments.show', $assessment))->assertOk()->assertSee(self::BADGE);
    }

    public function test_plain_confirm_shows_no_badge(): void
    {
        $counselor = $this->guidanceCounselor();

        $assessment = $this->saveThroughWizard($this->psychometrician(), stressRaw: 14, decision: ['is_confirmed' => '1']);

        $this->assertStringContainsString('was assessed with Severe Stress on', SystemNotification::query()->sole()->message);

        $this->actingAs($counselor)->get(route('notifications.index'))->assertOk()->assertDontSee(self::BADGE);
        $this->actingAs($counselor)->get(route('flagged-cases.index'))->assertOk()->assertDontSee(self::BADGE);
        $this->actingAs($counselor)->get(route('assessments.show', $assessment))->assertOk()->assertDontSee(self::BADGE);
    }

    public function test_confirm_with_stored_corrections_shows_no_badge_and_the_ai_level(): void
    {
        $counselor = $this->guidanceCounselor();

        // Like dev assessment #125: a Confirm saved with corrections stored.
        // save() ignored them, so the reviewed level is the AI's own.
        $assessment = $this->flaggedAssessmentFor($counselor, 'Severe', [
            'is_confirmed' => true,
            'corrected_stress_level' => 'Extremely Severe',
        ]);

        $this->actingAs($counselor)->get(route('notifications.index'))->assertOk()->assertDontSee(self::BADGE);
        $this->actingAs($counselor)->get(route('flagged-cases.index'))
            ->assertOk()
            ->assertDontSee(self::BADGE)
            ->assertDontSee('Extremely Severe');
        $this->actingAs($counselor)->get(route('assessments.show', $assessment))->assertOk()->assertDontSee(self::BADGE);
    }

    public function test_same_level_pick_shows_no_badge(): void
    {
        $counselor = $this->guidanceCounselor();

        $assessment = $this->flaggedAssessmentFor($counselor, 'Severe', [
            'is_confirmed' => false,
            'corrected_stress_level' => 'Severe',
        ]);

        $this->actingAs($counselor)->get(route('notifications.index'))->assertOk()->assertDontSee(self::BADGE);
        $this->actingAs($counselor)->get(route('flagged-cases.index'))->assertOk()->assertDontSee(self::BADGE);
        $this->actingAs($counselor)->get(route('assessments.show', $assessment))->assertOk()->assertDontSee(self::BADGE);
    }

    public function test_psychometrician_is_never_notified_about_a_corrected_assessment(): void
    {
        $counselors = [$this->guidanceCounselor(), $this->guidanceCounselor()];
        $psychometrician = $this->psychometrician();

        $this->saveThroughWizard($psychometrician, stressRaw: 11, decision: [
            'is_confirmed' => '0',
            'corrected_stress_level' => 'Severe',
        ]);

        $this->assertSame(0, SystemNotification::query()->where('user_id', $psychometrician->id)->count());
        $this->assertEqualsCanonicalizing(
            array_map(fn (User $counselor): int => $counselor->id, $counselors),
            SystemNotification::query()->pluck('user_id')->all()
        );
    }

    public function test_every_counselor_page_shows_the_reviewed_level_not_the_ai_level(): void
    {
        $counselor = $this->guidanceCounselor();

        // AI: Stress Moderate. Reviewed: Stress Severe.
        $assessment = $this->saveThroughWizard($this->psychometrician(), stressRaw: 11, decision: [
            'is_confirmed' => '0',
            'corrected_stress_level' => 'Severe',
        ]);

        $session = CounselingSession::factory()->create([
            'student_id' => $assessment->student_id,
            'assessment_id' => $assessment->id,
            'counselor_id' => $counselor->id,
        ]);

        $pages = [
            'flagged cases' => route('flagged-cases.index'),
            'dashboard' => route('guidance-counselor.dashboard'),
            'assessment history' => route('assessments.index'),
            'assessment page' => route('assessments.show', $assessment),
            'counseling session' => route('counseling-sessions.show', $session),
            'counseling history' => route('counseling-sessions.students.show', $assessment->student_id),
            'session form picker' => route('counseling-sessions.edit', $session),
            'assessment report' => route('reports.assessment.print', $assessment),
            'student history report' => route('reports.student-history.print', ['student_number' => $assessment->student->student_number]),
        ];

        foreach ($pages as $name => $url) {
            $html = $this->actingAs($counselor)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Severe', $html, "{$name}: the reviewed level is missing.");
            $this->assertStringNotContainsString('Moderate', $html, "{$name}: the AI's raw level leaked.");
        }
    }

    public function test_psychometrician_pages_still_show_the_ai_level_and_no_badge(): void
    {
        $psychometrician = $this->psychometrician();

        $assessment = $this->saveThroughWizard($psychometrician, stressRaw: 11, decision: [
            'is_confirmed' => '0',
            'corrected_stress_level' => 'Severe',
        ]);

        $pages = [
            'dashboard' => route('psychometrician.dashboard'),
            'assessment history' => route('assessments.index'),
            'student profile' => route('students.show', $assessment->student_id),
            'assessment page' => route('assessments.show', $assessment),
            'assessment report' => route('reports.assessment.print', $assessment),
            'student history report' => route('reports.student-history.print', ['student_number' => $assessment->student->student_number]),
        ];

        foreach ($pages as $name => $url) {
            $html = $this->actingAs($psychometrician)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Moderate', $html, "{$name}: the AI's level should still be shown to the Psychometrician.");
            $this->assertStringNotContainsString(self::BADGE, $html, "{$name}: the badge is for the Counselor only.");
        }

        // The Prediction Feedback card is unchanged for her.
        $this->actingAs($psychometrician)->get(route('assessments.show', $assessment))
            ->assertSee('Corrected by '.$psychometrician->name)
            ->assertSee('Stress &rarr; Severe', false);
    }

    public function test_badge_carries_no_level_direction_or_data_attributes(): void
    {
        $html = Blade::render('<x-corrected-badge />');

        $this->assertStringContainsString(self::BADGE, $html);
        $this->assertStringNotContainsString('data-', $html);
        $this->assertDoesNotMatchRegularExpression('/Normal|Mild|Moderate|Severe/', $html);
        $this->assertDoesNotMatchRegularExpression('/\b(up|down|higher|lower|raised|lowered|increased|decreased)\b|→|&rarr;/i', $html);
    }

    public function test_inbox_and_flagged_cases_do_not_add_queries_per_row(): void
    {
        $counselor = $this->guidanceCounselor();
        $corrected = ['is_confirmed' => false, 'corrected_stress_level' => 'Extremely Severe'];

        $this->flaggedAssessmentFor($counselor, 'Severe', $corrected);

        // Warm-up: the first request also loads the acting user's role once,
        // which the in-memory User then caches — not a per-row query.
        $this->actingAs($counselor)->get(route('notifications.index'))->assertOk();

        $oneRow = $this->queryCountFor($counselor, route('notifications.index'));
        $oneRowFlagged = $this->queryCountFor($counselor, route('flagged-cases.index'));

        foreach (range(1, 3) as $ignored) {
            $this->flaggedAssessmentFor($counselor, 'Severe', $corrected);
        }

        $this->assertSame($oneRow, $this->queryCountFor($counselor, route('notifications.index')));
        $this->assertSame($oneRowFlagged, $this->queryCountFor($counselor, route('flagged-cases.index')));
    }

    public function test_sidebar_unread_count_is_unaffected_by_corrections(): void
    {
        $counselor = $this->guidanceCounselor();
        $this->flaggedAssessmentFor($counselor, 'Severe', ['is_confirmed' => false, 'corrected_stress_level' => 'Extremely Severe']);
        $this->flaggedAssessmentFor($counselor, 'Severe', ['is_confirmed' => true]);

        $this->actingAs($counselor)->get(route('flagged-cases.index'))
            ->assertOk()
            ->assertSee('dark:bg-gold-soft">2</span>', false);
    }

    private function queryCountFor(User $user, string $url): int
    {
        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
