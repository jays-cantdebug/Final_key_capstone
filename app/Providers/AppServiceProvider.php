<?php

namespace App\Providers;

use App\Models\CounselingSession;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use App\Policies\CounselingSessionPolicy;
use App\Policies\StudentPolicy;
use App\Policies\SystemNotificationPolicy;
use App\Policies\UserPolicy;
use App\View\Composers\NotificationBadgeComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(SystemNotification::class, SystemNotificationPolicy::class);
        Gate::policy(CounselingSession::class, CounselingSessionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        View::composer('layouts.navigation', NotificationBadgeComposer::class);
    }
}
