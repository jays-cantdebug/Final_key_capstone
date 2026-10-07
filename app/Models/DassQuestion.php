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
     * The official DASS-21 item number => subscale mapping, as seeded by
     * DassQuestionSeeder (and listed in SYSTEM_DOCUMENTATION.md's "Which
     * questions belong to which subscale"). A version can only be
     * activated if its items are exactly 1-21 with these subscales (see
     * QuestionnaireVersionService::activate()).
     *
     * @var array<int, string>
     */
    public const OFFICIAL_SUBSCALE_BY_ITEM = [
        1 => self::SUBSCALE_STRESS,
        2 => self::SUBSCALE_ANXIETY,
        3 => self::SUBSCALE_DEPRESSION,
        4 => self::SUBSCALE_ANXIETY,
        5 => self::SUBSCALE_DEPRESSION,
        6 => self::SUBSCALE_STRESS,
        7 => self::SUBSCALE_ANXIETY,
        8 => self::SUBSCALE_STRESS,
        9 => self::SUBSCALE_ANXIETY,
        10 => self::SUBSCALE_DEPRESSION,
        11 => self::SUBSCALE_STRESS,
        12 => self::SUBSCALE_STRESS,
        13 => self::SUBSCALE_DEPRESSION,
        14 => self::SUBSCALE_STRESS,
        15 => self::SUBSCALE_ANXIETY,
        16 => self::SUBSCALE_DEPRESSION,
        17 => self::SUBSCALE_DEPRESSION,
        18 => self::SUBSCALE_STRESS,
        19 => self::SUBSCALE_ANXIETY,
        20 => self::SUBSCALE_ANXIETY,
        21 => self::SUBSCALE_DEPRESSION,
    ];

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
