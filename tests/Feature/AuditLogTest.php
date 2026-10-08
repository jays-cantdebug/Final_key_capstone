<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\CounselingSession;
use App\Models\Course;
use App\Models\DassQuestion;
use App\Models\DassResponse;
use App\Models\DassResult;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use App\Observers\AuditableObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    /** What the audit log stores in place of any encrypted field. */
    private const MARKER = '[changed]';

    public function test_creating_a_student_automatically_records_an_audit_log_entry(): void
    {
        $student = Student::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Student Information',
            'action' => 'Create',
            'record_id' => $student->id,
        ]);
    }

    public function test_updating_a_student_records_an_update_entry_with_old_and_new_values(): void
    {
        $student = Student::factory()->create(['first_name' => 'Before']);
        $student->update(['first_name' => 'After']);

        $log = AuditLog::query()->where('module', 'Student Information')->where('action', 'Update')->first();

        $this->assertNotNull($log);
        $this->assertSame('Before', $log->old_values['first_name']);
        $this->assertSame('After', $log->new_values['first_name']);
    }

    public function test_assessment_create_update_and_delete_all_log_under_the_same_module_name(): void
    {
        $assessment = Assessment::factory()->create();
        $assessment->update(['submitted_at' => now()->subDay()]);
        $assessment->delete();

        // Create was always hardcoded to 'Assessments' (plural); Update and
        // Delete previously fell through to class_basename() and logged
        // under 'Assessment' (singular) instead, silently splitting one
        // model's audit trail across two entries in the Module filter.
        $this->assertSame(
            3,
            AuditLog::query()->where('module', 'Assessments')->where('record_id', $assessment->id)->count()
        );
        $this->assertSame(
            0,
            AuditLog::query()->where('module', 'Assessment')->where('record_id', $assessment->id)->count()
        );
    }

    public function test_psychometrician_can_view_the_audit_log_index(): void
    {
        $psychometrician = $this->psychometrician();
        Student::factory()->create();

        $this->actingAs($psychometrician)->get(route('audit-logs.index'))->assertOk();
    }

    public function test_audit_logs_can_be_filtered_by_module(): void
    {
        $psychometrician = $this->psychometrician();
        Student::factory()->create();
        Course::factory()->create();

        $response = $this->actingAs($psychometrician)->get(route('audit-logs.index', ['module' => 'Student Information']));

        $response->assertOk();
        $response->assertSee('Student Information');
    }

    public function test_psychometrician_can_view_a_single_audit_log_entry(): void
    {
        $psychometrician = $this->psychometrician();
        $student = Student::factory()->create();
        $log = AuditLog::query()->where('record_id', $student->id)->firstOrFail();

        $this->actingAs($psychometrician)->get(route('audit-logs.show', $log))->assertOk();
    }

    public function test_guidance_counselor_cannot_access_audit_logs(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_counseling_session_notes_never_reach_the_audit_log_in_any_form(): void
    {
        $session = CounselingSession::factory()->create(['session_notes' => 'First secret note']);
        $session->update(['session_notes' => 'Second secret note']);
        $session->delete();

        $logs = AuditLog::query()->where('module', 'Counseling Sessions')->where('record_id', $session->id)->orderBy('id')->get();
        $this->assertSame(['Create', 'Update', 'Delete'], $logs->pluck('action')->all());

        [$create, $update, $delete] = $logs->all();
        $this->assertSame(self::MARKER, $create->new_values['session_notes']);
        $this->assertSame(self::MARKER, $update->old_values['session_notes']);
        $this->assertSame(self::MARKER, $update->new_values['session_notes']);
        $this->assertSame(self::MARKER, $delete->old_values['session_notes']);

        // Neither the plaintext nor a Laravel ciphertext payload (base64
        // JSON, always starting "eyJ") is stored anywhere in those rows.
        $stored = DB::table('audit_logs')->whereIn('id', $logs->modelKeys())->get(['old_values', 'new_values'])->toJson();
        $this->assertStringNotContainsString('secret note', $stored);
        $this->assertStringNotContainsString('eyJ', $stored);
    }

    public function test_an_update_that_leaves_the_notes_alone_does_not_mention_them(): void
    {
        $session = CounselingSession::factory()->create();
        $session->update(['session_status' => CounselingSession::STATUS_CANCELLED]);

        $update = AuditLog::query()->where('module', 'Counseling Sessions')->where('action', 'Update')->firstOrFail();

        $this->assertArrayNotHasKey('session_notes', $update->old_values);
        $this->assertArrayNotHasKey('session_notes', $update->new_values);
        $this->assertSame(CounselingSession::STATUS_SCHEDULED, $update->old_values['session_status']);
    }

    public function test_encrypted_integer_scores_and_answers_are_masked_too(): void
    {
        // DassResult and DassResponse aren't observed today; this pins the
        // observer's masking for them in case they ever are.
        $result = DassResult::factory()->create();
        $answer = DassResponse::query()->create([
            'assessment_id' => $result->assessment_id,
            'dass_question_id' => DassQuestion::factory()->create()->id,
            'answer_value' => 3,
        ]);
        $observer = app(AuditableObserver::class);

        $observer->created($result);
        $observer->created($answer);
        $result->update(['stress_final_score' => 40]);
        $observer->updated($result);

        $logs = AuditLog::query()->whereIn('module', ['DassResult', 'DassResponse'])->orderBy('id')->get();
        $this->assertCount(3, $logs);

        foreach (['depression', 'anxiety', 'stress'] as $subscale) {
            $this->assertSame(self::MARKER, $logs[0]->new_values["{$subscale}_raw_score"]);
            $this->assertSame(self::MARKER, $logs[0]->new_values["{$subscale}_final_score"]);
        }
        $this->assertSame(self::MARKER, $logs[1]->new_values['answer_value']);
        $this->assertSame(self::MARKER, $logs[2]->old_values['stress_final_score']);
        $this->assertSame(self::MARKER, $logs[2]->new_values['stress_final_score']);
        $this->assertArrayNotHasKey('depression_final_score', $logs[2]->old_values);
    }

    public function test_archiving_logs_archive_and_every_other_removal_logs_delete(): void
    {
        $archived = [
            'Student Information' => Student::factory()->create(),
            'Course Management' => Course::factory()->create(),
            'Year Level Management' => YearLevel::factory()->create(),
            'Section Management' => Section::factory()->create(),
            'Questionnaire Management' => Questionnaire::factory()->create(),
        ];
        foreach ($archived as $model) {
            $model->delete();
        }

        $deleted = [
            'Counseling Sessions' => CounselingSession::factory()->create(),
            'Questionnaire Management' => QuestionnaireVersion::factory()->create(),
            'Assessments' => Assessment::factory()->create(),
        ];
        foreach ($deleted as $model) {
            $model->delete();
        }

        foreach ($archived as $module => $model) {
            $this->assertDatabaseHas('audit_logs', ['module' => $module, 'action' => 'Archive', 'record_id' => $model->id]);
        }
        foreach ($deleted as $module => $model) {
            $this->assertDatabaseHas('audit_logs', ['module' => $module, 'action' => 'Delete', 'record_id' => $model->id]);
        }
        // Counted rather than matched per record: the version and the
        // questionnaire share a module and can share an id.
        $this->assertSame(5, AuditLog::query()->where('action', 'Archive')->count());
        $this->assertSame(3, AuditLog::query()->where('action', 'Delete')->count());

        // A real hard delete of an archivable model is still a Delete.
        $student = Student::factory()->create();
        $student->forceDelete();
        $this->assertDatabaseHas('audit_logs', ['module' => 'Student Information', 'action' => 'Delete', 'record_id' => $student->id]);
    }

    public function test_action_filter_keeps_old_delete_entries_apart_from_new_archive_entries(): void
    {
        $psychometrician = $this->psychometrician();
        // An entry written before the cutover, when archiving logged "Delete".
        $old = AuditLog::query()->create(['module' => 'Student Information', 'action' => 'Delete', 'record_id' => 999]);
        $student = Student::factory()->create();
        $this->actingAs($psychometrician)->delete(route('students.destroy', $student));
        $new = AuditLog::query()->where('action', 'Archive')->where('record_id', $student->id)->firstOrFail();

        $archive = $this->get(route('audit-logs.index', ['action' => 'Archive']));
        $archive->assertOk()->assertSee('<option value="Archive"', false)->assertSee('<option value="Delete"', false);
        $this->assertSame([$new->id], $archive->viewData('logs')->getCollection()->modelKeys());

        $delete = $this->get(route('audit-logs.index', ['action' => 'Delete']));
        $this->assertSame([$old->id], $delete->viewData('logs')->getCollection()->modelKeys());
    }
}
