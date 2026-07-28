<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClassificationThreshold;
use App\Models\Course;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
