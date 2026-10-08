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
    'code_help' => 'Type the code the staff member gave you.',
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
        'Your answers are kept confidential and protected in line with the Data Privacy Act of 2012 (RA 10173). You may ask the staff member any question about how your information is used before you continue.',
    ],
    'consent_accept' => 'I understand and agree',
    'consent_decline' => 'I do not agree',

    // The student's own details (only when the staff member chose that the
    // student fills them in). Nothing here may come from a student record.
    'identity_heading' => 'Your details',
    'identity_help' => 'Please type your own details. The staff member will check them with you.',
    'identity_first_name' => 'First Name',
    'identity_middle_name' => 'Middle Name',
    'identity_middle_name_help' => 'Your middle initial only, with a period, e.g., P.',
    'identity_last_name' => 'Last Name',
    'identity_gender' => 'Gender',
    'identity_gender_placeholder' => 'Select gender',
    'identity_course' => 'Course',
    'identity_course_placeholder' => 'Select a course',
    'identity_year_level' => 'Year Level',
    'identity_year_level_placeholder' => 'Select a year level',
    'identity_section' => 'Section',
    'identity_section_placeholder' => 'Select a section',
    'identity_submit' => 'Continue',

    'counter' => ':answered of :total answered',
    'done' => 'Done',
    'missing' => 'Please answer every statement before pressing Done.',
    'status_saved' => 'All answers saved.',
    'status_saving' => 'Saving…',
    'status_offline' => 'Not saved — reconnecting…',
    'noscript' => 'Please turn on JavaScript in this browser to answer the questionnaire.',

    'thanks_heading' => 'Thank you',
    'thanks_body' => 'You have finished. Please hand the device back to the staff member.',

    'declined_heading' => 'Thank you',
    'declined_body' => 'Nothing was saved. Please hand the device back to the staff member.',

    'unavailable_heading' => 'Not available',
    'unavailable_body' => 'This page is not available. Please ask the staff member for help.',

    'staff_heading' => 'Please sign out first',
    'staff_body' => 'This browser is signed in to a staff account. Please sign out, or use a private window, then open this page again.',

];
