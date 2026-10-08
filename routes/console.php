<?php

use App\Models\RemoteAssessmentDraft;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Remote (student device) drafts past their fixed expiry. Every draft
// lookup also checks the expiry itself, and drafts are pruned lazily when a
// new one is created, so this is a backstop: it only runs when something
// calls `php artisan schedule:run` every minute (cron, or Windows Task
// Scheduler) or `php artisan schedule:work` is running.
Schedule::command('model:prune', ['--model' => [RemoteAssessmentDraft::class]])->everyMinute();
