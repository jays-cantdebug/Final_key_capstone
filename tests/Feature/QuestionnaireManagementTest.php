<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\DassQuestion;
use App\Models\DassResponse;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Database\Seeders\DassQuestionSeeder;
use Database\Seeders\QuestionnaireSeeder;
use Database\Seeders\QuestionnaireVersionSeeder;
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

    public function test_a_swapped_subscale_tag_blocks_activation_and_names_every_wrong_item(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version);
        // Still 7/7/7, but item 2 (Anxiety) and item 3 (Depression) swapped.
        $version->questions()->where('item_number', 2)->update(['subscale' => DassQuestion::SUBSCALE_DEPRESSION]);
        $version->questions()->where('item_number', 3)->update(['subscale' => DassQuestion::SUBSCALE_ANXIETY]);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors([
            'activation' => 'This version cannot be activated. Item 2 must be Anxiety, but is Depression. Item 3 must be Depression, but is Anxiety.',
        ]);
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_a_missing_item_number_blocks_activation(): void
    {
        $psychometrician = $this->psychometrician();
        $version = QuestionnaireVersion::factory()->create();
        $this->addDassQuestions($version);
        // Still 7/7/7 and every tag "valid", but numbered 1-20 and 25.
        $version->questions()->where('item_number', 21)->update(['item_number' => 25]);

        $response = $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$version->questionnaire, $version])
        );

        $response->assertSessionHasErrors([
            'activation' => 'This version cannot be activated. Item 21 is missing. Item 25 is not a DASS-21 item; items must be numbered 1 to 21.',
        ]);
        $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_a_translated_version_with_the_official_mapping_activates(): void
    {
        $psychometrician = $this->psychometrician();
        $current = $this->createActiveQuestionnaireVersion();
        $translated = QuestionnaireVersion::factory()->create([
            'questionnaire_id' => Questionnaire::factory()->create(['title' => 'DASS-21 (Filipino)'])->id,
        ]);
        $this->addDassQuestions($translated);
        $translated->questions()->each(fn (DassQuestion $question) => $question->update(['question_text' => "Pahayag {$question->item_number}"]));

        $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$translated->questionnaire, $translated])
        )->assertSessionHasNoErrors();

        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $translated->fresh()->status);
        $this->assertSame(QuestionnaireVersion::STATUS_ARCHIVED, $current->fresh()->status);
    }

    public function test_the_seeded_dass21_matches_the_official_mapping_and_passes_activation(): void
    {
        $this->seed([QuestionnaireSeeder::class, QuestionnaireVersionSeeder::class, DassQuestionSeeder::class]);
        $seeded = QuestionnaireVersion::query()->with('questions')->sole();

        $this->assertSame(
            DassQuestion::OFFICIAL_SUBSCALE_BY_ITEM,
            $seeded->questions->sortBy('item_number')->pluck('subscale', 'item_number')->all()
        );

        $this->actingAs($this->psychometrician())->patch(
            route('questionnaires.versions.activate', [$seeded->questionnaire, $seeded])
        )->assertSessionHasNoErrors();
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $seeded->fresh()->status);
    }

    public function test_a_version_of_an_inactive_or_archived_questionnaire_cannot_be_activated(): void
    {
        $psychometrician = $this->psychometrician();
        $current = $this->createActiveQuestionnaireVersion();

        foreach ([Questionnaire::STATUS_INACTIVE, Questionnaire::STATUS_ARCHIVED] as $status) {
            $version = QuestionnaireVersion::factory()->create([
                'questionnaire_id' => Questionnaire::factory()->create(['status' => $status])->id,
            ]);
            $this->addDassQuestions($version);

            $response = $this->actingAs($psychometrician)->patch(
                route('questionnaires.versions.activate', [$version->questionnaire, $version])
            );

            $response->assertSessionHasErrors([
                'activation' => "This version cannot be activated because its questionnaire is {$status}. Set the questionnaire to Active first.",
            ]);
            $this->assertSame(QuestionnaireVersion::STATUS_DRAFT, $version->fresh()->status);
            $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $current->fresh()->status);
        }
    }

    public function test_the_questionnaire_with_the_active_version_cannot_be_made_inactive_or_archived(): void
    {
        $psychometrician = $this->psychometrician();
        $active = $this->createActiveQuestionnaireVersion();
        $questionnaire = $active->questionnaire;

        foreach ([Questionnaire::STATUS_INACTIVE, Questionnaire::STATUS_ARCHIVED] as $status) {
            $response = $this->actingAs($psychometrician)->put(route('questionnaires.update', $questionnaire), [
                'title' => $questionnaire->title,
                'description' => $questionnaire->description,
                'status' => $status,
            ]);

            $response->assertSessionHasErrors([
                'status' => 'This questionnaire has the Active version, so it must stay Active. Activate a version of another questionnaire first, then change this status.',
            ]);
            $this->assertSame(Questionnaire::STATUS_ACTIVE, $questionnaire->fresh()->status);
        }

        // Without an Active version, the status can change freely.
        $other = Questionnaire::factory()->create();
        $this->put(route('questionnaires.update', $other), [
            'title' => $other->title,
            'description' => $other->description,
            'status' => Questionnaire::STATUS_INACTIVE,
        ])->assertSessionHasNoErrors();
        $this->assertSame(Questionnaire::STATUS_INACTIVE, $other->fresh()->status);
    }

    public function test_activating_a_version_audits_the_version_it_archives(): void
    {
        $psychometrician = $this->psychometrician();
        $current = $this->createActiveQuestionnaireVersion();
        $newVersion = QuestionnaireVersion::factory()->create(['version_number' => 2]);
        $this->addDassQuestions($newVersion);
        $before = (int) AuditLog::query()->max('id');

        $this->actingAs($psychometrician)->patch(
            route('questionnaires.versions.activate', [$newVersion->questionnaire, $newVersion])
        )->assertSessionHasNoErrors();

        $entries = AuditLog::query()->where('id', '>', $before)->orderBy('id')->get();

        $this->assertSame(
            [[$current->id, 'Update'], [$newVersion->id, 'Questionnaire Activation']],
            $entries->map(fn (AuditLog $entry): array => [$entry->record_id, $entry->action])->all()
        );
        $archive = $entries->first();
        $this->assertSame('Questionnaire Management', $archive->module);
        $this->assertSame($psychometrician->id, $archive->user_id);
        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $archive->old_values['status']);
        $this->assertSame(QuestionnaireVersion::STATUS_ARCHIVED, $archive->new_values['status']);
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
            'activation' => 'This version cannot be activated. Depression has 6 questions; it needs exactly 7. Item 21 is missing.',
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
            'activation' => 'This version cannot be activated. Stress has 8 questions; it needs exactly 7. Item 22 is not a DASS-21 item; items must be numbered 1 to 21.',
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
            'activation' => 'This version cannot be activated. Depression has 5 questions; it needs exactly 7. Stress has 1 question; it needs exactly 7. Items 6, 8, 11, 12, 14, 17, 18 and 21 are missing.',
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
        $question = $version->questions()->orderBy('item_number')->firstOrFail();

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

    public function test_deleting_a_draft_question_frees_its_item_number_so_a_new_one_can_be_added_and_the_version_activated(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        $draft = QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id]);
        $this->addDassQuestions($draft);
        $itemFour = $draft->questions()->where('item_number', 4)->firstOrFail();

        $this->actingAs($psychometrician)
            ->delete(route('questionnaires.versions.questions.destroy', [$questionnaire, $draft, $itemFour]))
            ->assertSessionHasNoErrors();

        // Gone from the table entirely, not soft-deleted: a kept row would
        // still hold item 4 and display order 4 in this version.
        $this->assertDatabaseMissing('dass_questions', ['id' => $itemFour->id]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Questionnaire Management',
            'action' => 'Delete',
            'record_id' => $itemFour->id,
        ]);

        $this->post(route('questionnaires.versions.questions.store', [$questionnaire, $draft]), [
            'item_number' => 4,
            'question_text' => 'Replacement item 4',
            'question_type' => DassQuestion::TYPE_LIKERT_SCALE,
            'subscale' => DassQuestion::OFFICIAL_SUBSCALE_BY_ITEM[4],
            'display_order' => 4,
            'is_required' => true,
        ])->assertSessionHasNoErrors();

        $this->patch(route('questionnaires.versions.activate', [$questionnaire, $draft]))->assertSessionHasNoErrors();

        $this->assertSame(QuestionnaireVersion::STATUS_ACTIVE, $draft->fresh()->status);
        $this->assertSame('Replacement item 4', $draft->questions()->where('item_number', 4)->value('question_text'));
    }

    public function test_a_question_that_has_responses_is_never_deleted(): void
    {
        $psychometrician = $this->psychometrician();
        $questionnaire = Questionnaire::factory()->create();
        $draft = QuestionnaireVersion::factory()->create(['questionnaire_id' => $questionnaire->id]);
        $this->addDassQuestions($draft, 1, 0, 0);
        $question = $draft->questions()->firstOrFail();

        // Can't happen through the app (responses are only saved against the
        // Active version, which never returns to Draft); written directly to
        // prove the guard holds if it ever did.
        DassResponse::query()->create([
            'assessment_id' => Assessment::factory()->create()->id,
            'dass_question_id' => $question->id,
            'answer_value' => 2,
        ]);

        $this->actingAs($psychometrician)
            ->delete(route('questionnaires.versions.questions.destroy', [$questionnaire, $draft, $question]))
            ->assertSessionHasErrors('version');

        $this->assertNotSoftDeleted($question);
    }
}
