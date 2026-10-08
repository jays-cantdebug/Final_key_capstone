<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\StudentDeviceResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-site request protection for the student-device POSTs, which run
 * without a session and so without Laravel's CSRF token. A POST is only
 * accepted when the browser says it came from this origin: `Sec-Fetch-Site:
 * same-origin`, or else an `Origin` header equal to this host. A request
 * with neither (or a mismatch) gets the generic response.
 */
class EnsureSameOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        $origin = $request->headers->get('Origin');

        $sameOrigin = $fetchSite !== null
            ? $fetchSite === 'same-origin'
            : $origin !== null && $origin === $request->getSchemeAndHttpHost();

        if (! $sameOrigin) {
            return StudentDeviceResponse::unavailable($request, Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
