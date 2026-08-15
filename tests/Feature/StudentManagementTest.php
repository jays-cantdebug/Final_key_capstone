<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DassResult;
use App\Models\Student;
use App\Services\StudentNumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_psychometrician_can_view_the_student_list(): void
    {
        $psychometrician = $this->psychometrician();
        Student::factory()->count(3)->create();

        $this->actingAs($psychometrician)->get(route('students.index'))->assertOk();
    }

    public function test_guidance_counselor_cannot_view_the_student_list(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('students.index'))->assertForbidden();
    }

    public function test_psychometrician_can_update_a_student_record(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create(['first_name' => 'Old Name']);

        $response = $this->actingAs($psychometrician)->put(route('students.update', $student), [
            'first_name' => 'New',
            'last_name' => $student->last_name,
            'gender' => $student->gender,
            'course_id' => $student->course_id,
            'year_level_id' => $student->year_level_id,
            'section_id' => $student->section_id,
        ]);

        $response->assertRedirect(route('students.show', $student));
        $this->assertSame('New', $student->fresh()->first_name);
    }

    public function test_destroy_archives_the_student_via_soft_delete_rather_than_hard_deleting(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create();

        $this->actingAs($psychometrician)->delete(route('students.destroy', $student));

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_no_standalone_create_route_exists_for_students(): void
    {
        $this->assertFalse(Route::has('students.create'));
        $this->assertFalse(Route::has('students.store'));
    }

    public function test_student_number_is_year_prefixed_and_sequential(): void
    {
        $generator = app(StudentNumberGeneratorService::class);

        $first = $generator->generate();
        Student::factory()->create(['student_number' => $first]);
        $second = $generator->generate();

        $year = now()->year;
        $this->assertSame("{$year}-00001", $first);
        $this->assertSame("{$year}-00002", $second);
    }

    public function test_student_number_generation_accounts_for_archived_students(): void
    {
        $generator = app(StudentNumberGeneratorService::class);
        $student = Student::factory()->create(['student_number' => $generator->generate()]);
        $student->delete();

        $next = $generator->generate();

        $this->assertNotSame($student->student_number, $next);
    }

    public function test_student_list_can_be_searched_by_name(): void
    {
        $psychometrician = $this->psychometrician();
        Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        Student::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

        $response = $this->actingAs($psychometrician)->get(route('students.index', ['search' => 'Findme']));

        $response->assertOk();
        $response->assertSee('Unique');
        $response->assertDontSee('Someone Else');
    }

    public function test_student_list_normal_request_returns_the_full_page(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->get(route('students.index'));

        $response->assertOk();
        $response->assertViewIs('students.index');
    }

    public function test_student_list_live_search_request_returns_only_the_table_partial(): void
    {
        $psychometrician = $this->psychometrician();
        Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        Student::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

        $response = $this->actingAs($psychometrician)
            ->get(route('students.index', ['search' => 'Findme']), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('students._table');
        $response->assertSee('Unique');
        $response->assertDontSee('Someone Else');

        // The partial must not carry the surrounding page chrome — only a
        // live-search fetch (not a normal page load) should ever receive
        // this trimmed-down response.
        $response->assertDontSee('Student Management');
    }

    public function test_student_profile_shows_their_assessment_history_with_no_search_field(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();

        $assessment = Assessment::factory()->create(['student_id' => $student->id]);
        DassResult::factory()->create(['assessment_id' => $assessment->id]);
        $otherAssessment = Assessment::factory()->create(['student_id' => $otherStudent->id]);
        DassResult::factory()->create(['assessment_id' => $otherAssessment->id]);

        $response = $this->actingAs($psychometrician)->get(route('students.show', $student));

        $response->assertOk();
        $response->assertViewHas('assessments', fn ($paginator) => $paginator->total() === 1
            && $paginator->first()->is($assessment));

        // Report buttons that used to live only on the separate
        // assessments.index page now live directly on the profile.
        $response->assertSee(route('reports.student-history.print', ['student_number' => $student->student_number]), false);
        $response->assertSee(route('reports.student-history.pdf', ['student_number' => $student->student_number]), false);

        // The redundant "View assessment history" link is gone, and this
        // page never needs a search box -- the student is already fixed.
        $response->assertDontSee('View assessment history');
        $response->assertDontSee('name="search"', false);
    }

    public function test_student_profile_hides_report_buttons_when_there_is_no_history_yet(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create();

        $response = $this->actingAs($psychometrician)->get(route('students.show', $student));

        $response->assertOk();
        $response->assertSee('No assessments yet for this student.');
        $response->assertDontSee('Print Report');
        $response->assertDontSee('Download PDF');
    }
}
