<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

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
}
