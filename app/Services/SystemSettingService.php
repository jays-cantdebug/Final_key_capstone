<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassificationThreshold;
use App\Models\QuestionnaireVersion;
use App\Models\SystemSetting;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads and updates system_settings. Only the Psychometrician may change
 * these values (enforced by route middleware, matching the Course/Year
 * Level/Section/Questionnaire precedent of no dedicated Policy for a
 * single admin-only page).
 */
class SystemSettingService
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    /**
     * @return Collection<int, SystemSetting>
     */
    public function all(): Collection
    {
        return SystemSetting::query()->orderBy('key')->get();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return SystemSetting::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Update settings values by key.
     *
     * Updates each SystemSetting model instance individually (rather than
     * a single bulk query-builder update) so that Eloquent's `updated`
     * model event fires for each change — required for Module 13's Audit
     * Log observer to record "All Settings changes... in the Audit Log"
     * as explicitly specified. A bulk `WHERE ... UPDATE` bypasses
     * Eloquent model events entirely, which would make that requirement
     * unsatisfiable.
     *
     * @param array<string, string> $values
     */
    public function update(array $values): void
    {
        $this->database->transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                SystemSetting::query()->where('key', $key)->first()?->update(['value' => $value]);
            }
        });
    }

    /**
     * The currently configured Notification Severity Threshold, used by
     * DassScoringService to determine whether an assessment should be
     * flagged. Defaults to "Moderate" if unset, matching the documented
     * seed default.
     */
    public function notificationSeverityThreshold(): string
    {
        return $this->get(
            SystemSetting::KEY_NOTIFICATION_SEVERITY_THRESHOLD,
            ClassificationThreshold::SEVERITY_MODERATE
        );
    }

    /**
     * The currently active questionnaire version, queried live from
     * questionnaire_versions.status rather than the
     * active_questionnaire_version_id setting, since the version's own
     * status column (managed by Module 4's QuestionnaireVersionService)
     * is the true source of truth. The setting key exists in the schema
     * for documentation purposes only.
     */
    public function activeQuestionnaireVersion(): ?QuestionnaireVersion
    {
        return QuestionnaireVersion::query()
            ->where('status', QuestionnaireVersion::STATUS_ACTIVE)
            ->with('questionnaire')
            ->first();
    }
}
