<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Http\Controllers\AssessmentWizardController;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * New Assessment Step 1 refuses a student who already has an active
 * record (pointing at Take Again instead), warns about an archived one,
 * and the final save re-checks so a stale session, a second tab or a
 * double submit can't register the same student twice.
 */
class DuplicateStudentTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    /** @var array{course_id: int, year_level_id: int, section_id: int} */
    private array $lookupIds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lookupIds = [
            'course_id' => Course::factory()->create()->id,
            'year_level_id' => YearLevel::factory()->create()->id,
            'section_id' => Section::factory()->create()->id,
        ];
    }

    private function existingStudent(array $attributes = []): Student
    {
        return Student::factory()->create([
            'first_name' => 'Juan',
            'middle_name' => 'D.',
            'last_name' => 'Cruz',
            ...$attributes,
        ]);
    }

    private function archivedStudent(array $attributes = []): Student
    {
        $student = $this->existingStudent($attributes);
        $student->delete();

        return $student;
    }

    private function postStepOne(array $overrides = []): TestResponse
    {
        return $this->post(route('assessments.create.student'), [
            'first_name' => 'Juan',
            'middle_name' => 'D.',
            'last_name' => 'Cruz',
            'gender' => 'Male',
            'privacy_consent' => '1',
            ...$this->lookupIds,
            ...$overrides,
        ]);
    }

    private function answerQuestionnaireAndSave(int $stressRaw = 1): TestResponse
    {
        $version = $this->createActiveQuestionnaireVersion();
        $responses = $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: $stressRaw);

        $this->post(route('assessments.create.questionnaire.store'), ['responses' => $responses])
            ->assertRedirect(route('assessments.create.result'));

        return $this->reviewAndSaveAssessment();
    }

    private function archivedMatchAuditCount(): int
    {
        return AuditLog::query()->where('action', 'Archived Match Confirmed')->count();
    }

    public function test_an_active_student_with_the_same_name_blocks_step_one(): void
    {
        $existing = $this->existingStudent();

        $response = $this->actingAs($this->psychometrician())->postStepOne();

        $response->assertRedirect(route('assessments.create'));
        $this->assertNull(session('assessment_wizard.student_data'));
        $this->assertDatabaseCount('students', 1);

        $this->get(route('assessments.create'))
            ->assertOk()
            ->assertSee('This student already exists')
            ->assertSee($existing->student_number)
            ->assertSee("A second record can't be created for the same name.", false)
            ->assertSee(route('assessments.create.retake', $existing), false)
            ->assertSee(route('students.show', $existing), false)
            ->assertSee('aria-label="Dismiss"', false);
    }

    public function test_matching_ignores_case_and_surrounding_whitespace(): void
    {
        $this->existingStudent();

        $this->actingAs($this->psychometrician())
            ->postStepOne(['first_name' => '  juan ', 'middle_name' => 'd.', 'last_name' => 'CRUZ  '])
            ->assertRedirect(route('assessments.create'));

        $this->assertNull(session('assessment_wizard.student_data'));
    }

    public function test_matching_collapses_whitespace_inside_names_on_both_sides(): void
    {
        $this->existingStudent(['last_name' => 'Dela  Cruz']);

        $this->actingAs($this->psychometrician())
            ->postStepOne(['last_name' => 'Dela   Cruz'])
            ->assertRedirect(route('assessments.create'));

        $this->assertNull(session('assessment_wizard.student_data'));
    }

    public function test_a_different_middle_initial_is_not_a_match(): void
    {
        $this->existingStudent(['middle_name' => 'P.']);

        $this->actingAs($this->psychometrician())
            ->postStepOne()
            ->assertRedirect(route('assessments.create.questionnaire'));

        $this->assertSame('Cruz', session('assessment_wizard.student_data.last_name'));
    }

    public function test_a_stored_record_with_no_middle_name_is_not_a_match(): void
    {
        $this->existingStudent(['middle_name' => null]);

        $this->actingAs($this->psychometrician())
            ->postStepOne()
            ->assertRedirect(route('assessments.create.questionnaire'));
    }

    public function test_course_year_level_section_and_gender_are_not_compared(): void
    {
        $this->existingStudent([
            'gender' => 'Female',
            'course_id' => Course::factory()->create()->id,
            'year_level_id' => YearLevel::factory()->create()->id,
            'section_id' => Section::factory()->create()->id,
        ]);

        $this->actingAs($this->psychometrician())
            ->postStepOne()
            ->assertRedirect(route('assessments.create'));

        $this->assertNull(session('assessment_wizard.student_data'));
    }

    public function test_several_matches_list_one_row_per_student_with_a_students_search_link(): void
    {
        $first = $this->existingStudent();
        $second = $this->existingStudent(['middle_name' => 'Dela']);

        $this->actingAs($this->psychometrician())->postStepOne();

        $this->get(route('assessments.create'))
            ->assertSee('Students with this name already exist')
            ->assertSee('2 students named Juan D. Cruz are already registered.')
            ->assertSee(route('assessments.create.retake', $first), false)
            ->assertSee(route('assessments.create.retake', $second), false)
            ->assertSee(e(route('students.index', ['search' => 'Juan Cruz'])), false);
    }

    public function test_an_active_match_cannot_be_overridden(): void
    {
        $this->existingStudent();

        $this->actingAs($this->psychometrician())
            ->postStepOne(['archived_warning_shown' => '1', 'confirm_archived_match' => '1'])
            ->assertRedirect(route('assessments.create'));

        $this->assertNull(session('assessment_wizard.student_data'));
        $this->assertDatabaseCount('students', 1);
    }

    public function test_an_archived_match_warns_and_requires_the_confirm_box(): void
    {
        $archived = $this->archivedStudent();
        $this->actingAs($this->psychometrician());

        // First submit: the warning appears, without an error on a
        // checkbox the user hasn't seen yet.
        $this->postStepOne()
            ->assertRedirect(route('assessments.create'))
            ->assertSessionDoesntHaveErrors('confirm_archived_match');

        $this->assertNull(session('assessment_wizard.student_data'));

        $this->get(route('assessments.create'))
            ->assertSee('A student with this name was archived')
            ->assertSee("Archived students can't use Take Again, so continuing will create a new, separate record. Their earlier assessments stay in Assessment History under {$archived->student_number}.", false)
            ->assertSee(e(route('assessments.index', ['student_number' => $archived->student_number])), false)
            ->assertSee('I understand. Create a new student record.')
            ->assertDontSee(route('assessments.create.retake', $archived), false);

        // Continue without ticking the box: still refused, now with an error.
        $this->postStepOne(['archived_warning_shown' => '1'])
            ->assertRedirect(route('assessments.create'))
            ->assertSessionHasErrors(['confirm_archived_match' => AssessmentWizardController::CONFIRM_ARCHIVED_MATCH_MESSAGE]);

        $this->assertNull(session('assessment_wizard.student_data'));
    }

    public function test_a_confirmed_archived_match_creates_a_new_student_and_one_audit_entry(): void
    {
        $this->seedOfficialThresholds();
        $archived = $this->archivedStudent();
        $this->actingAs($this->psychometrician());

        $this->postStepOne(['archived_warning_shown' => '1', 'confirm_archived_match' => '1'])
            ->assertRedirect(route('assessments.create.questionnaire'));

        $this->assertSame([$archived->id], session('assessment_wizard.acknowledged_archived_ids'));

        $this->answerQuestionnaireAndSave()->assertSessionHasNoErrors();

        $newStudent = Student::query()->whereKeyNot($archived->id)->sole();
        $this->assertSame('Juan', $newStudent->first_name);
        $this->assertDatabaseHas('assessments', ['student_id' => $newStudent->id]);

        $this->assertSame(1, $this->archivedMatchAuditCount());
        $log = AuditLog::query()->where('action', 'Archived Match Confirmed')->sole();
        $this->assertSame('Student Information', $log->module);
        $this->assertSame($newStudent->id, $log->record_id);
        $this->assertSame($newStudent->student_number, $log->new_values['student_number']);
        $this->assertSame($archived->id, $log->new_values['archived_matches'][0]['id']);
        $this->assertSame($archived->student_number, $log->new_values['archived_matches'][0]['student_number']);
    }

    public function test_an_abandoned_archived_confirmation_writes_nothing(): void
    {
        $this->archivedStudent();

        $this->actingAs($this->psychometrician())
            ->postStepOne(['archived_warning_shown' => '1', 'confirm_archived_match' => '1'])
            ->assertRedirect(route('assessments.create.questionnaire'));

        $this->assertDatabaseCount('students', 1);
        $this->assertSame(0, $this->archivedMatchAuditCount());
    }

    public function test_an_active_match_takes_priority_over_an_archived_one(): void
    {
        $this->existingStudent();
        $this->archivedStudent();

        $this->actingAs($this->psychometrician())->postStepOne()->assertRedirect(route('assessments.create'));

        $this->get(route('assessments.create'))
            ->assertSee('This student already exists')
            ->assertDontSee('was archived')
            ->assertDontSee('I understand. Create a new student record.');
    }

    public function test_final_save_refuses_a_student_registered_after_step_one(): void
    {
        $this->seedOfficialThresholds();
        $this->guidanceCounselor();
        $this->actingAs($this->psychometrician());

        $this->postStepOne()->assertRedirect(route('assessments.create.questionnaire'));

        // Registered meanwhile, e.g. from another tab.
        $registeredMeanwhile = $this->existingStudent();

        // Severe stress: would create a flag + notification if it saved.
        $response = $this->answerQuestionnaireAndSave(stressRaw: 15);

        $response->assertRedirect(route('assessments.create'));
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseCount('dass_results', 0);
        $this->assertDatabaseCount('prediction_feedback', 0);
        $this->assertDatabaseCount('flagged_cases', 0);
        $this->assertDatabaseCount('system_notifications', 0);
        $this->assertNull(session('assessment_wizard'));

        $this->get(route('assessments.create'))
            ->assertSee('This student was registered while you were working')
            ->assertSee('while this assessment was in progress, so nothing was saved.')
            ->assertSee(route('assessments.create.retake', $registeredMeanwhile), false);
    }

    public function test_final_save_accepts_an_archived_match_that_appeared_after_step_one(): void
    {
        $this->seedOfficialThresholds();
        $this->actingAs($this->psychometrician());

        $this->postStepOne()->assertRedirect(route('assessments.create.questionnaire'));

        // Archived by someone else between Step 1 and the save: accepted,
        // never confirmed, so no confirmation audit entry.
        $this->archivedStudent();

        $this->answerQuestionnaireAndSave()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('assessments', 1);
        $this->assertSame(0, $this->archivedMatchAuditCount());
    }

    public function test_a_new_step_one_clears_an_earlier_archived_confirmation(): void
    {
        $this->seedOfficialThresholds();
        $this->archivedStudent();
        $this->actingAs($this->psychometrician());

        $this->postStepOne(['archived_warning_shown' => '1', 'confirm_archived_match' => '1']);
        $this->assertNotNull(session('assessment_wizard.acknowledged_archived_ids'));

        $this->postStepOne(['first_name' => 'Ana', 'middle_name' => 'R.', 'last_name' => 'Lopez'])
            ->assertRedirect(route('assessments.create.questionnaire'));
        $this->assertNull(session('assessment_wizard.acknowledged_archived_ids'));

        $this->answerQuestionnaireAndSave()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('assessments', 1);
        $this->assertSame(0, $this->archivedMatchAuditCount());
    }

    public function test_take_again_is_not_blocked_by_its_own_name(): void
    {
        $this->seedOfficialThresholds();
        $existing = $this->existingStudent();
        $this->actingAs($this->psychometrician());

        $this->get(route('assessments.create.retake', $existing))
            ->assertRedirect(route('assessments.create.questionnaire'));

        $version = $this->createActiveQuestionnaireVersion();
        $this->post(route('assessments.create.questionnaire.store'), [
            'responses' => $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: 1),
            'privacy_consent' => '1',
        ])->assertRedirect(route('assessments.create.result'));

        $this->reviewAndSaveAssessment()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('assessments', ['student_id' => $existing->id]);
    }

    public function test_a_regular_step_one_after_an_abandoned_take_again_is_still_checked(): void
    {
        $existing = $this->existingStudent();
        $this->actingAs($this->psychometrician());

        $this->get(route('assessments.create.retake', $existing));

        $this->postStepOne()->assertRedirect(route('assessments.create'));

        $this->assertNull(session('assessment_wizard.existing_student_id'));
        $this->assertNull(session('assessment_wizard.student_data'));
    }

    public function test_guidance_counselor_cannot_post_step_one(): void
    {
        $this->actingAs($this->guidanceCounselor())->postStepOne()->assertForbidden();
    }

    public function test_the_final_save_form_guards_against_a_double_submit(): void
    {
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $this->actingAs($this->psychometrician());

        $this->postStepOne();
        $this->post(route('assessments.create.questionnaire.store'), [
            'responses' => $this->buildResponses($version, depressionRaw: 1, anxietyRaw: 1, stressRaw: 1),
        ]);

        $this->get(route('assessments.create.result'))
            ->assertOk()
            ->assertSee('x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true;', false)
            ->assertSee('button.disabled = true', false);
    }
}
