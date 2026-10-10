<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\StudentDeviceResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The optional allowlist for the whole student device (`/s`):
 * REMOTE_ASSESSMENT_ALLOWED_IPS, comma-separated IPv4/IPv6 addresses or
 * CIDR ranges (config('remote_assessment.allowed_ips')). Empty: no
 * restriction at all.
 *
 * First in the `student-device` middleware group, so a refused address is
 * answered before anything else runs — no cookie decryption, no staff-
 * browser check, no rate limiter, no database — with exactly the generic
 * 404 an invalid code gets (StudentDeviceResponse::unavailable(), HTML or
 * JSON), so nothing hints that an allowlist exists. The exception renderer
 * uses allows() too, so a refused address gets that 404 even for a wrong
 * method or an unknown path.
 *
 * The address is Request::ip(), which follows trustProxies (only
 * 127.0.0.1 in bootstrap/app.php): a forged X-Forwarded-For from anywhere
 * else is ignored. Never trust "*" proxies with an allowlist. Never the
 * user agent. An entry that isn't a valid address or range matches
 * nothing, so a list of typos refuses every PC (fails closed). It
 * identifies a machine, never a student.
 */
class RestrictStudentDeviceNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::allows($request)) {
            return StudentDeviceResponse::unavailable($request);
        }

        return $next($request);
    }

    public static function allows(Request $request): bool
    {
        if (self::entries() === []) {
            return true;
        }

        $ip = $request->ip();
        $valid = array_values(array_diff(self::entries(), self::invalidEntries()));

        return is_string($ip) && $valid !== [] && IpUtils::checkIp($ip, $valid);
    }

    /**
     * The configured entries, as written (trimmed, empty ones dropped).
     *
     * @return array<int, string>
     */
    public static function entries(): array
    {
        $configured = config('remote_assessment.allowed_ips', []);

        return array_values(array_filter(
            array_map(fn (mixed $entry): string => trim((string) $entry), is_array($configured) ? $configured : explode(',', (string) $configured)),
            fn (string $entry): bool => $entry !== '',
        ));
    }

    /**
     * Entries that are neither an IP address nor a CIDR range; they match
     * nothing. Shown to the Psychometrician on the live page only.
     *
     * @return array<int, string>
     */
    public static function invalidEntries(): array
    {
        return array_values(array_filter(self::entries(), fn (string $entry): bool => ! self::isValidEntry($entry)));
    }

    private static function isValidEntry(string $entry): bool
    {
        [$address, $prefix] = array_pad(explode('/', $entry, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        $max = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= $max;
    }
}
