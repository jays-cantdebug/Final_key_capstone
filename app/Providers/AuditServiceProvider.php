<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogLoginLockout;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
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
use App\Observers\AuditableObserver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up automatic Audit Logging: a single shared model observer for
 * Create/Update/Delete/Restore across every auditable model, and event
 * listeners for Laravel's built-in Login/Logout/Lockout events.
 *
 * No Controller, Service, or Model from any prior module is modified to
 * achieve this — every hook here is registered centrally, keeping
 * Modules 1-12 fully frozen.
 */
class AuditServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Assessment::observe(AuditableObserver::class);
        Student::observe(AuditableObserver::class);
        Course::observe(AuditableObserver::class);
        YearLevel::observe(AuditableObserver::class);
        Section::observe(AuditableObserver::class);
        Questionnaire::observe(AuditableObserver::class);
        QuestionnaireVersion::observe(AuditableObserver::class);
        DassQuestion::observe(AuditableObserver::class);
        User::observe(AuditableObserver::class);
        CounselingSession::observe(AuditableObserver::class);
        SystemSetting::observe(AuditableObserver::class);
        FlaggedCase::observe(AuditableObserver::class);

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Lockout::class, LogLoginLockout::class);
    }
}
