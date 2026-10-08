<?php

/*
|--------------------------------------------------------------------------
| Remote (student device) assessment
|--------------------------------------------------------------------------
|
| The questionnaire can be answered on a separate student device — a PC
| provided by the guidance office — reached with a short typed code, while
| the Psychometrician watches on their own PC. See RemoteAssessmentService.
|
*/

return [

    // The address the student PC opens (shown on the live page). It must be
    // reachable from the student PC — the server's LAN IP or a domain,
    // never 127.0.0.1/localhost. Falls back to APP_URL.
    'url' => env('REMOTE_ASSESSMENT_URL') ?: env('APP_URL', 'http://localhost'),

    // Fixed lifetime of a draft from the moment it is created. Not extended
    // by activity; "New code" keeps the original expiry.
    'ttl_minutes' => (int) env('REMOTE_ASSESSMENT_TTL_MINUTES', 60),

    // Once the student presses Done, the draft is kept for at least this
    // long so the Psychometrician can review and submit (never shortens a
    // longer remaining expiry).
    'locked_grace_minutes' => (int) env('REMOTE_ASSESSMENT_LOCKED_GRACE_MINUTES', 15),

    // Whether the student acknowledges the data privacy notice on their own
    // screen before the first question (the server refuses answers until
    // then). The notice text is in lang/en/student_device.php. Applies to
    // drafts created after the change; an existing draft keeps its setting.
    // A draft where the student also types their own Step 1 details always
    // shows the notice first, whatever this says.
    'student_consent' => (bool) env('REMOTE_ASSESSMENT_STUDENT_CONSENT', true),

    // Optional: only these addresses may open the student device (/s) —
    // the guidance office's student PC(s). Comma-separated IPv4/IPv6
    // addresses or CIDR ranges, e.g. "192.168.1.20,192.168.1.21" or
    // "192.168.1.16/28". Empty: no restriction. Any other address gets the
    // same generic 404 as an invalid code. An entry that isn't a valid
    // address or range matches nothing (fails closed). Uses the request IP
    // as trustProxies resolves it: never trust "*" proxies with this set.
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('REMOTE_ASSESSMENT_ALLOWED_IPS', ''))), fn (string $entry): bool => $entry !== '')),

    // How often the Psychometrician's live page polls, in milliseconds.
    'poll_interval_ms' => (int) env('REMOTE_ASSESSMENT_POLL_INTERVAL_MS', 1500),

    // Rate limits, per minute. A whole class behind one school NAT shares an
    // IP, so the per-IP code limit counts only FAILED attempts: a
    // successful claim never uses it up.
    'limits' => [
        'failed_entry_per_ip' => (int) env('REMOTE_ASSESSMENT_FAILED_ENTRY_PER_MINUTE', 10),
        'pages_per_ip' => (int) env('REMOTE_ASSESSMENT_PAGES_PER_MINUTE', 300),
        'pages_per_device' => (int) env('REMOTE_ASSESSMENT_DEVICE_PAGES_PER_MINUTE', 60),
        'actions_per_device' => (int) env('REMOTE_ASSESSMENT_ACTIONS_PER_MINUTE', 180),
        'monitor_per_user' => (int) env('REMOTE_ASSESSMENT_MONITOR_PER_MINUTE', 120),
    ],

];
