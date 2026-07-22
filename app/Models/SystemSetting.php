<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    public const KEY_SYSTEM_NAME = 'system_name';

    public const KEY_SCHOOL_NAME = 'school_name';

    public const KEY_ACTIVE_QUESTIONNAIRE_VERSION_ID = 'active_questionnaire_version_id';

    public const KEY_ASSESSMENT_AVAILABILITY = 'assessment_availability';

    public const KEY_DATA_RETENTION_PERIOD = 'data_retention_period';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
    ];
}
