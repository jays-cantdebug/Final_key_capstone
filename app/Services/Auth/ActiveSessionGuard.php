<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Backs single-session-per-account enforcement: a fresh login is rejected
 * while another non-expired session already exists for that user (see
 * LoginRequest::authenticate()), and a lightweight per-request backstop
 * (EnsureSingleActiveSession) catches the one path that bypasses the login
 * form entirely — a "Remember Me" cookie silently re-authenticating a
 * second device.
 *
 * "Active" is defined the same way Laravel's own session garbage collector
 * eventually would (last_activity within session.lifetime), but checked
 * directly rather than waiting on GC, which only sweeps expired rows
 * probabilistically (~2% of requests by default) — so a stale row from a
 * browser that was closed without logging out stops counting as active
 * the moment it logically expires, not whenever GC happens to run next.
 */
class ActiveSessionGuard
{
    /**
     * Whether any non-expired session exists for this user. Used at login
     * time — deliberately has no "except this session" concept, since the
     * request attempting to log in isn't authenticated yet.
     */
    public function hasActiveSession(User $user): bool
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $this->activityCutoff())
            ->exists();
    }

    /**
     * Whether $sessionId is the longest-standing non-expired session this
     * user currently has. A single legitimate session is trivially "the
     * oldest" (no other rows to compare against). When more than one row
     * exists — which the login-time check is meant to prevent, but a
     * "Remember Me" cookie re-authenticates silently on whatever route is
     * visited next, never touching that check — the first-created session
     * wins and every later one is treated as an interloper.
     *
     * `sessions` has no creation timestamp, only `last_activity`, so
     * "first-created" is approximated as "least recently touched among
     * currently-active rows" with the row `id` as a tiebreaker for
     * same-second collisions. This holds correctly at the moment that
     * matters — the instant a second row is first created, its
     * last_activity is "now," which cannot be earlier than a
     * already-existing row's last touch.
     */
    public function isOldestActiveSession(User $user, string $sessionId): bool
    {
        $oldestId = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $this->activityCutoff())
            ->orderBy('last_activity')
            ->orderBy('id')
            ->value('id');

        return $oldestId === null || $oldestId === $sessionId;
    }

    /**
     * Immediately end every session this user has, anywhere. Used by the
     * admin "Force Logout" action as the recovery path when someone can't
     * wait out the session lifetime.
     */
    public function forceLogout(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function activityCutoff(): int
    {
        return now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
    }
}
