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

        $response->assertSessionHasErrors('activation');
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_activating_a_version_archives_whatever_else_was_active(): void
    {
        $psychometrician = $this->psychometrician();
        $currentlyActive = QuestionnaireVersion::factory()->active()->create();
        $newVersion = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($newVersion);

        $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$newVersion->questionnaire, $newVersion])
        );

        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $newVersion->fresh()->status);
        $this->assertSame(QuestionnaireVersion::STATUS_ARCHIVED, $currentlyActive->fresh()->status);
    }

    /**
     * Regression test: an Archived version previously had no UI path back
     * to Active. activate() itself places no restriction on the version's
     * current status (only that it is a valid 7/7/7 DASS-21 layout), so a
     * previously Archived version can be reactivated directly.
     */
    public function test_an_archived_version_can_be_reactivated(): void
    {
        $psychometrician = $this->psychometrician();
        $archived = QuestionnaireVersion::factory()->archived()->create();
        $this->addDassQuestions($archived);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$archived->questionnaire, $archived])
        );

        $response->assertRedirect();
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $archived->fresh()->status);
    }

    public function test_a_version_with_exactly_seven_required_questions_per_subscale_activates(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 7, anxiety: 7, stress: 7);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $version->fresh()->status);
    }

    public function test_a_subscale_with_six_questions_blocks_activation(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 6, anxiety: 7, stress: 7);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors([
            'activation' => 'This version cannot be activated. Depression has 6 questions; it needs exactly 7.',
        ]);
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_a_subscale_with_eight_questions_blocks_activation(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 7, anxiety: 7, stress: 8);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors([
            'activation' => 'This version cannot be activated. Stress has 8 questions; it needs exactly 7.',
        ]);
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_every_wrong_subscale_is_named_in_one_message(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 5, anxiety: 7, stress: 1);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors([
            'activation' => 'This version cannot be activated. Depression has 5 questions; it needs exactly 7. Stress has 1 question; it needs exactly 7.',
        ]);
    }

    public function test_a_non_required_question_blocks_activation(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version);
        $version->questions()->where('item_number', 4)->update(['is_required' => false]);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors([
            'activation' => 'This version cannot be activated. Every question must be required; item 4 is optional.',
        ]);
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_reactivating_an_archived_version_with_wrong_counts_is_blocked(): void
    {
        $psychometrician = $this->psychometrician();
        $currentlyActive = $this->createActiveQuestionnaireVersion();
        $archived = QuestionnaireVersion::factory()->archived()->create();
        $this->addDassQuestions($archived, depression: 1, anxiety: 1, stress: 1);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$archived->questionnaire, $archived])
        );

        $response->assertSessionHasErrors('activation');
        $this->assertSame(QuestionnaireVersion::STATUS_ARCHIVED, $archived->fresh()->status);
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $currentlyActive->fresh()->status);
    }

    public function test_activation_error_stays_on_screen_until_dismissed_on_the_version_page(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 5, anxiety: 7, stress: 8);

        $response = $this->actingAs($psychometrician)
            ->from(route('questionnaires.versions.show', [$version->questionnaire, $version]))
            ->followingRedirects()
            ->patch(route('questionnaires.versions.activate', [$version->questionnaire, $version]));

        $response->assertOk();
        $response->assertSee('Depression has 5 questions; it needs exactly 7. Stress has 8 questions; it needs exactly 7.');
        $response->assertSee('aria-label="Dismiss"', false);
        $response->assertDontSee('setTimeout(() => show = false', false);
    }

    public function test_activation_error_stays_on_screen_until_dismissed_on_the_questionnaire_page(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 6, anxiety: 7, stress: 7);

        $response = $this->actingAs($psychometrician)
            ->from(route('questionnaires.show', $version->questionnaire))
            ->followingRedirects()
            ->patch(route('questionnaires.versions.activate', [$version->questionnaire, $version]));

        $response->assertOk();
        $response->assertSee('Depression has 6 questions; it needs exactly 7.');
        $response->assertSee('aria-label="Dismiss"', false);
        $response->assertDontSee('setTimeout(() => show = false', false);
    }

    public function test_other_toasts_on_the_version_page_still_auto_hide(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version);

        $response = $this->actingAs($psychometrician)
            ->followingRedirects()
            ->patch(route('questionnaires.versions.activate', [$version->questionnaire, $version]));

        $response->assertOk();
        $response->assertSee('Questionnaire version activated successfully.');
        $response->assertSee('setTimeout(() => show = false, 4000)', false);
    }

    public function test_a_draft_with_an_incomplete_layout_can_still_be_edited(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version, depression: 2, anxiety: 0, stress: 0);
        $question = $version->questions()->where('item_number', 1)->firstOrFail();

        $response = $this->actingAs($psychometrician)->put(
            route('questionnaires.versions.questions.update', [$version->questionnaire, $version, $question]),
            [
                'item_number' => 1,
                'question_text' => 'Edited while still a partial Draft.',
                'question_type' => DassQuestion::TYPE_LIKERT_SCALE,
                'subscale' => DassQuestion::SUBSCALE_ANXIETY,
                'display_order' => 1,
                'is_required' => true,
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(DassQuestion::SUBSCALE_ANXIETY, $question->fresh()->subscale);
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

    public function test_a_questionnaire_with_an_active_version_cannot_be_archived(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        $version = QuestionnaireVersion::factory()->active()->create(['questionnaire_id' => $questionnaire->id]);

        $response = $this->actingAs($psychometrician)->delete(route('questionnaires.destroy', $questionnaire));

        $response->assertSessionHasErrors([
            'questionnaire' => 'Activate another version before archiving this questionnaire.',
        ]);
        $this->assertNotSoftDeleted('questionnaires', ['id' => $questionnaire->id]);
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $version->fresh()->status);
    }

    public function test_the_active_version_can_be_archived(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->active()->create();

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.archive', [$version->questionnaire, $version])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame(QuestionnaireVersion::STATUS_ARCHIVED, $version->fresh()->status);
    }

    public function test_a_draft_version_cannot_be_archived(): void
    {
        $psychometrician = $this->psychometrician();
        $draft = QuestionnaireVersion::factory()->create();

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.archive', [$draft->questionnaire, $draft])
        );

        $response->assertSessionHasErrors(['version' => 'Only the Active version can be archived.']);
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_archive_confirmation_warns_that_new_assessments_will_be_blocked(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->active()->create();
        $warning = 'New assessments will be blocked until another version is activated.';

        $this->actingAs($psychometrician)
            ->get(route('questionnaires.versions.show', [$version->questionnaire, $version]))
            ->assertSee($warning);
        $this->actingAs($psychometrician)
            ->get(route('questionnaires.show', $version->questionnaire))
            ->assertSee($warning);
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

    public function test_a_version_cannot_be_reached_through_another_questionnaires_url(): void
    {
        $psychometrician = $this->psychometrician();
        $live = Questionnaire::factory()->create();
        $hidden = Questionnaire::factory()->create();
        $version = QuestionnaireVersion::factory()->create(['questionnaire_id' => $hidden->id]);
        $this->addDassQuestions($version);
        $this->actingAs($psychometrician)->delete(route('questionnaires.destroy', $hidden))->assertSessionHasNoErrors();

        // Activating it here would leave the system's only Active version
        // under an archived questionnaire, around the archive guard.
        $this->patch(route('questionnaires.versions.activate', [$live, $version]))->assertNotFound();
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);

        $this->get(route('questionnaires.versions.show', [$live, $version]))->assertNotFound();
        $this->get(route('questionnaires.versions.edit', [$live, $version]))->assertNotFound();
        $this->delete(route('questionnaires.versions.destroy', [$live, $version]))->assertNotFound();
        $this->assertNotSoftDeleted($version);
    }

    public function test_a_question_cannot_be_reached_through_another_versions_url(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        $draft = QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id]);
        $otherDraft = QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id, 'version_number' => 2]);
        $this->addDassQuestions($otherDraft, 1, 0, 0);
        $question = $otherDraft->questions()->first();

        $this->actingAs($psychometrician)
            ->get(route('questionnaires.versions.questions.edit', [$questionnaire, $draft, $question]))
            ->assertNotFound();
        $this->delete(route('questionnaires.versions.questions.destroy', [$questionnaire, $draft, $question]))->assertNotFound();
        $this->assertNotSoftDeleted($question);

        // A version under another questionnaire is refused here too.
        $otherQuestionnaire = Questionnaire::factory()->create();
        $this->get(route('questionnaires.versions.questions.create', [$otherQuestionnaire, $otherDraft]))->assertNotFound();
    }

    public function test_every_link_on_the_questionnaire_and_version_pages_still_opens(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        $draft = QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id, 'version_number' => 2]);
        $this->addDassQuestions($draft, 1, 1, 1);
        $active = $this->createActiveQuestionnaireVersion();
        $active->update(['questionnaire_id' => $questionnaire->id]);

        $pages = [
            route('questionnaires.show', $questionnaire),
            route('questionnaires.versions.show', [$questionnaire, $draft]),
            route('questionnaires.versions.show', [$questionnaire, $active]),
        ];

        $links = [];

        foreach ($pages as $page) {
            $html = $this->actingAs($psychometrician)->get($page)->assertOk()->getContent();
            preg_match_all('#href="(http://localhost/questionnaires/[^"]+)"#', $html, $matches);
            $links = [...$links, ...$matches[1]];
        }

        $links = array_unique($links);
        $this->assertContains(route('questionnaires.versions.questions.edit', [$questionnaire, $draft, $draft->questions()->first()]), $links);

        foreach ($links as $link) {
            $this->get(html_entity_decode($link))->assertOk();
        }

        // And the forms on those pages still reach their actions.
        $question = $draft->questions()->first();
        $this->put(route('questionnaires.versions.questions.update', [$questionnaire, $draft, $question]), [
            'item_number' => $question->item_number,
            'question_text' => 'Updated wording',
            'question_type' => $question->question_type,
            'subscale' => $question->subscale,
            'display_order' => $question->display_order,
            'is_required' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Updated wording', $question->fresh()->question_text);
        $this->patch(route('questionnaires.versions.archive', [$questionnaire, $active]))->assertSessionHasNoErrors();
        $this->patch(route('questionnaires.versions.activate', [$questionnaire, $active]))->assertSessionHasNoErrors();
    }
}
