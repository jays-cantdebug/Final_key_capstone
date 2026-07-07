<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'course_id',
        'year_level_id',
        'section_id',
        'privacy_consent_at',
    ];

    /**
     * The attributes that should be appended.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'privacy_consent_at' => 'datetime',
        ];
    }

    /**
     * Get the student's course.
     *
     * Includes soft-deleted (archived) courses — deactivating/archiving a
     * course must not break historical student/assessment views.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /**
     * Get the student's year level. Includes archived year levels — see
     * course() above for why.
     */
    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class)->withTrashed();
    }

    /**
     * Get the student's section. Includes archived sections — see
     * course() above for why.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class)->withTrashed();
    }

    /**
     * Get the student's display name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(sprintf('%s %s %s', $this->first_name, $this->middle_name ?? '', $this->last_name)),
        );
    }
}