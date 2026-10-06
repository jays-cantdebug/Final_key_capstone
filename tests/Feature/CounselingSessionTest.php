<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\CounselingSessionFormRequest;
use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\DassResult;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class CounselingSessionTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_guidance_counselor_can_create_a_counseling_session(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();
        $sessionAt = now()->addDay();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => $sessionAt->format('Y-m-d'),
            'session_time' => $sessionAt->format('H:i'),
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => false,
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('counseling_sessions', [
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
        ]);
    }

    public function test_creating_a_session_requires_both_date_and_time_with_precise_per_field_errors(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->addDay()->format('Y-m-d'),
            // session_time omitted entirely.
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => false,
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasErrors('session_time');
        $response->assertSessionDoesntHaveErrors('session_date');
    }

    public function test_a_unique_name_search_auto_selects_the_single_matching_student(): void
    {
        $counselor = $this->guidanceCounselor();
        Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Match']);

        $response = $this->actingAs($counselor)->get(route('counseling-sessions.create', ['search' => 'Unique Match']));

        $response->assertOk();
        $response->assertViewHas('foundStudent', fn ($student) => $student->first_name === 'Unique');
    }

    public function test_an_ambiguous_name_search_returns_multiple_matches_for_the_picker(): void
    {
        $counselor = $this->guidanceCounselor();
        Student::factory()->create(['first_name' => 'John', 'last_name' => 'Smith']);
        Student::factory()->create(['first_name' => 'John', 'last_name' => 'Smithson']);

        $response = $this->actingAs($counselor)->get(route('counseling-sessions.create', ['search' => 'John']));

        $response->assertOk();
        $response->assertViewHas('matches', fn ($matches) => $matches->count() === 2);
        $response->assertViewHas('foundStudent', null);
    }

    public function test_session_list_normal_request_returns_the_full_page(): void
    {
        $counselor = $this->guidanceCounselor();

        $response = $this->actingAs($counselor)->get(route('counseling-sessions.index'));

        $response->assertOk();
        $response->assertViewIs('counseling-sessions.index');
    }

    public function test_session_list_live_search_request_returns_only_the_table_partial(): void
    {
        $counselor = $this->guidanceCounselor();
        $findMe = Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        $someoneElse = Student::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);
        CounselingSession::factory()->create(['student_id' => $findMe->id, 'counselor_id' => $counselor->id]);
        CounselingSession::factory()->create(['student_id' => $someoneElse->id, 'counselor_id' => $counselor->id]);

        $response = $this->actingAs($counselor)
            ->get(route('counseling-sessions.index', ['search' => 'Findme']), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('counseling-sessions._table');
        $response->assertSee('Unique');
        $response->assertDontSee('Someone');
        $response->assertDontSee('Counseling Sessions');
    }

    public function test_student_picker_normal_request_returns_the_full_page(): void
    {
        $counselor = $this->guidanceCounselor();

        $response = $this->actingAs($counselor)->get(route('counseling-sessions.create'));

        $response->assertOk();
        $response->assertViewIs('counseling-sessions.create');
    }

    public function test_student_picker_live_search_request_returns_only_the_results_partial(): void
    {
        $counselor = $this->guidanceCounselor();
        Student::factory()->create(['first_name' => 'John', 'last_name' => 'Smith']);
        Student::factory()->create(['first_name' => 'John', 'last_name' => 'Smithson']);

        $response = $this->actingAs($counselor)
            ->get(route('counseling-sessions.create', ['search' => 'John']), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('counseling-sessions._search-results');
        $response->assertSee('Smith');
        $response->assertSee('Smithson');
        $response->assertDontSee('Schedule Session');
    }

    public function test_restricted_session_cannot_be_edited_by_a_different_counselor(): void
    {
        $creator = $this->guidanceCounselor();
        $otherCounselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->restricted()->create(['counselor_id' => $creator->id]);

        $response = $this->actingAs($otherCounselor)->get(route('counseling-sessions.edit', $session));

        $response->assertForbidden();
    }

    public function test_restricted_session_can_be_edited_by_its_own_creator(): void
    {
        $creator = $this->guidanceCounselor();
        $session = CounselingSession::factory()->restricted()->create(['counselor_id' => $creator->id]);

        $this->actingAs($creator)->get(route('counseling-sessions.edit', $session))->assertOk();
    }

    public function test_psychometrician_cannot_access_counseling_sessions(): void
    {
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->get(route('counseling-sessions.index'))->assertForbidden();
    }

    public function test_follow_up_required_without_a_date_fails_with_a_clear_message(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->addDay()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '1',
            'follow_up_date' => '',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasErrors(['follow_up_date' => CounselingSessionFormRequest::FOLLOW_UP_DATE_REQUIRED_MESSAGE]);
        $this->assertDatabaseCount('counseling_sessions', 0);
    }

    public function test_unticking_follow_up_required_clears_the_stored_follow_up_date(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_required' => true,
            'follow_up_date' => now()->addWeek()->toDateString(),
        ]);

        // The hidden date input still submits its last value when unticked.
        $response = $this->actingAs($counselor)->put(route('counseling-sessions.update', $session), [
            'session_date' => now()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Follow-up no longer needed.',
            'session_status' => CounselingSession::STATUS_COMPLETED,
            'follow_up_required' => '0',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertFalse($session->follow_up_required);
        $this->assertNull($session->follow_up_date);
    }

    public function test_creating_a_session_without_follow_up_ignores_a_submitted_follow_up_date(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->addDay()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '0',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('counseling_sessions', [
            'student_id' => $student->id,
            'follow_up_required' => false,
            'follow_up_date' => null,
        ]);
    }

    public function test_follow_up_date_before_the_session_date_is_rejected(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => '2026-10-10',
            'session_time' => '09:00',
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '1',
            'follow_up_date' => '2026-10-09',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasErrors(['follow_up_date' => CounselingSessionFormRequest::FOLLOW_UP_DATE_BEFORE_SESSION_MESSAGE]);
        $this->assertDatabaseCount('counseling_sessions', 0);
    }

    public function test_follow_up_date_on_the_same_day_as_the_session_is_allowed(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => '2026-10-10',
            'session_time' => '09:00',
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '1',
            'follow_up_date' => '2026-10-10',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasNoErrors();
        $session = CounselingSession::query()->where('student_id', $student->id)->sole();
        $this->assertSame('2026-10-10', $session->follow_up_date->toDateString());
    }

    public function test_a_blank_session_date_does_not_add_a_misleading_follow_up_date_error(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => '',
            'session_time' => '09:00',
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '1',
            'follow_up_date' => '2026-10-10',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasErrors('session_date');
        $response->assertSessionDoesntHaveErrors('follow_up_date');
    }

    public function test_an_update_that_leaves_out_follow_up_required_keeps_the_follow_up_date(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_required' => true,
            'follow_up_date' => '2026-10-20',
        ]);

        $response = $this->actingAs($counselor)->put(route('counseling-sessions.update', $session), [
            'session_date' => '2026-10-10',
            'session_time' => '09:00',
            'session_notes' => 'Notes only.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
            // follow_up_required and follow_up_date deliberately omitted.
        ]);

        $response->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertTrue($session->follow_up_required);
        $this->assertSame('2026-10-20', $session->follow_up_date->toDateString());
    }

    public function test_schedule_form_shows_validation_errors_as_field_tooltips_not_a_duplicate_list(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();
        $createUrl = route('counseling-sessions.create', ['student_id' => $student->id]);

        $response = $this->actingAs($counselor)->from($createUrl)->followingRedirects()->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->addDay()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Initial consultation.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '1',
            'follow_up_date' => '',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertOk();
        $response->assertSee(CounselingSessionFormRequest::FOLLOW_UP_DATE_REQUIRED_MESSAGE);
        $response->assertSee('showsTooltip(', false);
        $response->assertDontSee('list-disc space-y-1 pl-5', false);
        $response->assertDontSee('when follow up required is 1');
    }

    public function test_edit_form_shows_validation_errors_as_field_tooltips(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create(['counselor_id' => $counselor->id]);

        $response = $this->actingAs($counselor)
            ->from(route('counseling-sessions.edit', $session))
            ->followingRedirects()
            ->put(route('counseling-sessions.update', $session), [
                'session_date' => now()->format('Y-m-d'),
                'session_time' => '09:00',
                'session_notes' => 'Updated notes.',
                'session_status' => CounselingSession::STATUS_COMPLETED,
                'follow_up_required' => '1',
                'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
            ]);

        $response->assertOk();
        $response->assertSee(CounselingSessionFormRequest::FOLLOW_UP_DATE_REQUIRED_MESSAGE);
        $this->assertFalse($session->refresh()->follow_up_required);
    }

    public function test_a_completed_session_dated_after_today_is_rejected_on_create(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->addDay()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Logged too early.',
            'session_status' => CounselingSession::STATUS_COMPLETED,
            'follow_up_required' => '0',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasErrors([
            'session_date' => 'A Completed session cannot be dated after today. Set the status to Scheduled, or correct the date.',
        ]);
        $this->assertDatabaseCount('counseling_sessions', 0);
    }

    public function test_a_completed_session_dated_after_today_is_rejected_on_update(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create([
            'counselor_id' => $counselor->id,
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'session_datetime' => now()->addWeek()->setTime(9, 0),
        ]);

        $response = $this->actingAs($counselor)->put(route('counseling-sessions.update', $session), [
            'session_date' => now()->addWeek()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Marked completed before it happened.',
            'session_status' => CounselingSession::STATUS_COMPLETED,
            'follow_up_required' => '0',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasErrors(['session_date' => CounselingSessionFormRequest::COMPLETED_IN_FUTURE_MESSAGE]);
        $this->assertSame(CounselingSession::STATUS_SCHEDULED, $session->refresh()->session_status);
    }

    public function test_a_completed_session_dated_today_is_allowed(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->format('Y-m-d'),
            'session_time' => '23:59',
            'session_notes' => 'Held today.',
            'session_status' => CounselingSession::STATUS_COMPLETED,
            'follow_up_required' => '0',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('counseling_sessions', [
            'student_id' => $student->id,
            'session_status' => CounselingSession::STATUS_COMPLETED,
        ]);
    }

    public function test_a_scheduled_session_dated_after_today_is_allowed(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counseling-sessions.store'), [
            'student_id' => $student->id,
            'session_date' => now()->addMonth()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Upcoming session.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '0',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('counseling_sessions', [
            'student_id' => $student->id,
            'session_status' => CounselingSession::STATUS_SCHEDULED,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function sessionFields(array $overrides = []): array
    {
        return [
            'session_date' => now()->format('Y-m-d'),
            'session_time' => '09:00',
            'session_notes' => 'Session notes.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => '0',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
            ...$overrides,
        ];
    }

    public function test_an_update_cannot_link_another_students_assessment_through_a_submitted_student_id(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create(['counselor_id' => $counselor->id, 'assessment_id' => null]);
        $otherAssessment = Assessment::factory()->create();

        $this->actingAs($counselor)->put(route('counseling-sessions.update', $session), $this->sessionFields([
            'student_id' => (string) $otherAssessment->student_id,
            'assessment_id' => (string) $otherAssessment->id,
        ]))->assertSessionHasErrors('assessment_id');

        $session->refresh();
        $this->assertNull($session->assessment_id);
        $this->assertNotSame($otherAssessment->student_id, $session->student_id);
    }

    public function test_an_update_never_changes_the_sessions_student(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create(['counselor_id' => $counselor->id, 'assessment_id' => null]);
        $originalStudentId = $session->student_id;
        $otherStudent = Student::factory()->create();

        $this->actingAs($counselor)->put(route('counseling-sessions.update', $session), $this->sessionFields([
            'student_id' => (string) $otherStudent->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($originalStudentId, $session->fresh()->student_id);
    }

    public function test_an_update_can_still_link_the_sessions_own_students_assessment(): void
    {
        $counselor = $this->guidanceCounselor();
        $session = CounselingSession::factory()->create(['counselor_id' => $counselor->id, 'assessment_id' => null]);
        $ownAssessment = Assessment::factory()->create(['student_id' => $session->student_id]);

        $this->actingAs($counselor)->put(route('counseling-sessions.update', $session), $this->sessionFields([
            'assessment_id' => (string) $ownAssessment->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($ownAssessment->id, $session->fresh()->assessment_id);
    }

    public function test_a_session_cannot_be_created_for_an_archived_student(): void
    {
        $counselor = $this->guidanceCounselor();
        $student = Student::factory()->create();
        $student->delete();

        $this->actingAs($counselor)->post(route('counseling-sessions.store'), $this->sessionFields([
            'student_id' => (string) $student->id,
        ]))->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('counseling_sessions', 0);
    }

    public function test_an_archived_students_existing_session_stays_editable_and_viewable(): void
    {
        $counselor = $this->guidanceCounselor();
        $assessment = Assessment::factory()->create();
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        $session = CounselingSession::factory()->create([
            'counselor_id' => $counselor->id,
            'student_id' => $assessment->student_id,
            'assessment_id' => null,
        ]);
        $assessment->student->delete();

        $this->actingAs($counselor)->get(route('counseling-sessions.edit', $session))->assertOk();
        $this->put(route('counseling-sessions.update', $session), $this->sessionFields([
            'session_notes' => 'Updated after archiving.',
            'assessment_id' => (string) $assessment->id,
        ]))->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('Updated after archiving.', $session->session_notes);
        $this->assertSame($assessment->id, $session->assessment_id);

        $this->get(route('counseling-sessions.show', $session))->assertOk();
        $this->get(route('counseling-sessions.students.show', $assessment->student_id))->assertOk();
    }
}
