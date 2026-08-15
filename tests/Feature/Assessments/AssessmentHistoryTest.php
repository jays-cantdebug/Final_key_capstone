<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class AssessmentHistoryTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_assessment_list_can_be_searched_by_student_name(): void
    {
        $psychometrician = $this->psychometrician();
        $findMe = Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        $someoneElse = Student::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);
        Assessment::factory()->create(['student_id' => $findMe->id]);
        Assessment::factory()->create(['student_id' => $someoneElse->id]);

        $response = $this->actingAs($psychometrician)->get(route('assessments.index', ['search' => 'Findme']));

        $response->assertOk();
        $response->assertSee('Unique');
        $response->assertDontSee('Someone');
    }

    public function test_assessment_list_normal_request_returns_the_full_page(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->get(route('assessments.index'));

        $response->assertOk();
        $response->assertViewIs('assessments.index');
    }

    public function test_assessment_list_live_search_request_returns_only_the_table_partial(): void
    {
        $psychometrician = $this->psychometrician();
        $findMe = Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        $someoneElse = Student::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);
        Assessment::factory()->create(['student_id' => $findMe->id]);
        Assessment::factory()->create(['student_id' => $someoneElse->id]);

        $response = $this->actingAs($psychometrician)
            ->get(route('assessments.index', ['search' => 'Findme']), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('assessments._table');
        $response->assertSee('Unique');
        $response->assertDontSee('Someone');

        // The partial must not carry the surrounding page chrome — only a
        // live-search fetch (not a normal page load) should ever receive
        // this trimmed-down response.
        $response->assertDontSee('Assessment History');
    }

    public function test_assessment_list_live_search_preserves_the_student_number_filter(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create(['first_name' => 'Unique', 'last_name' => 'Findme']);
        $otherStudent = Student::factory()->create();
        Assessment::factory()->create(['student_id' => $student->id]);
        Assessment::factory()->create(['student_id' => $otherStudent->id]);

        $response = $this->actingAs($psychometrician)
            ->get(route('assessments.index', ['student_number' => $student->student_number]), ['X-Live-Search' => 'true']);

        $response->assertOk();
        $response->assertViewIs('assessments._table');
        $response->assertSee('Unique');
        $response->assertDontSee($otherStudent->student_number);
    }
}
