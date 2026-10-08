<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\CounselingSession;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * The Students page's Archived tab and Restore (Psychometrician only).
 */
class ArchivedStudentsTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private function archivedStudent(array $attributes = []): Student
    {
        $student = Student::factory()->create([
            'first_name' => 'Ana',
            'middle_name' => 'Reyes',
            'last_name' => 'Lopez',
            ...$attributes,
        ]);
        $student->delete();

        return $student;
    }

    public function test_archived_tab_lists_only_archived_students_and_is_not_captured_as_a_student_id(): void
    {
        $active = Student::factory()->create(['first_name' => 'Active', 'last_name' => 'Person']);
        $archived = $this->archivedStudent();
        Assessment::factory()->count(2)->create(['student_id' => $archived->id]);

        $response = $this->actingAs($this->psychometrician())->get('/students/archived');

        $response->assertOk()->assertViewIs('students.archived.index');
        $this->assertSame([$archived->id], $response->viewData('students')->getCollection()->modelKeys());
        $this->assertSame(2, $response->viewData('students')->first()->assessments_count);
        $response->assertSee($archived->student_number)->assertDontSee($active->student_number);

        $this->assertNotContains($archived->id, $this->get(route('students.index'))->viewData('students')->getCollection()->modelKeys());
    }

    public function test_archived_tab_live_search_returns_the_table_partial(): void
    {
        $this->archivedStudent();
        $this->archivedStudent(['first_name' => 'Ben', 'last_name' => 'Cruz']);

        $response = $this->actingAs($this->psychometrician())
            ->withHeader('X-Live-Search', 'true')
            ->get(route('students.archived.index', ['search' => 'Ben']));

        $response->assertOk()->assertViewIs('students.archived._table');
        $this->assertCount(1, $response->viewData('students'));
    }

    public function test_guidance_counselor_cannot_see_or_restore_archived_students(): void
    {
        $archived = $this->archivedStudent();
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('students.archived.index'))->assertForbidden();
        $this->patch(route('students.restore', $archived))->assertForbidden();

        $this->assertSoftDeleted($archived);
    }

    public function test_restoring_keeps_the_student_number_and_brings_the_student_back(): void
    {
        $archived = $this->archivedStudent();
        $assessment = Assessment::factory()->create(['student_id' => $archived->id]);
        $number = $archived->student_number;

        $response = $this->actingAs($this->psychometrician())->patch(route('students.restore', $archived));

        $response->assertRedirect(route('students.archived.index'))->assertSessionHasNoErrors();
        $this->assertStringContainsString('return to their original place in the Students list', session('status'));

        $restored = $archived->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertSame($number, $restored->student_number);

        $this->assertContains($archived->id, $this->get(route('students.index'))->viewData('students')->getCollection()->modelKeys());
        $this->get(route('students.show', $archived))->assertOk()->assertSee($number);
        $this->assertSame([$assessment->id], $this->get(route('students.show', $archived))->viewData('assessments')->getCollection()->modelKeys());
        $this->get(route('assessments.create.retake', $archived))->assertRedirect();
    }

    public function test_a_restored_student_can_get_new_counseling_sessions_again(): void
    {
        $archived = $this->archivedStudent();
        $this->actingAs($this->psychometrician())->patch(route('students.restore', $archived));
        $sessionAt = now()->addDay();

        $this->actingAs($this->guidanceCounselor())->post(route('counseling-sessions.store'), [
            'student_id' => $archived->id,
            'session_date' => $sessionAt->format('Y-m-d'),
            'session_time' => $sessionAt->format('H:i'),
            'session_notes' => 'First session after restore.',
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => false,
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, CounselingSession::query()->where('student_id', $archived->id)->count());
    }

    public function test_restore_writes_exactly_one_restore_entry_with_the_reason_and_shows_it(): void
    {
        $archived = $this->archivedStudent();
        $archivedAt = $archived->deleted_at->toDateTimeString();
        $psychometrician = $this->psychometrician();
        $before = (int) AuditLog::query()->max('id');

        $this->actingAs($psychometrician)->patch(route('students.restore', $archived), [
            'reason' => 'Returned for the second semester.',
        ]);

        $entries = AuditLog::query()->where('id', '>', $before)->get();
        $this->assertSame(['Restore'], $entries->pluck('action')->all());

        $entry = $entries->first();
        $this->assertSame('Student Information', $entry->module);
        $this->assertSame($archived->id, $entry->record_id);
        $this->assertSame($psychometrician->id, $entry->user_id);
        $this->assertSame(['archived_at' => $archivedAt], $entry->old_values);
        $this->assertSame('Returned for the second semester.', $entry->new_values['restore_reason']);
        $this->assertSame($archived->student_number, $entry->new_values['student_number']);
        $this->assertNull($entry->new_values['deleted_at']);

        $this->get(route('audit-logs.show', $entry))
            ->assertOk()
            ->assertSee('Reason')
            ->assertSee('Returned for the second semester.');
    }

    public function test_the_reason_is_optional(): void
    {
        $archived = $this->archivedStudent();

        $this->actingAs($this->psychometrician())
            ->patch(route('students.restore', $archived), ['reason' => ''])
            ->assertSessionHasNoErrors();

        $this->assertFalse($archived->fresh()->trashed());
        $entry = AuditLog::query()->where('action', 'Restore')->firstOrFail();
        $this->assertArrayHasKey('restore_reason', $entry->new_values);
        $this->assertNull($entry->new_values['restore_reason']);
        $this->get(route('audit-logs.show', $entry))->assertSee('None given');
    }

    public function test_the_reason_is_limited_to_255_characters(): void
    {
        $archived = $this->archivedStudent();
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)
            ->patch(route('students.restore', $archived), ['reason' => Str::repeat('a', 256)])
            ->assertSessionHasErrors(['reason' => 'The reason may not be longer than 255 characters.']);
        $this->assertSoftDeleted($archived);
        $this->assertSame(0, AuditLog::query()->where('action', 'Restore')->count());

        $this->patch(route('students.restore', $archived), ['reason' => Str::repeat('a', 255)])
            ->assertSessionHasNoErrors();
        $this->assertFalse($archived->fresh()->trashed());

        $this->get(route('students.archived.index'))->assertSee('maxlength="255"', false);
    }

    public function test_restore_is_blocked_while_an_active_student_has_the_same_name(): void
    {
        $archived = $this->archivedStudent();
        // Registered after the archive, e.g. through the wizard's "create a
        // new record" option on the archived-match warning. Middle name
        // differs but shares the initial, which is all the rule compares.
        $active = Student::factory()->create(['first_name' => ' ana ', 'middle_name' => 'Rosa', 'last_name' => 'LOPEZ']);
        $psychometrician = $this->psychometrician();

        $list = $this->actingAs($psychometrician)->get(route('students.archived.index'));
        $list->assertSee('Active record exists:')
            ->assertSee($active->student_number)
            ->assertSee('cursor-not-allowed', false)
            ->assertDontSee(e(route('students.restore', $archived)), false);

        $this->from(route('students.archived.index'))
            ->patch(route('students.restore', $archived))
            ->assertRedirect(route('students.archived.index'))
            ->assertSessionHasErrors(['restore' => "{$archived->full_name} ({$archived->student_number}) can't be restored: an active student with the same name already exists ({$active->student_number}). Use that record instead — records are not merged."]);

        $this->assertSoftDeleted($archived);
        $this->assertSame(0, AuditLog::query()->where('action', 'Restore')->count());
    }

    public function test_of_two_archived_students_with_the_same_name_only_the_first_can_be_restored(): void
    {
        $first = $this->archivedStudent();
        $second = $this->archivedStudent();
        $psychometrician = $this->psychometrician();

        $this->actingAs($psychometrician)->patch(route('students.restore', $first))->assertSessionHasNoErrors();

        $this->get(route('students.archived.index'))->assertSee('Active record exists:')->assertSee($first->student_number);
        $this->patch(route('students.restore', $second))->assertSessionHasErrors('restore');

        $this->assertFalse($first->fresh()->trashed());
        $this->assertSoftDeleted($second);
    }

    public function test_restoring_an_already_active_student_changes_and_logs_nothing(): void
    {
        $student = Student::factory()->create();
        $psychometrician = $this->psychometrician();
        $before = (int) AuditLog::query()->max('id');

        $this->actingAs($psychometrician)
            ->patch(route('students.restore', $student))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "{$student->full_name} ({$student->student_number}) is already active.");

        $this->assertSame($before, (int) AuditLog::query()->max('id'));
    }

    public function test_a_restored_students_archived_course_is_kept_in_the_edit_form(): void
    {
        $course = Course::factory()->create(['course_code' => 'BSOLD', 'course_name' => 'Old Program']);
        $archived = $this->archivedStudent(['course_id' => $course->id]);
        $course->delete();
        $psychometrician = $this->psychometrician();
        $this->actingAs($psychometrician)->patch(route('students.restore', $archived));

        $edit = $this->get(route('students.edit', $archived));
        $edit->assertOk()->assertSee('BSOLD - Old Program (archived)');
        $this->assertMatchesRegularExpression('/<option value="'.$course->id.'"\s+selected/', $edit->getContent());

        // Another student's edit form doesn't offer the archived course.
        $other = Student::factory()->create();
        $this->get(route('students.edit', $other))->assertDontSee('BSOLD');

        $this->put(route('students.update', $archived), [
            'first_name' => 'Ana',
            'middle_name' => 'Reyes',
            'last_name' => 'Lopez',
            'gender' => $archived->gender,
            'course_id' => $course->id,
            'year_level_id' => $archived->year_level_id,
            'section_id' => $archived->section_id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($course->id, $archived->fresh()->course_id);
    }
}
