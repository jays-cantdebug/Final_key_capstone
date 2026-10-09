<?php

declare(strict_types=1);

namespace App\Observers;

use App\Casts\EncryptedAttribute;
use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\Course;
use App\Models\DassQuestion;
use App\Models\FlaggedCase;
use App\Models\PredictionFeedback;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * Generic Eloquent observer recording Create/Update/Archive/Delete/Restore
 * audit entries for every model it is attached to (see AuditServiceProvider).
 * A single shared observer is used, rather than one per model, since the
 * logging behavior is identical except for two named special cases below.
 *
 * Encrypted attributes (the `encrypted*` casts and EncryptedInteger) are
 * never written to the trail in any form, plaintext or ciphertext: each is
 * replaced by ENCRYPTED_MARKER, so the log shows that the field was set or
 * changed but never what it said.
 */
class AuditableObserver
{
    public const ENCRYPTED_MARKER = '[changed]';

    /**
     * Models whose user-facing "delete" is an archive (soft delete with no
     * hard-delete path in the app), so a soft delete is logged as "Archive".
     * Every other deletion, including a forceDelete() of these, is "Delete".
     *
     * @var array<int, class-string<Model>>
     */
    private const ARCHIVED_ON_DELETE = [
        Student::class,
        Course::class,
        YearLevel::class,
        Section::class,
        Questionnaire::class,
    ];

    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function created(Model $model): void
    {
        if ($model instanceof Assessment) {
            $this->auditLogService->record(
                'Assessments',
                'Assessment Submission',
                $model->getKey(),
                null,
                $this->sanitize($model, $model->getAttributes())
            );

            return;
        }

        if ($model instanceof PredictionFeedback) {
            $this->auditLogService->record(
                'Feedback Loop',
                'Feedback Loop Submission',
                $model->getKey(),
                null,
                $this->sanitize($model, $model->getAttributes())
            );

            return;
        }

        $this->auditLogService->record(
            $this->moduleNameFor($model),
            'Create',
            $model->getKey(),
            null,
            $this->sanitize($model, $model->getAttributes())
        );
    }

    public function updated(Model $model): void
    {
        if (
            $model instanceof QuestionnaireVersion
            && $model->wasChanged('status')
            && $model->status === QuestionnaireVersion::STATUS_ACTIVE
        ) {
            $this->auditLogService->record(
                'Questionnaire Management',
                'Questionnaire Activation',
                $model->getKey(),
                $this->sanitize($model, $this->originalForAudit($model)),
                $this->sanitize($model, $model->getChanges())
            );

            return;
        }

        if ($model instanceof PredictionFeedback) {
            $this->auditLogService->record(
                'Feedback Loop',
                'Feedback Loop Submission',
                $model->getKey(),
                $this->sanitize($model, $this->originalForAudit($model)),
                $this->sanitize($model, $model->getChanges())
            );

            return;
        }

        $this->auditLogService->record(
            $this->moduleNameFor($model),
            'Update',
            $model->getKey(),
            $this->sanitize($model, $this->originalForAudit($model)),
            $this->sanitize($model, $model->getChanges())
        );
    }

    public function deleted(Model $model): void
    {
        $this->auditLogService->record(
            $this->moduleNameFor($model),
            $this->deleteActionFor($model),
            $model->getKey(),
            $this->sanitize($model, $model->getAttributes()),
            null
        );
    }

    public function restored(Model $model): void
    {
        $this->auditLogService->record(
            $this->moduleNameFor($model),
            'Restore',
            $model->getKey(),
            null,
            $this->sanitize($model, $model->getAttributes())
        );
    }

    /**
     * Map a model class to a human-readable module label for the audit
     * log's `module` column.
     */
    private function moduleNameFor(Model $model): string
    {
        return match ($model::class) {
            Student::class => 'Student Information',
            Course::class => 'Course Management',
            YearLevel::class => 'Year Level Management',
            Section::class => 'Section Management',
            Questionnaire::class, QuestionnaireVersion::class, DassQuestion::class => 'Questionnaire Management',
            User::class => 'User Management',
            CounselingSession::class => 'Counseling Sessions',
            SystemSetting::class => 'Settings',
            FlaggedCase::class => 'Flagged Cases',
            // Matches the 'Assessments' label hardcoded for the Create case
            // in created() above — without this arm, Update/Delete/Restore
            // fell through to class_basename() ('Assessment', singular),
            // splitting one model's audit trail across two module names in
            // the Audit Logs filter dropdown.
            Assessment::class => 'Assessments',
            default => class_basename($model),
        };
    }

    private function deleteActionFor(Model $model): string
    {
        $isSoftDelete = in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && ! $model->isForceDeleting();

        return $isSoftDelete && in_array($model::class, self::ARCHIVED_ON_DELETE, true) ? 'Archive' : 'Delete';
    }

    /**
     * The model's original attributes for an Update entry. getOriginal()
     * runs the casts, which would decrypt encrypted fields into plaintext,
     * so those are read raw instead (sanitize() then masks them) and left
     * out entirely when this update didn't change them.
     *
     * @return array<string, mixed>
     */
    private function originalForAudit(Model $model): array
    {
        $casts = $model->getCasts();
        $original = [];

        foreach (array_keys($model->getRawOriginal()) as $key) {
            if (isset($casts[$key]) && $this->isEncryptedCast($casts[$key])) {
                if ($model->wasChanged($key)) {
                    $original[$key] = $model->getRawOriginal($key);
                }

                continue;
            }

            $original[$key] = $model->getOriginal($key);
        }

        return $original;
    }

    /**
     * Strip sensitive fields before writing attribute snapshots to the
     * audit trail, and mask every encrypted attribute.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function sanitize(Model $model, array $attributes): array
    {
        $attributes = Arr::except($attributes, ['password', 'remember_token']);

        foreach ($model->getCasts() as $key => $cast) {
            if (array_key_exists($key, $attributes) && $attributes[$key] !== null && $this->isEncryptedCast($cast)) {
                $attributes[$key] = self::ENCRYPTED_MARKER;
            }
        }

        return $attributes;
    }

    private function isEncryptedCast(string $cast): bool
    {
        return is_a($cast, EncryptedAttribute::class, true) || str_starts_with($cast, 'encrypted');
    }
}
