<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out an authenticated user whose account has been deactivated, on
 * their very next request. UserManagementService::deactivate() already
 * deletes their session rows and replaces their remember-me token; this is
 * the safety net for anything that survives that (another session driver,
 * a request already in flight). The message is the same generic one for
 * any ended session, so it doesn't announce the deactivation itself (the
 * login form also refuses an inactive account with the usual wrong-
 * credentials message).
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Your session has ended. Please log in again.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
