<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassificationThreshold;
use App\Models\QuestionnaireVersion;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the canonical system_settings keys with their documented
     * defaults.
     */
    public function run(): void
    {
        $activeVersionId = QuestionnaireVersion::query()
            ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
            ->value('id');

        $defaults = [
            [
                'key' => SystemSetting::KEY_SYSTEM_NAME,
                'value' => 'NORMI',
                'description' => 'The name displayed for this system.',
            ],
            [
                'key' => SystemSetting::KEY_SCHOOL_NAME,
                'value' => 'Northern Mindanao Colleges, Inc.',
                'description' => 'The institution name associated with this deployment.',
            ],
            [
                'key' => SystemSetting::KEY_ACTIVE_QUESTIONNAIRE_VERSION_ID,
                'value' => (string) ($activeVersionId ?? ''),
                'description' => 'Documentary reference to the active questionnaire version. The true source of truth is questionnaire_versions.status; this value is not read by scoring logic.',
            ],
            [
                'key' => SystemSetting::KEY_NOTIFICATION_SEVERITY_THRESHOLD,
                'value' => ClassificationThreshold::SEVERITY_MODERATE,
                'description' => 'Minimum severity level that triggers a Guidance Counselor notification and Flagged Case.',
            ],
            [
                'key' => SystemSetting::KEY_ASSESSMENT_AVAILABILITY,
                'value' => 'Available',
                'description' => 'Whether new assessments are currently available (informational only in this phase; not enforced).',
            ],
            [
                'key' => SystemSetting::KEY_DATA_RETENTION_PERIOD,
                'value' => '5 years',
                'description' => 'Documented data retention period for RA 10173 compliance (informational only).',
            ],
        ];

        foreach ($defaults as $default) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $default['key']],
                [
                    'value' => $default['value'],
                    'description' => $default['description'],
                ]
            );
        }
    }
}
