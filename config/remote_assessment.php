<?php

/*
|--------------------------------------------------------------------------
| Remote (student device) assessment
|--------------------------------------------------------------------------
|
| The questionnaire can be answered on a separate student device, reached
| with a short typed code or a QR link, while the Psychometrician watches
| on their own PC. See RemoteAssessmentService.
|
*/

return [

    // The address the student device uses (shown as text and in the QR
    // code). It must be reachable from the student PC — a LAN IP or a
    // domain, never 127.0.0.1/localhost. Falls back to APP_URL.
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
    'student_consent' => (bool) env('REMOTE_ASSESSMENT_STUDENT_CONSENT', true),

    // How often the Psychometrician's live page polls, in milliseconds.
    'poll_interval_ms' => (int) env('REMOTE_ASSESSMENT_POLL_INTERVAL_MS', 1500),

    // Rate limits, per minute. A whole class behind one school NAT shares an
    // IP, so the per-IP code/token limit counts only FAILED attempts: a
    // successful claim never uses it up.
    'limits' => [
        'failed_entry_per_ip' => (int) env('REMOTE_ASSESSMENT_FAILED_ENTRY_PER_MINUTE', 10),
        'pages_per_ip' => (int) env('REMOTE_ASSESSMENT_PAGES_PER_MINUTE', 300),
        'pages_per_device' => (int) env('REMOTE_ASSESSMENT_DEVICE_PAGES_PER_MINUTE', 60),
        'actions_per_device' => (int) env('REMOTE_ASSESSMENT_ACTIONS_PER_MINUTE', 180),
        'monitor_per_user' => (int) env('REMOTE_ASSESSMENT_MONITOR_PER_MINUTE', 120),
    ],

];
