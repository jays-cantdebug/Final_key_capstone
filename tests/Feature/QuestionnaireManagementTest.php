<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DassQuestion;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

class QuestionnaireManagementTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_psychometrician_can_create_a_questionnaire(): void
    {
        $psychometrician = $this->psychometrician();

        $response = $this->actingAs($psychometrician)->post(route('questionnaires.store'), [
            'title' => 'DASS-21',
            'description' => 'Standard scale',
            'status' => Questionnaire::STATUS_ACTIVE,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questionnaires', ['title' => 'DASS-21']);
    }

    public function test_psychometrician_can_create_a_draft_version_for_a_questionnaire(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();

        $response = $this->actingAs($psychometrician)->post(route('questionnaires.versions.store', $questionnaire), [
            'version_number' => 1,
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('questionnaire_versions', [
            'questionnaire_id' => $questionnaire->id,
            'status' => QuestionnaireVersion::STATUS_DRAFT,
        ]);
    }

    public function test_a_version_cannot_be_activated_without_at_least_one_question(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors('version');
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_activating_a_version_archives_whatever_else_was_active(): void
    {
        $psychometrician = $this->psychometrician();
        $currentlyActive = QuestionnaireVersion::factory()->active()->create();
        $newVersion = QuestionnaireVersion::factory()->create();
        DassQuestion::factory()->create(['questionnaire_version_id' => $newVersion->id]);

        $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$newVersion->questionnaire, $newVersion])
        );

        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $newVersion->fresh()->status);
        $this->assertSame(QuestionnaireVersion::STATUS_ARCHIVED, $currentlyActive->fresh()->status);
    }

    /**
     * Regression test: an Archived version previously had no UI path back
     * to Active. activate() itself places no restriction on the version's
     * current status (only that it has questions), so a previously
     * Archived version can be reactivated directly.
     */
    public function test_an_archived_version_can_be_reactivated(): void
    {
        $psychometrician = $this->psychometrician();
        $archived = QuestionnaireVersion::factory()->archived()->create();
        DassQuestion::factory()->create(['questionnaire_version_id' => $archived->id]);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$archived->questionnaire, $archived])
        );

        $response->assertRedirect();
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $archived->fresh()->status);
    }

    public function test_a_draft_version_can_be_deleted_but_an_active_version_cannot(): void
    {
        $psychometrician = $this->psychometrician();
        $draft = QuestionnaireVersion::factory()->create();
        $active = QuestionnaireVersion::factory()->active()->create();

        $this->actingAs($psychometrician)
            ->delete(route('questionnaires.versions.destroy', [$draft->questionnaire, $draft]))
            ->assertRedirect();
        $this->assertSoftDeleted('questionnaire_versions', ['id' => $draft->id]);

        $response = $this->actingAs($psychometrician)
            ->delete(route('questionnaires.versions.destroy', [$active->questionnaire, $active]));
        $response->assertSessionHasErrors('version');
        $this->assertDatabaseHas('questionnaire_versions', ['id' => $active->id, 'deleted_at' => null]);
    }

    public function test_guidance_counselor_cannot_access_questionnaire_management(): void
    {
        $counselor = $this->guidanceCounselor();

        $this->actingAs($counselor)->get(route('questionnaires.index'))->assertForbidden();
    }

    public function test_a_questionnaire_with_no_assessments_can_be_archived(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id]);

        $response = $this->actingAs($psychometrician)->delete(route('questionnaires.destroy', $questionnaire));

        $response->assertRedirect(route('questionnaires.index'));
        $this->assertSoftDeleted('questionnaires', ['id' => $questionnaire->id]);
    }

    public function test_a_questionnaire_with_a_version_used_by_an_assessment_cannot_be_archived(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        $version = QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id]);
        Assessment::factory()->create(['questionnaire_version_id' => $version->id]);

        $response = $this->actingAs($psychometrician)->delete(route('questionnaires.destroy', $questionnaire));

        $response->assertSessionHasErrors('questionnaire');
        $this->assertDatabaseHas('questionnaires', ['id' => $questionnaire->id, 'deleted_at' => null]);
    }
}
