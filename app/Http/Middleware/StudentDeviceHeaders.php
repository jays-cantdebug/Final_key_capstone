<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers for every student-device response (pages, JSON, and —
 * via StudentDeviceResponse — error pages): never cached, by the browser
 * or anything shared in between, never framed, no referrer to other
 * origins, and a strict CSP (scripts, styles, fonts and requests from this
 * origin only; no inline script).
 */
class StudentDeviceHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request));
    }

    public static function apply(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        // "same-origin" rather than "no-referrer": with no-referrer,
        // browsers send `Origin: null` on same-origin POSTs, which
        // EnsureSameOrigin would then have to refuse.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', self::contentSecurityPolicy());
        $response->headers->remove('X-Powered-By');
        // The rate limiter's counters say nothing a device needs, and their
        // presence on some 404s (a code that passed the limiter) and not on
        // others (an address refused before it) would hint at the
        // allowlist. Retry-After on a 429 stays.
        $response->headers->remove('X-RateLimit-Limit');
        $response->headers->remove('X-RateLimit-Remaining');
        $response->headers->remove('X-RateLimit-Reset');

        // Redirects carry Symfony's fallback "Redirecting to <a href…>"
        // page; browsers follow the Location header and never show it.
        if ($response->isRedirection()) {
            $response->setContent('');
        }

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    private static function contentSecurityPolicy(): string
    {
        $script = "'self'";
        $style = "'self'";
        $connect = "'self'";

        // `npm run dev`: assets come from the Vite dev server, which also
        // injects styles inline.
        if (Vite::isRunningHot()) {
            $hot = rtrim(trim((string) @file_get_contents(Vite::hotFile())), '/');
            $script .= " {$hot}";
            $style .= " {$hot} 'unsafe-inline'";
            $connect .= " {$hot} ".preg_replace('#^http#', 'ws', $hot);
        }

        return implode('; ', [
            "default-src 'none'",
            "script-src {$script}",
            "style-src {$style}",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src {$connect}",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'none'",
        ]);
    }
}
