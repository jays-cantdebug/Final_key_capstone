<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionnaireVersion extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_ARCHIVED = 'Archived';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'questionnaire_id',
        'version_number',
        'status',
        'effective_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the questionnaire this version belongs to.
     *
     * Includes soft-deleted (archived) questionnaires — "Archived
     * questionnaires must remain available for historical assessments,"
     * so this must never silently return null.
     */
    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class)->withTrashed();
    }

    /**
     * Get the questions belonging to this version.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(DassQuestion::class)->orderBy('display_order');
    }

    /**
     * Get the assessments submitted against this version.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Determine whether this version may still be edited (questions added/edited/removed).
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
