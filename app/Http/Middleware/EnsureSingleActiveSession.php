<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\ActiveSessionGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backstop for single-session-per-account enforcement. LoginRequest blocks
 * a second login while one is already active, but that check only runs on
 * the login form itself — a "Remember Me" cookie re-authenticates
 * silently on whatever authenticated route is visited next, never
 * touching it. This runs on every authenticated request instead and ends
 * whichever session isn't the longest-standing one for that user, so a
 * second device can never stay signed in even if it got in via that path
 * (or a rare simultaneous-login race).
 */
class EnsureSingleActiveSession
{
    public function __construct(private readonly ActiveSessionGuard $activeSessionGuard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $this->activeSessionGuard->isOldestActiveSession($user, $request->session()->getId())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account is already logged in elsewhere. Please log out from the other device first.',
            ]);
        }

        return $next($request);
    }
}
