<?php

namespace App\Providers;

use App\Listeners\DiscardRemoteDraftOnLogout;
use App\Models\CounselingSession;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use App\Policies\CounselingSessionPolicy;
use App\Policies\StudentPolicy;
use App\Policies\SystemNotificationPolicy;
use App\Policies\UserPolicy;
use App\Services\RemoteAssessmentService;
use App\Support\UnreadableEncryptedValues;
use App\View\Composers\NotificationBadgeComposer;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One "unreadable encrypted value" warning per record and column per request.
        $this->app->scoped(UnreadableEncryptedValues::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No function arguments in exception stack traces, so an error never
        // writes a link token (StudentDeviceController::begin()/claim()) or a
        // student's details into storage/logs. PHP's production php.ini
        // default; Herd's development php.ini has it off.
        ini_set('zend.exception_ignore_args', '1');

        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(SystemNotification::class, SystemNotificationPolicy::class);
        Gate::policy(CounselingSession::class, CounselingSessionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        View::composer('layouts.navigation', NotificationBadgeComposer::class);

        $this->configureStudentDeviceRateLimits();

        // Event discovery is off (bootstrap/app.php), so registered by hand.
        Event::listen(Logout::class, DiscardRemoteDraftOnLogout::class);
    }

    /**
     * Rate limits for the remote assessment routes (limits in
     * config/remote_assessment.php). Student-device requests are keyed by
     * the device cookie when there is one, so a class behind one school NAT
     * doesn't share a budget; without a cookie they fall back to the IP,
     * with a higher limit. Failed code attempts have their own per-IP
     * limit, counted in StudentDeviceController (successes don't count).
     */
    private function configureStudentDeviceRateLimits(): void
    {
        $byDeviceOrIp = function (Request $request, string $prefix, int $perDevice, int $perIp): Limit {
            $device = $request->cookie(RemoteAssessmentService::DEVICE_COOKIE);

            return is_string($device) && $device !== ''
                ? Limit::perMinute($perDevice)->by("{$prefix}|device|".hash('sha256', $device))
                : Limit::perMinute($perIp)->by("{$prefix}|ip|".$request->ip());
        };

        RateLimiter::for('student-device-page', fn (Request $request): Limit => $byDeviceOrIp(
            $request,
            'page',
            (int) config('remote_assessment.limits.pages_per_device'),
            (int) config('remote_assessment.limits.pages_per_ip'),
        ));

        RateLimiter::for('student-device-device', fn (Request $request): Limit => $byDeviceOrIp(
            $request,
            'action',
            (int) config('remote_assessment.limits.actions_per_device'),
            (int) config('remote_assessment.limits.actions_per_device'),
        ));

        RateLimiter::for('remote-assessment-monitor', fn (Request $request): Limit => Limit::perMinute(
            (int) config('remote_assessment.limits.monitor_per_user'),
        )->by('monitor|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
