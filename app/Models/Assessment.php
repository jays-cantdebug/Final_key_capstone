<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;
use LogicException;

class Assessment extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'Completed';

    /**
     * `administration_mode`: answered on a separate student device. Null
     * means the same device (the Psychometrician's own PC).
     */
    public const ADMINISTRATION_STUDENT_DEVICE = 'student_device';

    /**
     * The three subscale keys used by `effectiveLevel()` (matching
     * `dass_results.{subscale}_level` and `prediction_feedback.corrected_{subscale}_level`).
     */
    public const SUBSCALES = [
        FlaggedCase::SUBSCALE_DEPRESSION,
        FlaggedCase::SUBSCALE_ANXIETY,
        FlaggedCase::SUBSCALE_STRESS,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'questionnaire_version_id',
        'psychometrician_id',
        'status',
        'submitted_at',
        'privacy_consent_at',
        'administration_mode',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'privacy_consent_at' => 'datetime',
        ];
    }

    /**
     * Get the student this assessment was administered to.
     *
     * Includes soft-deleted (archived) students — archiving a student via
     * Student Information Management must not break historical assessment
     * views (Dashboard, Assessment History, Flagged Students, etc.); a
     * plain belongsTo silently returns null for an archived student and
     * crashes any view rendering `$assessment->student->...`.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * Get the questionnaire version used for this assessment.
     *
     * Includes soft-deleted (archived) versions — "Archived questionnaire
     * versions shall remain available for historical assessments."
     */
    public function questionnaireVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class)->withTrashed();
    }

    /**
     * Get the psychometrician who administered this assessment.
     */
    public function psychometrician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'psychometrician_id');
    }

    /**
     * Get the individual question responses for this assessment.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(DassResponse::class);
    }

    /**
     * Get the computed DASS-21 result for this assessment.
     */
    public function result(): HasOne
    {
        return $this->hasOne(DassResult::class);
    }

    /**
     * Get the differentiated flagged cases (Counseling Endorsement and/or
     * Awareness Notification rows) generated for this assessment.
     */
    public function flaggedCases(): HasMany
    {
        return $this->hasMany(FlaggedCase::class);
    }

    /**
     * Get the Feedback Loop entry (confirm/correct) for this assessment,
     * if the psychometrician has submitted one.
     */
    public function predictionFeedback(): HasOne
    {
        return $this->hasOne(PredictionFeedback::class);
    }

    /**
     * The single Flag badge to display for this assessment in a summary
     * table (Dashboard "All Assessments", Flagged Students), per the
     * priority-display rule: Counseling Endorsement takes priority over
     * Awareness Notification if both exist.
     *
     * Operates entirely on the already-loaded `flaggedCases` relation —
     * callers must eager-load it (`->with('flaggedCases')`) to avoid an
     * N+1 query when this is called once per row in a paginated table.
     */
    public function priorityFlag(): ?FlaggedCase
    {
        return $this->flaggedCases->firstWhere('flag_type', FlaggedCase::FLAG_TYPE_COUNSELING_ENDORSEMENT)
            ?? $this->flaggedCases->firstWhere('flag_type', FlaggedCase::FLAG_TYPE_AWARENESS_NOTIFICATION);
    }

    /**
     * Count of additional flagged_cases rows beyond the one shown by
     * priorityFlag(), for the secondary "+1 Notification"-style indicator.
     */
    public function secondaryFlagCount(): int
    {
        $priority = $this->priorityFlag();

        if ($priority === null) {
            return 0;
        }

        return $this->flaggedCases->reject(fn (FlaggedCase $flaggedCase): bool => $flaggedCase->is($priority))->count();
    }

    /**
     * Whether the Psychometrician's Step 3 review really changed the AI's
     * classification: the review is a Correct and at least one corrected
     * level differs from the AI's own level for that subscale. A same-level
     * pick, or a Confirm that has corrections stored (legacy rows written
     * before Confirm-with-corrections was refused), does not count.
     *
     * Works only from the already-loaded `result` and `predictionFeedback`
     * relations — callers must eager-load both.
     */
    public function wasCorrected(): bool
    {
        $this->assertReviewRelationsLoaded();

        $feedback = $this->predictionFeedback;

        if ($this->result === null || $feedback === null || $feedback->is_confirmed) {
            return false;
        }

        foreach (self::SUBSCALES as $subscale) {
            $corrected = $feedback->{"corrected_{$subscale}_level"};

            if ($corrected !== null && $corrected !== $this->result->{"{$subscale}_level"}) {
                return true;
            }
        }

        return false;
    }

    /**
     * The reviewed ("effective") severity level for one subscale — the same
     * rule `AssessmentService::save()` flags against: a Confirm keeps the
     * AI's level; a Correct uses the corrected level where one was set and
     * the AI's level otherwise. With no review on record, the AI's level.
     * Null when the assessment has no result.
     *
     * Works only from the already-loaded `result` and `predictionFeedback`
     * relations — callers must eager-load both.
     *
     * @param  string  $subscale  One of self::SUBSCALES.
     */
    public function effectiveLevel(string $subscale): ?string
    {
        if (! in_array($subscale, self::SUBSCALES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown DASS-21 subscale [%s].', $subscale));
        }

        $this->assertReviewRelationsLoaded();

        $aiLevel = $this->result?->{"{$subscale}_level"};
        $feedback = $this->predictionFeedback;

        if ($feedback === null || $feedback->is_confirmed) {
            return $aiLevel;
        }

        return $feedback->{"corrected_{$subscale}_level"} ?? $aiLevel;
    }

    /**
     * The highest reviewed severity across the three subscales (the
     * reviewed counterpart of `DassResult::highestSeverityLevel()`), or
     * null when the assessment has no result.
     */
    public function effectiveHighestSeverityLevel(): ?string
    {
        $this->assertReviewRelationsLoaded();

        if ($this->result === null) {
            return null;
        }

        return (new DassResult([
            'depression_level' => $this->effectiveLevel(FlaggedCase::SUBSCALE_DEPRESSION),
            'anxiety_level' => $this->effectiveLevel(FlaggedCase::SUBSCALE_ANXIETY),
            'stress_level' => $this->effectiveLevel(FlaggedCase::SUBSCALE_STRESS),
        ]))->highestSeverityLevel();
    }

    /**
     * Fail loudly instead of silently issuing a query per row: every list
     * that shows reviewed levels or the Corrected badge must eager-load both.
     */
    private function assertReviewRelationsLoaded(): void
    {
        foreach (['result', 'predictionFeedback'] as $relation) {
            if (! $this->relationLoaded($relation)) {
                throw new LogicException(sprintf(
                    'Assessment #%s: eager-load "%s" before reading reviewed levels or the Corrected badge.',
                    $this->getKey(),
                    $relation
                ));
            }
        }
    }
}
