<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DassResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'depression_raw_score',
        'anxiety_raw_score',
        'stress_raw_score',
        'depression_final_score',
        'anxiety_final_score',
        'stress_final_score',
        'depression_level',
        'anxiety_level',
        'stress_level',
        'overall_status',
        'overall_flag',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depression_raw_score' => 'integer',
            'anxiety_raw_score' => 'integer',
            'stress_raw_score' => 'integer',
            'depression_final_score' => 'integer',
            'anxiety_final_score' => 'integer',
            'stress_final_score' => 'integer',
            'overall_flag' => 'boolean',
        ];
    }

    /**
     * Get the assessment this result belongs to.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
