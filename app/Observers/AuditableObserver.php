<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Assessment;
use App\Models\CounselingSession;
use App\Models\Course;
use App\Models\DassQuestion;
use App\Models\FlaggedCase;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\Section;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Generic Eloquent observer recording Create/Update/Delete/Restore audit
 * entries for every model it is attached to (see AuditServiceProvider).
 * A single shared observer is used, rather than one per model, since the
 * logging behavior is identical except for two named special cases below.
 */
class AuditableObserver
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function created(Model $model): void
    {
        if ($model instanceof Assessment) {
            $this->auditLogService->record(
                'Assessments',
                'Assessment Submission',
                $model->getKey(),
                null,
                $this->sanitize($model->getAttributes())
            );

            return;
        }

        $this->auditLogService->record(
            $this->moduleNameFor($model),
            'Create',
            $model->getKey(),
            null,
            $this->sanitize($model->getAttributes())
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
                $this->sanitize($model->getOriginal()),
                $this->sanitize($model->getChanges())
            );

            return;
        }

        $this->auditLogService->record(
            $this->moduleNameFor($model),
            'Update',
            $model->getKey(),
            $this->sanitize($model->getOriginal()),
            $this->sanitize($model->getChanges())
        );
    }

    public function deleted(Model $model): void
    {
        $this->auditLogService->record(
            $this->moduleNameFor($model),
            'Delete',
            $model->getKey(),
            $this->sanitize($model->getAttributes()),
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
            $this->sanitize($model->getAttributes())
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
            default => class_basename($model),
        };
    }

    /**
     * Strip sensitive fields before writing attribute snapshots to the
     * audit trail.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function sanitize(array $attributes): array
    {
        return Arr::except($attributes, ['password', 'remember_token']);
    }
}
