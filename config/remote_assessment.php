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

    // "Add 15 minutes" on the live page (docs/BUG_LOG.md N7): each press adds
    // `extend_minutes`, never past `max_lifetime_minutes` from when the
    // draft was created. Allowed while the student hasn't pressed Done
    // (waiting, notice, details, answering, held), never after Done.
    'extend_minutes' => (int) env('REMOTE_ASSESSMENT_EXTEND_MINUTES', 15),
    'max_lifetime_minutes' => (int) env('REMOTE_ASSESSMENT_MAX_LIFETIME_MINUTES', 120),

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

    // Student entry only (default on): the student enters Step 1 on the
    // student device; the Psychometrician never types a new student's
    // details. "New Assessment" starts (or resumes) a student-device run
    // directly; the manual Step 1 form doesn't exist, and its POST, the
    // same-device Step 2 answers and sending staff-typed details are
    // refused on the server. Take Again works, on the student device only.
    // Off: the manual Step 1 and answering on this PC come back — the
    // fallback when the student device can't be used (e.g. no network).
    'student_entry_only' => (bool) env('REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY', true),

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
