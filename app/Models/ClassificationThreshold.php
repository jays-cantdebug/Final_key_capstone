<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Official, locked DASS-21 severity classification cutoffs.
 *
 * This table has no CRUD UI in this phase: it exists solely so future
 * scoring logic (the DASS Scoring Service) can classify a final subscale
 * score by querying these rows via Eloquent, instead of hardcoding the
 * official cutoffs in application code. Only a database seeder populates
 * and maintains this table.
 */
class ClassificationThreshold extends Model
{
    use HasFactory;

    public const SUBSCALE_DEPRESSION = 'Depression';

    public const SUBSCALE_ANXIETY = 'Anxiety';

    public const SUBSCALE_STRESS = 'Stress';

    public const SEVERITY_NORMAL = 'Normal';

    public const SEVERITY_MILD = 'Mild';

    public const SEVERITY_MODERATE = 'Moderate';

    public const SEVERITY_SEVERE = 'Severe';

    public const SEVERITY_EXTREMELY_SEVERE = 'Extremely Severe';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subscale',
        'severity_level',
        'severity_rank',
        'min_score',
        'max_score',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity_rank' => 'integer',
            'min_score' => 'integer',
            'max_score' => 'integer',
        ];
    }
}
