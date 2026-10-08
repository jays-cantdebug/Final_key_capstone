<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Questionnaire;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Students, courses, year levels, sections and questionnaires are archived
 * (soft-deleted), so the UI and the audit log call it "Archive"; counseling
 * sessions, versions and questions keep "Delete".
 */
class ArchiveAndDeleteTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_student_archive_button_and_dialog_say_what_happens(): void
    {
        Student::factory()->create();

        $response = $this->actingAs($this->psychometrician())->get(route('students.index'));

        $response->assertOk()
            ->assertSee('>Archive</button>', false)
            ->assertSee('Archive this student record?')
            ->assertSee('Their assessments stay in Assessment History.')
            ->assertDontSee('reviewed later')
            ->assertDontSee('Delete this student record?');
    }

    public function test_lookup_records_are_archived_and_labelled_archive(): void
    {
        $psychometrician = $this->psychometrician();
        $records = [
            'course' => [Course::factory()->create(), 'courses.destroy', 'Course archived successfully.'],
            'year level' => [YearLevel::factory()->create(), 'year-levels.destroy', 'Year level archived successfully.'],
            'section' => [Section::factory()->create(), 'sections.destroy', 'Section archived successfully.'],
        ];

        $page = $this->actingAs($psychometrician)->get(route('settings.records'));
        foreach (array_keys($records) as $label) {
            $page->assertSee("aria-label=\"Archive {$label}\"", false)
                ->assertSee("Archive this {$label}?")
                ->assertDontSee("Delete this {$label}?");
        }

        foreach ($records as [$record, $route, $message]) {
            $this->delete(route($route, $record))->assertSessionHas('status', $message);
            $this->assertSoftDeleted($record);
        }
    }

    public function test_a_lookup_used_by_an_active_student_cannot_be_archived(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->psychometrician())
            ->delete(route('courses.destroy', $student->course))
            ->assertSessionHasErrors(['course' => 'Cannot archive a course used by active students. Set its status to Inactive instead.']);

        $this->assertNotSoftDeleted($student->course);
    }

    public function test_questionnaire_archive_button_and_dialog(): void
    {
        $questionnaire = Questionnaire::factory()->create();

        $this->actingAs($this->psychometrician())
            ->get(route('questionnaires.index'))
            ->assertOk()
            ->assertSee('>Archive</button>', false)
            ->assertSee('Archive this questionnaire?')
            ->assertDontSee('Delete this questionnaire?');

        $this->delete(route('questionnaires.destroy', $questionnaire))
            ->assertSessionHas('status', 'Questionnaire archived successfully.');
        $this->assertSoftDeleted($questionnaire);
    }
}
