<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\Course;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\PredictionFeedback;
use App\Models\Student;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_reports_hub_renders_for_either_role(): void
    {
        $this->actingAs($this->psychometrician())->get(route('reports.index'))->assertOk();
        $this->actingAs($this->guidanceCounselor())->get(route('reports.index'))->assertOk();
    }

    public function test_assessment_report_pdf_downloads_successfully(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $response = $this->actingAs($psychometrician)->get(route('reports.assessment.pdf', $assessment));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_flagged_students_report_is_guidance_counselor_only(): void
    {
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();

        $this->actingAs($counselor)->get(route('reports.flagged-students.print'))->assertOk();
        $this->actingAs($psychometrician)->get(route('reports.flagged-students.print'))->assertForbidden();
    }

    public function test_counseling_report_is_guidance_counselor_only(): void
    {
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();

        $this->actingAs($counselor)->get(route('reports.counseling.print'))->assertOk();
        $this->actingAs($psychometrician)->get(route('reports.counseling.print'))->assertForbidden();
    }

    public function test_flagged_students_report_flag_type_filter_narrows_results(): void
    {
        $counselor = $this->guidanceCounselor();

        $endorsement = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $endorsement->id]);
        FlaggedCase::factory()->endorsement()->create(['assessment_id' => $endorsement->id]);

        $notification = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $notification->id]);
        FlaggedCase::factory()->create(['assessment_id' => $notification->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION]);

        $unfiltered = $this->actingAs($counselor)->get(route('reports.flagged-students.print'));
        $unfiltered->assertOk();
        $unfiltered->assertSee('Counseling Endorsement');
        $unfiltered->assertSee('Awareness Notification');

        $endorsementOnly = $this->actingAs($counselor)->get(route('reports.flagged-students.print', [
            'flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT,
        ]));
        $endorsementOnly->assertOk();
        $endorsementOnly->assertSee('Counseling Endorsement');
        $endorsementOnly->assertDontSee('Awareness Notification');
    }

    public function test_flagged_students_report_notification_filter_excludes_cases_that_also_have_an_endorsement(): void
    {
        $counselor = $this->guidanceCounselor();

        // Same assessment flagged for both Stress (endorsement) and
        // Depression (notification) — the Flagged Cases listing's
        // "Notification" tab excludes this from view (Endorsement takes
        // display priority), so the report's flag_type filter must match.
        $both = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $both->id]);
        FlaggedCase::factory()->endorsement()->create(['assessment_id' => $both->id]);
        FlaggedCase::factory()->create(['assessment_id' => $both->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION]);

        $notificationOnly = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $notificationOnly->id]);
        FlaggedCase::factory()->create(['assessment_id' => $notificationOnly->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION]);

        $notificationReport = $this->actingAs($counselor)->get(route('reports.flagged-students.print', [
            'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION,
        ]));
        $notificationReport->assertOk();
        $notificationReport->assertSee($notificationOnly->fresh()->student->student_number);
        $notificationReport->assertDontSee($both->fresh()->student->student_number);

        // The same dual-flagged assessment must still surface under the
        // Endorsement filter, unaffected by the exclusion above.
        $endorsementReport = $this->actingAs($counselor)->get(route('reports.flagged-students.print', [
            'flag_type' => FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT,
        ]));
        $endorsementReport->assertOk();
        $endorsementReport->assertSee($both->fresh()->student->student_number);
    }

    public function test_assessment_summary_report_renders(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('reports.assessment-summary'))->assertOk();
    }

    public function test_assessment_summary_report_shows_institution_wide_totals_and_per_condition_breakdowns(): void
    {
        $psychometrician = $this->psychometrician();

        $bsit = Course::factory()->create(['course_code' => 'BSIT']);
        $bscs = Course::factory()->create(['course_code' => 'BSCS']);
        $yearOne = YearLevel::factory()->create(['display_order' => 1]);

        $bsitStudent = Student::factory()->create(['course_id' => $bsit->id, 'year_level_id' => $yearOne->id, 'gender' => 'Male']);
        $bscsStudent = Student::factory()->create(['course_id' => $bscs->id, 'year_level_id' => $yearOne->id, 'gender' => 'Female']);

        $bsitAssessment = Assessment::factory()->create(['student_id' => $bsitStudent->id]);
        DassResult::factory()->withLevels(
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
            ClassificationThreshold::SEVERITY_NORMAL,
            ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE,
        )->create(['assessment_id' => $bsitAssessment->id]);
        FlaggedCase::factory()->create(['assessment_id' => $bsitAssessment->id, 'flag_type' => FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION]);
        FlaggedCase::factory()->endorsement()->create(['assessment_id' => $bsitAssessment->id]);

        $bscsAssessment = Assessment::factory()->create(['student_id' => $bscsStudent->id]);
        DassResult::factory()->create(['assessment_id' => $bscsAssessment->id]);

        // Unfiltered: both students/assessments count.
        $response = $this->actingAs($psychometrician)->get(route('reports.assessment-summary'));
        $response->assertOk();
        $response->assertViewHas('totalStudents', 2);
        $response->assertViewHas('totalAssessments', 2);
        $response->assertViewHas('counselingEndorsements', 1);
        $response->assertViewHas('awarenessNotifications', 1);
        $response->assertViewHas('depressionBySeverity', fn (array $counts) => $counts[ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE] === 1);
        $response->assertViewHas('stressBySeverity', fn (array $counts) => $counts[ClassificationThreshold::SEVERITY_EXTREMELY_SEVERE] === 1);

        // Filtered to BSIT only: excludes the BSCS student/assessment entirely.
        $filtered = $this->actingAs($psychometrician)->get(route('reports.assessment-summary', ['course_id' => $bsit->id]));
        $filtered->assertOk();
        $filtered->assertViewHas('totalStudents', 1);
        $filtered->assertViewHas('totalAssessments', 1);
        $filtered->assertViewHas('counselingEndorsements', 1);
        $filtered->assertViewHas('awarenessNotifications', 1);

        // Filtered to a gender with no matching students: zeroed out, not an error.
        $genderFiltered = $this->actingAs($psychometrician)->get(route('reports.assessment-summary', ['gender' => 'Prefer not to say']));
        $genderFiltered->assertOk();
        $genderFiltered->assertViewHas('totalStudents', 0);
        $genderFiltered->assertViewHas('totalAssessments', 0);
    }

    public function test_assessment_summary_report_print_and_pdf_render(): void
    {
        $psychometrician = $this->psychometrician();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);

        $this->actingAs($psychometrician)->get(route('reports.assessment-summary.print'))->assertOk();

        $pdfResponse = $this->actingAs($psychometrician)->get(route('reports.assessment-summary.pdf'));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }

    private const REVIEWED_BASIS_NOTE = 'Counts use the reviewed classification';

    private const AI_BASIS_NOTE = "Severity counts use the AI's classification before review.";

    /**
     * Three assessments saved through the real wizard, so their flags are
     * the ones the app itself raised:
     *  - AI Stress Moderate, corrected to Severe → Counseling Endorsement;
     *  - AI Depression Severe, corrected to Mild → no flag;
     *  - AI Anxiety Severe, confirmed → Awareness Notification.
     */
    private function seedReviewedAssessmentMix(): void
    {
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $this->actingAs($this->psychometrician());

        $this->saveAssessmentThroughWizard($version, depressionRaw: 0, anxietyRaw: 0, stressRaw: 11, decision: [
            'is_confirmed' => '0', 'corrected_stress_level' => 'Severe',
        ], firstName: 'Ana', lastName: 'Cruz');

        $this->saveAssessmentThroughWizard($version, depressionRaw: 11, anxietyRaw: 0, stressRaw: 0, decision: [
            'is_confirmed' => '0', 'corrected_depression_level' => 'Mild',
        ], firstName: 'Ben', lastName: 'Dizon');

        $this->saveAssessmentThroughWizard($version, depressionRaw: 0, anxietyRaw: 8, stressRaw: 0, firstName: 'Cara', lastName: 'Lim');
    }

    /**
     * @param  array<string, int>  $expected  Non-zero counts; every other tier must be 0.
     * @param  array<string, int>  $actual
     */
    private function assertSeverityCounts(array $expected, array $actual): void
    {
        $this->assertSame(
            array_merge(array_fill_keys(ClassificationThreshold::severityOrder(), 0), $expected),
            $actual
        );
    }

    public function test_assessment_summary_counts_reviewed_levels_for_the_guidance_counselor(): void
    {
        $this->seedReviewedAssessmentMix();

        $response = $this->actingAs($this->guidanceCounselor())->get(route('reports.assessment-summary'));

        $response->assertOk()
            ->assertSee(self::REVIEWED_BASIS_NOTE)
            ->assertDontSee(self::AI_BASIS_NOTE)
            ->assertViewHas('countsBasis', ReportService::COUNTS_BASIS_REVIEWED);

        $this->assertSeverityCounts(['Normal' => 2, 'Severe' => 1], $response->viewData('stressBySeverity'));
        $this->assertSeverityCounts(['Normal' => 2, 'Mild' => 1], $response->viewData('depressionBySeverity'));
        $this->assertSeverityCounts(['Normal' => 2, 'Severe' => 1], $response->viewData('anxietyBySeverity'));
    }

    public function test_assessment_summary_totals_and_flag_counts_agree_for_the_guidance_counselor(): void
    {
        $this->seedReviewedAssessmentMix();

        $data = $this->actingAs($this->guidanceCounselor())->get(route('reports.assessment-summary'))->assertOk()->original->getData();
        $severeOrWorse = fn (array $counts): int => $counts['Severe'] + $counts['Extremely Severe'];

        $this->assertSame(3, $data['totalAssessments']);

        foreach (['depressionBySeverity', 'anxietyBySeverity', 'stressBySeverity'] as $key) {
            $this->assertSame($data['totalAssessments'], array_sum($data[$key]), "{$key} must cover every assessment once.");
        }

        $this->assertSame(1, $data['counselingEndorsements']);
        $this->assertSame($data['counselingEndorsements'], $severeOrWorse($data['stressBySeverity']));

        $this->assertSame(1, $data['awarenessNotifications']);
        $this->assertSame(
            $data['awarenessNotifications'],
            $severeOrWorse($data['depressionBySeverity']) + $severeOrWorse($data['anxietyBySeverity'])
        );
    }

    public function test_assessment_summary_keeps_the_ai_levels_for_the_psychometrician(): void
    {
        $this->seedReviewedAssessmentMix();

        $response = $this->actingAs($this->psychometrician())->get(route('reports.assessment-summary'));

        $response->assertOk()
            ->assertSee(self::AI_BASIS_NOTE, false)
            ->assertDontSee(self::REVIEWED_BASIS_NOTE)
            ->assertViewHas('countsBasis', ReportService::COUNTS_BASIS_AI)
            // Flag totals still follow the reviewed levels, as before.
            ->assertViewHas('counselingEndorsements', 1)
            ->assertViewHas('awarenessNotifications', 1);

        $this->assertSeverityCounts(['Normal' => 2, 'Moderate' => 1], $response->viewData('stressBySeverity'));
        $this->assertSeverityCounts(['Normal' => 2, 'Severe' => 1], $response->viewData('depressionBySeverity'));
        $this->assertSeverityCounts(['Normal' => 2, 'Severe' => 1], $response->viewData('anxietyBySeverity'));
    }

    public function test_assessment_summary_print_and_pdf_carry_the_counts_basis_for_each_role(): void
    {
        $this->seedReviewedAssessmentMix();
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();

        $counselorPrint = $this->actingAs($counselor)->get(route('reports.assessment-summary.print'));
        $counselorPrint->assertOk()->assertSee(self::REVIEWED_BASIS_NOTE)->assertDontSee(self::AI_BASIS_NOTE, false);
        $this->assertSeverityCounts(['Normal' => 2, 'Severe' => 1], $counselorPrint->viewData('stressBySeverity'));

        $psychometricianPrint = $this->actingAs($psychometrician)->get(route('reports.assessment-summary.print'));
        $psychometricianPrint->assertOk()->assertSee(self::AI_BASIS_NOTE, false)->assertDontSee(self::REVIEWED_BASIS_NOTE);
        $this->assertSeverityCounts(['Normal' => 2, 'Moderate' => 1], $psychometricianPrint->viewData('stressBySeverity'));

        foreach ([$counselor, $psychometrician] as $user) {
            $pdf = $this->actingAs($user)->get(route('reports.assessment-summary.pdf'));
            $pdf->assertOk();
            $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        }
    }

    public function test_assessment_summary_counts_the_ai_level_of_a_confirm_with_stored_corrections_for_the_counselor(): void
    {
        // Like dev assessment #125: confirmed, but with corrections stored.
        // Flagging ignored them, so the reviewed level is the AI's own.
        $assessment = Assessment::factory()->create();
        DassResult::factory()->withLevels('Normal', 'Normal', 'Moderate')->create(['assessment_id' => $assessment->id]);
        PredictionFeedback::factory()->create([
            'assessment_id' => $assessment->id,
            'is_confirmed' => true,
            'corrected_stress_level' => 'Severe',
        ]);

        $response = $this->actingAs($this->guidanceCounselor())->get(route('reports.assessment-summary'))->assertOk();

        $this->assertSeverityCounts(['Moderate' => 1], $response->viewData('stressBySeverity'));
        $response->assertViewHas('counselingEndorsements', 0);
    }

    public function test_assessment_summary_reviewed_basis_costs_exactly_one_extra_query(): void
    {
        $this->seedReviewedAssessmentMix();
        $counselor = $this->guidanceCounselor();
        $psychometrician = $this->psychometrician();

        // The print view has no sidebar (whose unread-count query only runs
        // for counselors), so the only difference is the report itself.
        // Each count follows a warm-up request that loads the user's role.
        $this->assertSame(
            $this->printQueryCount($psychometrician) + 1,
            $this->printQueryCount($counselor)
        );
    }

    private function printQueryCount(User $user): int
    {
        $this->actingAs($user)->get(route('reports.assessment-summary.print'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('reports.assessment-summary.print'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_student_history_report_finds_an_archived_students_assessments(): void
    {
        $student = Student::factory()->create();
        $assessment = Assessment::factory()->create(['student_id' => $student->id]);
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        PredictionFeedback::factory()->create(['assessment_id' => $assessment->id]);
        $student->delete();

        foreach ([$this->psychometrician(), $this->guidanceCounselor()] as $user) {
            // Assessment History still lists them and offers Print/PDF.
            $this->actingAs($user)
                ->get(route('assessments.index', ['student_number' => $student->student_number]))
                ->assertOk()
                ->assertSee(route('reports.student-history.print', ['student_number' => $student->student_number]), false);

            $print = $this->get(route('reports.student-history.print', ['student_number' => $student->student_number]));
            $print->assertOk()->assertSee($student->student_number)->assertDontSee('No student found');
            $this->assertSame([$assessment->id], $print->viewData('assessments')->modelKeys());

            $pdf = $this->get(route('reports.student-history.pdf', ['student_number' => $student->student_number]));
            $pdf->assertOk();
            $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        }
    }
}
