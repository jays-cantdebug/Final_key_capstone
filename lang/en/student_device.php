<?php

/*
|--------------------------------------------------------------------------
| Student device text
|--------------------------------------------------------------------------
|
| Everything a student device can display. None of it may name the student
| or show anything from the rest of the app.
|
*/

return [

    'title' => 'Questionnaire',

    'code_heading' => 'Enter your code',
    'code_help' => 'Type the code the psychometrician gave you.',
    'code_label' => 'Code',
    'code_submit' => 'Continue',

    'begin_heading' => 'Questionnaire',
    'begin_help' => 'Press Begin when you are ready.',
    'begin_submit' => 'Begin',

    /*
    |--------------------------------------------------------------------------
    | Data privacy notice — PLACEHOLDER
    |--------------------------------------------------------------------------
    |
    | PLACEHOLDER TEXT, TO BE REPLACED BY THE DPO'S APPROVED TEXT. It must be
    | replaced with the privacy notice approved by Northern Mindanao
    | Colleges, Inc.'s Data Protection Officer before real use (RA 10173).
    | For a student under 18, a parent's or guardian's consent must also be
    | confirmed. Shown only when config('remote_assessment.student_consent')
    | is on.
    |
    */
    'consent_heading' => 'Data privacy notice',
    'consent_placeholder_marker' => '[Placeholder — to be replaced by the DPO\'s approved text]',
    'consent_paragraphs' => [
        'Your answers to this questionnaire are personal and sensitive information. They will be used only by the school\'s guidance and psychometric staff to understand how you are doing and, where needed, to offer support.',
        'Your answers are kept confidential and protected in line with the Data Privacy Act of 2012 (RA 10173). You may ask the psychometrician any question about how your information is used before you continue.',
    ],
    'consent_accept' => 'I understand and agree',
    'consent_decline' => 'I do not agree',

    // The student's own details (only when the staff member chose that the
    // student fills them in). The fields, labels, placeholders and options
    // are Step 1's own (assessments/create/_student-fields). Nothing here
    // may come from a student record.
    'identity_heading' => 'Your details',
    'identity_submit' => 'Save details',
    // Once saved, the page says only this: never the values.
    'identity_saved' => 'Details saved ✓',
    'identity_not_saved' => 'Details not saved yet',

    // The one page with the details form above the questionnaire.
    'combined_heading' => 'Your details and the questionnaire',
    'questions_heading' => 'Questionnaire',
    'questions_locked' => 'Complete your details first.',
    'statements_legend' => 'Statements',

    'counter' => ':answered of :total answered',
    'done' => 'Done',
    'missing' => 'Please answer every statement before pressing Done.',
    'status_saved' => 'All answers saved.',
    'status_saving' => 'Saving…',
    'status_offline' => 'Not saved — reconnecting…',
    'noscript' => 'Please turn on JavaScript in this browser to answer the questionnaire.',

    'thanks_heading' => 'Thank you',
    'thanks_body' => 'You have finished. Please let the psychometrician know.',

    'declined_heading' => 'Thank you',
    'declined_body' => 'Nothing was saved. Please let the psychometrician know.',

    'unavailable_heading' => 'Not available',
    'unavailable_body' => 'This page is not available. Please ask the psychometrician for help.',
    // Back to the code form; the same for every reason.
    'unavailable_retry' => 'Try again',

    // Held (the Psychometrician has to look at something before the
    // student continues). Neutral on purpose: never says why.
    'held_heading' => 'One moment, please',
    'held_body' => 'Thank you for your details. The psychometrician will assist you now. Please wait.',

    'staff_heading' => 'Please sign out first',
    'staff_body' => 'This browser is signed in to a staff account. Please sign out, or use a private window, then open this page again.',

];
