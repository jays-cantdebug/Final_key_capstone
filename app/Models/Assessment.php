<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'Completed';

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
}
