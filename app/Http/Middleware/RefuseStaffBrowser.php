<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\StudentDeviceResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a student-device page in a browser that is signed in to a staff
 * account: the student could otherwise type a staff URL and land in that
 * account. Detected without starting a session: the request carries the
 * session cookie of a live, signed-in row in `sessions` (the database
 * session driver, as ActiveSessionGuard also assumes; it reads only
 * user_id and last_activity, so it works with SESSION_ENCRYPT too), or a
 * "Remember Me" cookie, which would sign the browser in on its next staff
 * page. Shows one generic message with no data.
 */
class RefuseStaffBrowser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasStaffSession($request)) {
            return StudentDeviceResponse::staffBrowser($request);
        }

        return $next($request);
    }

    private function hasStaffSession(Request $request): bool
    {
        if ($request->cookies->has(Auth::guard('web')->getRecallerName())) {
            return true;
        }

        $sessionId = $request->cookies->get((string) config('session.cookie'));

        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        return DB::table((string) config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
            ->exists();
    }
}
