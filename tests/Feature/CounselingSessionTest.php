<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CounselingSession;
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
}
