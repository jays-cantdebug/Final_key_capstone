<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentCounselingHistoryService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class StudentCounselingHistoryTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private User $counselor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->counselor = $this->guidanceCounselor();
    }

    private function counselingSession(Student $student, string $status, CarbonInterface $at, array $attributes = []): CounselingSession
    {
        return CounselingSession::factory()->create([
            'student_id' => $student->id,
            'counselor_id' => $this->counselor->id,
            'session_status' => $status,
            'session_datetime' => $at,
            ...$attributes,
        ]);
    }

    private function summary(Student $student): ?Student
    {
        return app(StudentCounselingHistoryService::class)->summaryFor($student);
    }

    // --- Access -----------------------------------------------------------

    public function test_psychometrician_cannot_access_student_counseling_history(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create();

        $this->actingAs($psychometrician)->get(route('counseling-sessions.students.index'))->assertForbidden();
        $this->actingAs($psychometrician)->get(route('counseling-sessions.students.show', $student))->assertForbidden();
        $this->actingAs($psychometrician)->get(route('reports.counseling.pdf', ['student_id' => $student->id]))->assertForbidden();
    }

    public function test_students_path_is_not_captured_by_the_session_resource_route(): void
    {
        $this->actingAs($this->counselor)
            ->get('/counseling-sessions/students')
            ->assertOk()
            ->assertViewIs('counseling-sessions.students.index');
    }

    // --- Listing ------------------------------------------------------------

    public function test_index_lists_one_row_per_student_with_sessions_only(): void
    {
        $withSessions = Student::factory()->create(['first_name' => 'Has', 'last_name' => 'Sessions']);
        Student::factory()->create(['first_name' => 'No', 'last_name' => 'Sessions']);
        $this->counselingSession($withSessions, CounselingSession::STATUS_COMPLETED, now()->subDays(10));
        $this->counselingSession($withSessions, CounselingSession::STATUS_COMPLETED, now()->subDays(3));

        $response = $this->actingAs($this->counselor)->get(route('counseling-sessions.students.index'));

        $response->assertOk();
        $response->assertViewHas('students', function ($students) use ($withSessions) {
            return $students->total() === 1
                && $students->first()->id === $withSessions->id
                && (int) $students->first()->counseling_sessions_count === 2;
        });
    }

    public function test_live_search_returns_only_the_table_partial_filtered_by_name(): void
    {
        $findMe = Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        $other = Student::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);
        $this->counselingSession($findMe, CounselingSession::STATUS_COMPLETED, now()->subDay());
        $this->counselingSession($other, CounselingSession::STATUS_COMPLETED, now()->subDay());

        $response = $this->actingAs($this->counselor)
            ->get(route('counseling-sessions.students.index', ['search' => 'Findme']), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('counseling-sessions.students._table');
        $response->assertSee('Unique');
        $response->assertDontSee('Someone');
    }

    public function test_follow_up_filters_and_urgency_ordering(): void
    {
        $none = Student::factory()->create();
        $needed = Student::factory()->create();
        $overdue = Student::factory()->create();
        $this->counselingSession($none, CounselingSession::STATUS_COMPLETED, now()->subDay());
        $this->counselingSession($needed, CounselingSession::STATUS_COMPLETED, now()->subDays(2), [
            'follow_up_required' => true, 'follow_up_date' => now()->addWeek(),
        ]);
        $this->counselingSession($overdue, CounselingSession::STATUS_COMPLETED, now()->subDays(20), [
            'follow_up_required' => true, 'follow_up_date' => now()->subDays(5),
        ]);

        $ids = fn (array $query) => $this->actingAs($this->counselor)
            ->get(route('counseling-sessions.students.index', $query))
            ->viewData('students')
            ->pluck('id')
            ->all();

        $this->assertSame([$overdue->id, $needed->id, $none->id], $ids([]));
        $this->assertSame([$overdue->id, $needed->id], $ids(['follow_up' => 'needed']));
        $this->assertSame([$overdue->id], $ids(['follow_up' => 'overdue']));
    }

    // --- Last / next session ------------------------------------------------

    public function test_last_session_counts_only_completed_sessions(): void
    {
        $student = Student::factory()->create();
        $completedAt = now()->subDays(10)->startOfMinute();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, $completedAt);
        $this->counselingSession($student, CounselingSession::STATUS_CANCELLED, now()->subDays(5));
        $this->counselingSession($student, CounselingSession::STATUS_NO_SHOW, now()->subDays(2));

        $this->assertTrue($this->summary($student)->last_session_at->equalTo($completedAt));
    }

    public function test_last_session_is_null_when_no_session_is_completed(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, now()->addDay());

        $summary = $this->summary($student);

        $this->assertNull($summary->last_session_at);
        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NONE, $summary->follow_up_status);
    }

    public function test_next_scheduled_is_the_earliest_future_scheduled_session(): void
    {
        $student = Student::factory()->create();
        $soonest = now()->addDays(2)->startOfMinute();
        $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, now()->subDay()); // stale, in the past
        $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, now()->addDays(9));
        $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, $soonest);
        $this->counselingSession($student, CounselingSession::STATUS_CANCELLED, now()->addDay());

        $this->assertTrue($this->summary($student)->next_session_at->equalTo($soonest));
    }

    public function test_soft_deleted_sessions_are_ignored(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDays(5));
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay(), [
            'follow_up_required' => true, 'follow_up_date' => now()->addWeek(),
        ])->delete();

        $summary = $this->summary($student);

        $this->assertSame(1, (int) $summary->counseling_sessions_count);
        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NONE, $summary->follow_up_status);
    }

    // --- Follow-up status -----------------------------------------------------

    public function test_follow_up_is_needed_when_latest_completed_session_requires_it(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay(), [
            'follow_up_required' => true, 'follow_up_date' => now()->addWeek(),
        ]);

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NEEDED, $this->summary($student)->follow_up_status);
    }

    public function test_follow_up_due_today_is_needed_not_overdue(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeek(), [
            'follow_up_required' => true, 'follow_up_date' => now()->startOfDay(),
        ]);

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NEEDED, $this->summary($student)->follow_up_status);
    }

    public function test_follow_up_without_a_date_is_needed_never_overdue(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subMonth(), [
            'follow_up_required' => true, 'follow_up_date' => null,
        ]);

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NEEDED, $this->summary($student)->follow_up_status);
    }

    public function test_follow_up_is_overdue_when_its_date_has_passed(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeeks(3), [
            'follow_up_required' => true, 'follow_up_date' => now()->subDay(),
        ]);

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_OVERDUE, $this->summary($student)->follow_up_status);
    }

    public function test_a_later_scheduled_session_satisfies_the_follow_up(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeeks(3), [
            'follow_up_required' => true, 'follow_up_date' => now()->subDay(),
        ]);
        $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, now()->addDays(3));

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NONE, $this->summary($student)->follow_up_status);
    }

    public function test_later_cancelled_or_no_show_sessions_do_not_satisfy_the_follow_up(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeeks(3), [
            'follow_up_required' => true, 'follow_up_date' => now()->subDay(),
        ]);
        $this->counselingSession($student, CounselingSession::STATUS_CANCELLED, now()->subWeek());
        $this->counselingSession($student, CounselingSession::STATUS_NO_SHOW, now()->subDays(2));

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_OVERDUE, $this->summary($student)->follow_up_status);
    }

    public function test_a_later_completed_session_without_follow_up_clears_it(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeeks(3), [
            'follow_up_required' => true, 'follow_up_date' => now()->subWeek(),
        ]);
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay());

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NONE, $this->summary($student)->follow_up_status);
    }

    public function test_a_scheduled_sessions_own_follow_up_flag_does_not_count_until_it_is_completed(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeek(), [
            'follow_up_required' => false, 'follow_up_date' => null,
        ]);
        $scheduled = $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, now()->addDays(3), [
            'follow_up_required' => true, 'follow_up_date' => now()->addWeeks(3),
        ]);

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NONE, $this->summary($student)->follow_up_status);

        $scheduled->update(['session_status' => CounselingSession::STATUS_COMPLETED]);

        $this->assertSame(StudentCounselingHistoryService::FOLLOW_UP_NEEDED, $this->summary($student)->follow_up_status);
    }

    // --- History page ---------------------------------------------------------

    public function test_history_page_lists_the_students_sessions_newest_first(): void
    {
        $student = Student::factory()->create();
        $older = $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subWeek());
        $newer = $this->counselingSession($student, CounselingSession::STATUS_SCHEDULED, now()->addDay());
        $this->counselingSession(Student::factory()->create(), CounselingSession::STATUS_COMPLETED, now()->subDay());

        $response = $this->actingAs($this->counselor)->get(route('counseling-sessions.students.show', $student));

        $response->assertOk();
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->pluck('id')->all() === [$newer->id, $older->id]);
    }

    public function test_history_page_never_shows_session_notes(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay(), [
            'session_notes' => 'Secret restricted note text',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_RESTRICTED,
            'counselor_id' => $this->guidanceCounselor()->id,
        ]);

        $this->actingAs($this->counselor)
            ->get(route('counseling-sessions.students.show', $student))
            ->assertOk()
            ->assertDontSee('Secret restricted note text');
    }

    public function test_history_page_is_viewable_for_an_archived_student(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay());
        $student->delete();

        $this->actingAs($this->counselor)
            ->get(route('counseling-sessions.students.show', $student))
            ->assertOk()
            ->assertSee('Archived student');
    }

    // --- Per-student report -----------------------------------------------------

    public function test_counseling_report_can_be_scoped_to_one_student_and_still_redacts_restricted_notes(): void
    {
        $student = Student::factory()->create(['first_name' => 'Scoped', 'last_name' => 'Student']);
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay(), [
            'session_notes' => 'Restricted note from someone else',
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_RESTRICTED,
            'counselor_id' => $this->guidanceCounselor()->id,
        ]);
        $this->counselingSession(Student::factory()->create(), CounselingSession::STATUS_COMPLETED, now()->subDay(), [
            'session_notes' => 'Another student note',
        ]);

        $response = $this->actingAs($this->counselor)
            ->get(route('reports.counseling.print', ['student_id' => $student->id]));

        $response->assertOk();
        $response->assertSee('Student Counseling History Report');
        $response->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 1);
        $response->assertDontSee('Restricted note from someone else');
        $response->assertDontSee('Another student note');
    }

    public function test_per_student_pdf_downloads(): void
    {
        $student = Student::factory()->create();
        $this->counselingSession($student, CounselingSession::STATUS_COMPLETED, now()->subDay());

        $this->actingAs($this->counselor)
            ->get(route('reports.counseling.pdf', ['student_id' => $student->id]))
            ->assertOk()
            ->assertDownload("counseling-history-{$student->student_number}.pdf");
    }
}
