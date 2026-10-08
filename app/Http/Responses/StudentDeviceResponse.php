<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Middleware\StudentDeviceHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The only two non-questionnaire answers a student device ever gets: the
 * generic "not available" message (an invalid, expired, revoked or already
 * used code or link, a refused request, any error) and the staff-browser
 * refusal. Both carry no data. JSON requests get `{"state": ...}` only.
 */
class StudentDeviceResponse
{
    public const PATH_PREFIX = 's';

    public static function isStudentDeviceRequest(Request $request): bool
    {
        return $request->is(self::PATH_PREFIX, self::PATH_PREFIX.'/*');
    }

    public static function unavailable(Request $request, int $status = Response::HTTP_NOT_FOUND, array $headers = []): Response
    {
        $response = $request->expectsJson()
            ? response()->json(['state' => 'unavailable'], $status)
            : self::page('student-device.unavailable', 'unavailable', $status);

        $response->headers->add($headers);

        return StudentDeviceHeaders::apply($response);
    }

    public static function staffBrowser(Request $request): Response
    {
        $response = $request->expectsJson()
            ? response()->json(['state' => 'unavailable'], Response::HTTP_FORBIDDEN)
            : self::page('student-device.staff-browser', 'staff', Response::HTTP_FORBIDDEN);

        return StudentDeviceHeaders::apply($response);
    }

    /**
     * Rendered up front so that if the view itself fails (e.g. a missing
     * Vite manifest) the device still gets the same message as plain,
     * unstyled HTML instead of an error page.
     */
    private static function page(string $view, string $textKey, int $status): Response
    {
        try {
            $html = view($view)->render();
        } catch (Throwable) {
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'.e(__('student_device.title')).'</title></head><body>'
                .'<h1>'.e(__("student_device.{$textKey}_heading")).'</h1><p>'.e(__("student_device.{$textKey}_body")).'</p></body></html>';
        }

        return response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Exception rendering for student-device paths (bootstrap/app.php):
     * every error — including 404s for unknown /s paths, 429s and 500s,
     * with APP_DEBUG on — becomes the generic response, never a stack
     * trace or Laravel's error page. Rate-limit headers (Retry-After) are
     * kept. Null for any other path.
     */
    public static function forException(Throwable $exception, Request $request): ?Response
    {
        if (! self::isStudentDeviceRequest($request)) {
            return null;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $headers = array_intersect_key($exception->getHeaders(), array_flip(['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset']));

            return self::unavailable($request, $status, $headers);
        }

        return self::unavailable($request, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
