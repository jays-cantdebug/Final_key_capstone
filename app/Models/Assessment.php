<?php

declare(strict_types=1);

namespace App\Models;

use App\AI\DTOs\AssessmentPayload;
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
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the questionnaire version used for this assessment.
     */
    public function questionnaireVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class);
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
     * Build the immutable AI payload for this assessment.
     *
     * Requires student, result, and responses.question to already be
     * eager-loaded by the caller.
     */
    public function toAssessmentPayload(): AssessmentPayload
    {
        return new AssessmentPayload(
            assessmentId: $this->id,
            studentId: $this->student_id,
            studentFullName: $this->student->full_name,
            depressionFinalScore: $this->result->depression_final_score,
            anxietyFinalScore: $this->result->anxiety_final_score,
            stressFinalScore: $this->result->stress_final_score,
            depressionLevel: $this->result->depression_level,
            anxietyLevel: $this->result->anxiety_level,
            stressLevel: $this->result->stress_level,
            overallStatus: $this->result->overall_status,
            overallFlag: $this->result->overall_flag,
            responses: $this->responses->map(fn (DassResponse $response): array => [
                'question' => $response->question->question_text,
                'subscale' => $response->question->subscale,
                'answerValue' => $response->answer_value,
            ])->all(),
            submittedAt: $this->submitted_at->toDateTimeString(),
        );
    }
}
