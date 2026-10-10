<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\UnreadableEncryptedValueException;
use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\DassResult;
use App\Models\FlaggedCase;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Data encrypted with one APP_KEY (key A), read after the key changed (key
 * B): pages show "Unreadable (encrypted with a previous key)" instead of a
 * 500, the ciphertext is never overwritten, nothing is computed from an
 * unreadable value, one warning per record/column is logged, and
 * APP_PREVIOUS_KEYS makes the values readable again.
 */
class UnreadableEncryptedDataTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    private const PLACEHOLDER = 'Unreadable (encrypted with a previous key)';

    private const SCORE_COLUMNS = ['depression_raw_score', 'anxiety_raw_score', 'stress_raw_score', 'depression_final_score', 'anxiety_final_score', 'stress_final_score'];

    private User $psych;

    private User $counselor;

    private Assessment $assessment;

    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOfficialThresholds();
        $version = $this->createActiveQuestionnaireVersion();
        $this->psych = $this->psychometrician();
        $this->counselor = $this->guidanceCounselor();

        // Written with key A: depression raw 11 (final 22, Severe), anxiety 4 (8, Mild), stress 7 (14, Normal).
        $this->actingAs($this->psych);
        $this->assessment = $this->saveAssessmentThroughWizard($version, 11, 4, 7);
        $this->session = CounselingSession::factory()->create([
            'student_id' => $this->assessment->student_id,
            'assessment_id' => $this->assessment->id,
            'counselor_id' => $this->counselor->id,
            'session_notes' => 'Talked about exam stress.',
        ]);
        $this->app['auth']->forgetGuards();
    }

    public function test_the_assessment_page_shows_the_placeholder_instead_of_a_500_for_both_roles(): void
    {
        $this->switchToNewKey();

        foreach ([$this->psych, $this->counselor] as $user) {
            $html = $this->actingAs($user)->get(route('assessments.show', $this->assessment))->assertOk()->getContent();

            // 3 scores + 21 answers, and the levels (stored in plain text) still shown.
            $this->assertSame(24, substr_count($html, 'data-unreadable'));
            $this->assertStringContainsString(self::PLACEHOLDER, $html);
            $this->assertStringContainsString('Severe', $html);
            $this->assertStringNotContainsString('MAC is invalid', $html);
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_the_assessment_print_and_pdf_work(): void
    {
        $this->switchToNewKey();
        $this->actingAs($this->psych);

        $print = $this->get(route('reports.assessment.print', $this->assessment))->assertOk()->getContent();
        $this->assertSame(24, substr_count($print, 'data-unreadable'));
        $this->get(route('reports.assessment.pdf', $this->assessment))->assertOk();
    }

    public function test_the_session_page_edit_form_and_counseling_report_work(): void
    {
        $this->switchToNewKey();
        $this->actingAs($this->counselor);

        $show = $this->get(route('counseling-sessions.show', $this->session))->assertOk()->getContent();
        $this->assertStringContainsString(self::PLACEHOLDER, $show);

        $edit = $this->get(route('counseling-sessions.edit', $this->session))->assertOk()->getContent();
        $this->assertStringContainsString('data-notes-unreadable', $edit);
        $this->assertSame(1, preg_match('#<textarea[^>]*name="session_notes"[^>]*>(.*?)</textarea>#s', $edit, $textarea));
        $this->assertStringContainsString('data-optional', $textarea[0]);
        $this->assertStringNotContainsString(' required', $textarea[0]);
        $this->assertSame('', trim($textarea[1]), 'The edit form must not prefill anything for unreadable notes.');

        $report = $this->get(route('reports.counseling.print'))->assertOk()->getContent();
        $this->assertStringContainsString(self::PLACEHOLDER, $report);
        $this->get(route('reports.counseling.pdf'))->assertOk();
    }

    public function test_saving_an_unrelated_field_leaves_the_ciphertext_byte_identical(): void
    {
        $scoresBefore = $this->rawScores();
        $answersBefore = $this->rawAnswers();
        $notesBefore = $this->rawNotes();
        $this->switchToNewKey();

        // Model saves of another attribute, after the unreadable values were read.
        $result = DassResult::query()->where('assessment_id', $this->assessment->id)->sole();
        $this->assertNull($result->depression_final_score);
        $result->ai_provider = 'rule_based_checked';
        $result->save();
        $session = CounselingSession::query()->findOrFail($this->session->id);
        $this->assertNull($session->session_notes);
        $session->follow_up_required = true;
        $session->save();

        $this->assertSame($scoresBefore, $this->rawScores());
        $this->assertSame($answersBefore, $this->rawAnswers());
        $this->assertSame($notesBefore, $this->rawNotes());
    }

    public function test_editing_a_session_with_unreadable_notes_keeps_them_unless_new_notes_are_typed(): void
    {
        $notesBefore = $this->rawNotes();
        $this->switchToNewKey();
        $this->actingAs($this->counselor);

        $form = [
            'session_date' => now()->toDateString(),
            'session_time' => '09:30',
            'session_status' => CounselingSession::STATUS_COMPLETED,
            'confidentiality_level' => $this->session->confidentiality_level,
            'session_notes' => '',
        ];

        $this->put(route('counseling-sessions.update', $this->session), $form)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($notesBefore, $this->rawNotes(), 'Empty notes on the form must keep the unreadable ciphertext.');
        $this->assertSame(CounselingSession::STATUS_COMPLETED, $this->session->fresh()->session_status);

        $this->put(route('counseling-sessions.update', $this->session), [...$form, 'session_notes' => 'New notes after the key change.'])->assertSessionHasNoErrors();
        $this->assertNotSame($notesBefore, $this->rawNotes());
        $this->assertSame('New notes after the key change.', $this->session->fresh()->session_notes);

        // The audit log still never holds the notes.
        $audit = DB::table('audit_logs')->where('module', 'Counseling Sessions')->latest('id')->first();
        $this->assertStringNotContainsString('New notes', (string) $audit?->new_values);
    }

    public function test_an_unreadable_value_is_never_overwritten_with_null(): void
    {
        $scoresBefore = $this->rawScores();
        $notesBefore = $this->rawNotes();
        $this->switchToNewKey();

        $result = DassResult::query()->where('assessment_id', $this->assessment->id)->sole();
        $session = CounselingSession::query()->findOrFail($this->session->id);

        foreach ([fn () => $result->depression_final_score = null, fn () => $session->session_notes = null] as $overwrite) {
            try {
                $overwrite();
                $this->fail('Overwriting an unreadable value with null must throw.');
            } catch (UnreadableEncryptedValueException) {
                // expected
            }
        }

        $this->assertSame($scoresBefore, $this->rawScores());
        $this->assertSame($notesBefore, $this->rawNotes());
    }

    public function test_nothing_is_computed_from_an_unreadable_value(): void
    {
        $this->switchToNewKey();
        $result = DassResult::query()->where('assessment_id', $this->assessment->id)->sole();

        // Unreadable is "not available" (null), never 0.
        foreach (self::SCORE_COLUMNS as $column) {
            $this->assertNull($result->{$column}, "{$column} must read as null, not 0.");
            $this->assertTrue($result->isUnreadable($column));
        }

        // Severity, flags and the reports use the plain-text levels only.
        $this->assertSame('Severe', $result->highestSeverityLevel());
        $this->assertSame(
            [FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION],
            FlaggedCase::query()->where('assessment_id', $this->assessment->id)->pluck('flag_type')->all(),
        );
        $this->actingAs($this->psych);
        $this->get(route('reports.assessment-summary'))->assertOk();
        $this->get(route('reports.assessment-summary.print'))->assertOk();
    }

    public function test_one_warning_per_record_and_column_without_the_value(): void
    {
        $this->switchToNewKey();
        $warnings = [];
        Log::listen(function ($message) use (&$warnings) {
            if ($message->level === 'warning') {
                $warnings[] = $message;
            }
        });

        $this->actingAs($this->psych)->get(route('assessments.show', $this->assessment))->assertOk();

        // 3 final scores + 21 answers, each read more than once on the page, logged once each.
        $this->assertCount(24, $warnings);
        $keys = array_map(fn ($w) => $w->context['model'].'#'.$w->context['id'].'.'.$w->context['column'], $warnings);
        $this->assertCount(24, array_unique($keys));
        foreach ($warnings as $warning) {
            $this->assertSame(['model', 'id', 'column'], array_keys($warning->context));
        }
    }

    public function test_app_previous_keys_makes_the_old_values_readable_again(): void
    {
        $this->switchToNewKey(previousKeys: [$this->currentKey()]);
        $this->actingAs($this->psych);

        $html = $this->get(route('assessments.show', $this->assessment))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-unreadable', $html);
        $this->assertSame(22, DassResult::query()->where('assessment_id', $this->assessment->id)->sole()->depression_final_score);
        $this->assertSame('Talked about exam stress.', CounselingSession::query()->findOrFail($this->session->id)->session_notes);
    }

    public function test_readable_data_is_unchanged(): void
    {
        $this->actingAs($this->psych);
        $html = $this->get(route('assessments.show', $this->assessment))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-unreadable', $html);
        $this->assertSame(22, DassResult::query()->where('assessment_id', $this->assessment->id)->sole()->depression_final_score);
        $this->assertFalse(CounselingSession::query()->findOrFail($this->session->id)->isUnreadable('session_notes'));
    }

    /**
     * Replace the app's encrypter with one for a brand-new key (B), as after
     * APP_KEY changed; optionally with APP_PREVIOUS_KEYS.
     *
     * @param  list<string>  $previousKeys  raw keys
     */
    private function switchToNewKey(array $previousKeys = []): void
    {
        $encrypter = new Encrypter(Encrypter::generateKey(config('app.cipher')), config('app.cipher'));
        Crypt::swap($previousKeys === [] ? $encrypter : $encrypter->previousKeys($previousKeys));
    }

    private function currentKey(): string
    {
        return Crypt::getKey();
    }

    /** @return array<string, string> */
    private function rawScores(): array
    {
        return (array) DB::table('dass_results')->where('assessment_id', $this->assessment->id)->first(self::SCORE_COLUMNS);
    }

    /** @return list<string> */
    private function rawAnswers(): array
    {
        return DB::table('dass_responses')->where('assessment_id', $this->assessment->id)->orderBy('id')->pluck('answer_value')->all();
    }

    private function rawNotes(): string
    {
        return (string) DB::table('counseling_sessions')->where('id', $this->session->id)->value('session_notes');
    }
}
