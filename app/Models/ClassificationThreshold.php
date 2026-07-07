<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * DASS-21 severity classification cutoffs. Read-only by default (seeded
 * with the official, published values); a Psychometrician may edit them
 * via the guarded "Enable Override Mode" flow in Settings, in which case
 * `RuleBasedDASSProvider` immediately reflects the new values (it queries
 * this table fresh on every classification, never caching).
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
            'min_score' => 'integer',
            'max_score' => 'integer',
        ];
    }

    /**
     * The official, published DASS-21 cutoff scores — the single source
     * of truth used both to seed this table initially and to detect/undo
     * an Override Mode change ("Restore Official Values"). Keeping this
     * here (rather than duplicated in the seeder) means there is exactly
     * one place these numbers are ever written down.
     *
     * @return array<int, array{subscale: string, severity_level: string, min_score: int, max_score: int}>
     */
    public static function officialValues(): array
    {
        return [
            // Depression
            ['subscale' => self::SUBSCALE_DEPRESSION, 'severity_level' => self::SEVERITY_NORMAL, 'min_score' => 0, 'max_score' => 9],
            ['subscale' => self::SUBSCALE_DEPRESSION, 'severity_level' => self::SEVERITY_MILD, 'min_score' => 10, 'max_score' => 13],
            ['subscale' => self::SUBSCALE_DEPRESSION, 'severity_level' => self::SEVERITY_MODERATE, 'min_score' => 14, 'max_score' => 20],
            ['subscale' => self::SUBSCALE_DEPRESSION, 'severity_level' => self::SEVERITY_SEVERE, 'min_score' => 21, 'max_score' => 27],
            ['subscale' => self::SUBSCALE_DEPRESSION, 'severity_level' => self::SEVERITY_EXTREMELY_SEVERE, 'min_score' => 28, 'max_score' => 42],

            // Anxiety
            ['subscale' => self::SUBSCALE_ANXIETY, 'severity_level' => self::SEVERITY_NORMAL, 'min_score' => 0, 'max_score' => 7],
            ['subscale' => self::SUBSCALE_ANXIETY, 'severity_level' => self::SEVERITY_MILD, 'min_score' => 8, 'max_score' => 9],
            ['subscale' => self::SUBSCALE_ANXIETY, 'severity_level' => self::SEVERITY_MODERATE, 'min_score' => 10, 'max_score' => 14],
            ['subscale' => self::SUBSCALE_ANXIETY, 'severity_level' => self::SEVERITY_SEVERE, 'min_score' => 15, 'max_score' => 19],
            ['subscale' => self::SUBSCALE_ANXIETY, 'severity_level' => self::SEVERITY_EXTREMELY_SEVERE, 'min_score' => 20, 'max_score' => 42],

            // Stress
            ['subscale' => self::SUBSCALE_STRESS, 'severity_level' => self::SEVERITY_NORMAL, 'min_score' => 0, 'max_score' => 14],
            ['subscale' => self::SUBSCALE_STRESS, 'severity_level' => self::SEVERITY_MILD, 'min_score' => 15, 'max_score' => 18],
            ['subscale' => self::SUBSCALE_STRESS, 'severity_level' => self::SEVERITY_MODERATE, 'min_score' => 19, 'max_score' => 25],
            ['subscale' => self::SUBSCALE_STRESS, 'severity_level' => self::SEVERITY_SEVERE, 'min_score' => 26, 'max_score' => 33],
            ['subscale' => self::SUBSCALE_STRESS, 'severity_level' => self::SEVERITY_EXTREMELY_SEVERE, 'min_score' => 34, 'max_score' => 42],
        ];
    }

    /**
     * Severity levels in official clinical order (Normal -> Extremely
     * Severe), for consistent display ordering now that the unused
     * `severity_rank` column has been removed.
     *
     * @return array<int, string>
     */
    public static function severityOrder(): array
    {
        return [
            self::SEVERITY_NORMAL,
            self::SEVERITY_MILD,
            self::SEVERITY_MODERATE,
            self::SEVERITY_SEVERE,
            self::SEVERITY_EXTREMELY_SEVERE,
        ];
    }
}
