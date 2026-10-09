<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every page in the `web` group (login, staff pages,
 * prints, PDFs). The student device (`/s`) is outside that group and keeps
 * its own stricter headers (StudentDeviceHeaders: CSP, no-store).
 *
 * - X-Frame-Options: DENY. No page of the app is ever framed (prints open
 *   in a new tab), so no other site can overlay it to trick a click on
 *   Archive, Deactivate and the like.
 * - X-Content-Type-Options: nosniff.
 * - Referrer-Policy: same-origin. Same-origin navigations keep the full
 *   Referer (the assessment page's Back links rely on it); nothing is sent
 *   to other sites, not even the origin, as strict-origin-when-cross-origin
 *   would. Also matches the student device.
 *
 * No Content-Security-Policy here (yet): Alpine's standard build evaluates
 * its expressions with `new Function` (needs 'unsafe-eval') and some pages
 * use inline <style> blocks and style="display: none;" starting states.
 */
class StaffSecurityHeaders
{
    public const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
