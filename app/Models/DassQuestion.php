<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DassQuestion extends Model
{
    use HasFactory, SoftDeletes;

    public const SUBSCALE_DEPRESSION = 'Depression';

    public const SUBSCALE_ANXIETY = 'Anxiety';

    public const SUBSCALE_STRESS = 'Stress';

    public const TYPE_LIKERT_SCALE = 'Likert Scale';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'questionnaire_version_id',
        'item_number',
        'question_text',
        'question_type',
        'subscale',
        'display_order',
        'is_required',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_number' => 'integer',
            'display_order' => 'integer',
            'is_required' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the questionnaire version this question belongs to.
     */
    public function questionnaireVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class);
    }
}
