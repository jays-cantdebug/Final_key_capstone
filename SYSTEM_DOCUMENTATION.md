# NORMI — System Documentation

**NORMI** (Web-Based Student Depression, Anxiety and Stress Assessment) is a school guidance and psychometric records portal built for Northern Mindanao Colleges, Inc. It lets school staff run the DASS-21 mental health screening on students, get an AI-assisted severity classification, route serious cases to the guidance office, and keep proper records for reporting and audits.

> **Scope change (2026-10-08): student device assessment.** NORMI was built as a closed staff system with no student-facing access. It now has one deliberate exception: the Psychometrician can send the questionnaire to a separate **student device**, where the student answers it on their own screen, reached with a typed short code (or a link the Psychometrician copies from the live page) and **no login**. The student device is a **PC provided by the guidance office**, never a phone or tablet. That student page shows only the privacy notice, the questions and a thank-you message; everything else (scoring, the AI, the review, saving) still happens on the Psychometrician's side. See [Student Device Assessment](#student-device-assessment-scope-change). Statements elsewhere in this document about a closed staff system now carry this exception.
>
> **Second approved exception (2026-10-08): the student can fill in Step 1 too.** At the adviser's request, the Psychometrician can instead let the student type their **own details** (name, gender, course, year level, section) on the student device, after the privacy notice and before the questionnaire. The device still never shows any record that already exists in the system. See [When the student fills in Step 1 too](#when-the-student-fills-in-step-1-too), including the one remaining "tell" for the adviser and the DPO to decide on.

This document explains what the system does, how each part works, and — just as importantly — *why* it was built that way. It's written in plain language for a non-technical reader (like a thesis panel), not as a code reference.

---

## Table of Contents

1. [Who Uses This System](#who-uses-this-system)
2. [Technology Stack](#technology-stack)
3. [How the Project Is Organized (Folder Structure)](#how-the-project-is-organized-folder-structure)
4. [Login & Account Security](#login--account-security)
5. [Dashboards](#dashboards)
6. [Student Information Management](#student-information-management)
7. [Questionnaire Management](#questionnaire-management)
8. [The New Assessment Wizard](#the-new-assessment-wizard)
9. [Student Device Assessment (Scope Change)](#student-device-assessment-scope-change)
10. [The AI Classification System (Full Detail)](#the-ai-classification-system-full-detail)
11. [DASS-21 Scoring — How Raw Answers Become Final Scores](#dass-21-scoring--how-raw-answers-become-final-scores)
12. [Differentiated Flagging](#differentiated-flagging)
13. [The "Take Again" Retake Feature](#the-take-again-retake-feature)
14. [Assessment History](#assessment-history)
15. [Flagged Cases (Guidance Counselor)](#flagged-cases-guidance-counselor)
16. [Notifications](#notifications)
17. [Counseling Sessions](#counseling-sessions)
18. [Reports](#reports)
19. [Classification Thresholds & Settings](#classification-thresholds--settings)
20. [User Management](#user-management)
21. [Audit Logs](#audit-logs)
22. [Data Encryption & Privacy (RA 10173)](#data-encryption--privacy-ra-10173)
23. [Search & Filter System — How It Works Everywhere](#search--filter-system--how-it-works-everywhere)
24. [Page Loading Without a Flash](#page-loading-without-a-flash)
25. [Closing Summary](#closing-summary)

---

## Who Uses This System

There are exactly two staff roles. There is no public registration — accounts are created by an administrator, not signed up for.

Students are **not** users: they have no account and never log in. Since the 2026-10-08 scope change, a student can answer the questionnaire on a separate student device (see [Student Device Assessment](#student-device-assessment-scope-change)), but that page needs only a one-time code from the Psychometrician, can't reach any other page, and shows nothing already stored about any student, nor any result. When the Psychometrician chooses it, the student also types their own details there; the page shows those only in its own form, back to the student who typed them.

| Role | What they do |
|---|---|
| **Psychometrician** | Runs assessments, manages students, manages the questionnaire itself, configures the official severity thresholds, manages user accounts, and reviews audit logs. |
| **Guidance Counselor** | Receives cases the system flags as needing attention, manages counseling sessions, and reviews notifications. |

**Why split it this way?** The Psychometrician is the one administering the test and interpreting results clinically; the Guidance Counselor is the one who acts on serious cases. Keeping their tools separate means each role only sees what's relevant to their job — the Psychometrician isn't cluttered with counseling scheduling, and the Guidance Counselor isn't given the ability to edit the assessment itself. A few pages (Assessment History, Reports) are shared because both roles legitimately need to see that information.

---

## Technology Stack

| Technology | What it is | Why it was chosen |
|---|---|---|
| **PHP 8.2** | The programming language the entire backend is written in. | It's the language Laravel runs on, and it's what a huge share of web hosting supports — easy to deploy for a school. |
| **Laravel 11** | The backend framework — handles routing, database access, security, validation, and page rendering. | Laravel gives a huge amount of "already solved" infrastructure for free (login, CSRF protection, database migrations, testing tools), so development time goes into the actual mental-health-assessment logic instead of reinventing basic web plumbing. |
| **MySQL** | The database that stores every student, assessment, and record. | A standard, reliable relational database — good fit for data that has clear relationships (a student *has* assessments, an assessment *has* responses, etc.), which this system is full of. |
| **Blade** | Laravel's built-in templating language for building web pages out of PHP + HTML. | Comes free with Laravel, no extra framework to learn or maintain, and is fast enough that no separate frontend build pipeline (like React) is needed for what is fundamentally a forms-and-tables admin portal. |
| **Tailwind CSS** | A utility-based CSS framework for styling pages. | Lets the whole app share one consistent visual language (colors, spacing, rounded corners) by reusing small utility classes, instead of hand-writing custom CSS for every single page. |
| **Alpine.js** | A very lightweight JavaScript library for small interactive behaviors (modals, dropdowns, hover tooltips, show/hide toggles). | The app doesn't need a heavy JavaScript framework like React or Vue — it's a server-rendered app that just needs small sprinkles of interactivity. Alpine adds that without the complexity of a full frontend build system. |
| **Vite** | The build tool that compiles and bundles the CSS/JS files into what the browser actually loads. | Laravel's official, recommended asset bundler — fast rebuilds during development, small optimized output for production. |
| **Claude API (Anthropic)** | The AI service used for one of the two interchangeable "AI Classification" strategies — reads the three DASS-21 scores and classifies their severity. | Provides a second, independent classification pathway that can be cross-checked against the system's own deterministic rule engine (see the [AI Classification](#the-ai-classification-system-full-detail) section) — chosen specifically because its "tool use" feature can force a reply into a strict, guaranteed JSON structure rather than free-form text. |
| **barryvdh/laravel-dompdf** | A PHP library that turns an HTML page into a downloadable PDF. | Used for every printable/downloadable Report — lets the reports reuse the exact same Blade templates already built for on-screen viewing, instead of building PDFs by hand. |
| **Laravel Breeze** | A starter kit for login, password reset, and account security scaffolding. | Rather than writing password hashing, session handling, and login throttling from scratch (and risking security mistakes), Breeze provides Laravel's own official, security-reviewed implementation as a starting point, which was then customized (e.g. registration removed, roles added, styling replaced). |
| **PHPUnit** | The testing framework used to write and run the automated test suite. | Comes standard with Laravel; lets the system verify — automatically, every time something changes — that DASS scoring, flagging rules, role permissions, and every workflow still behave correctly. |

---

## How the Project Is Organized (Folder Structure)

Laravel enforces a specific folder layout. Here's what each major folder actually holds, in plain terms:

| Folder | What lives here |
|---|---|
| `app/Http/Controllers/` | The "traffic directors." Each one receives a web request (e.g., "show me the Students list"), asks a Service to do the real work, and decides which page to show or where to redirect. Controllers are kept deliberately thin. |
| `app/Http/Requests/` | Validation rules for incoming forms. Before a Controller ever touches submitted data, a Request class checks it's actually valid (required fields filled in, correct format, etc.) and rejects it with a clear error if not. |
| `app/Services/` | Where the actual business logic lives — the real "how NORMI works" code. Scoring an assessment, deciding whether to flag a case, generating a student number, checking if a course can be archived — all of it is a Service, not scattered across Controllers. |
| `app/AI/` | The whole AI Classification module lives in its own folder rather than mixed into `Services/`, because it's a self-contained, swappable component (see the AI section below) — it has its own Contracts, DTOs, Providers, and Factory subfolders. |
| `app/Models/` | Each one represents a database table (`Student`, `Assessment`, `FlaggedCase`, etc.) and the relationships between them (e.g., "an Assessment belongs to a Student"). |
| `app/Policies/` | Rules for *who* is allowed to do *what* to a specific record — e.g., "can this particular user edit this particular student record?" — separate from simple page-level "which role can visit this page" checks. |
| `app/Http/Middleware/` | Code that runs *before* a request reaches a Controller — e.g., "block this request entirely if the user isn't logged in" or "block it if their role doesn't match." |
| `app/Observers/` | Code that automatically reacts when a database record is created, updated, or deleted — this is how the Audit Log writes itself automatically without every Controller having to remember to log its own actions. |
| `app/Notifications/` | Defines what a system notification (e.g., "a student was flagged for counseling") contains and who it should go to. |
| `app/Exceptions/` | Custom error types for specific business situations (e.g., "you can't delete this course, a student is still using it") so the rest of the code can catch and handle them with a friendly message instead of crashing. |
| `database/migrations/` | Step-by-step instructions for building the database structure — every table and column, and every later change to them, is a dated file here so the database can be rebuilt from scratch at any time. |
| `database/seeders/` | Scripts that fill a fresh database with starting data — the two role names, the official DASS-21 thresholds, and the first admin account. |
| `database/factories/` | Used only for automated testing — generates realistic fake data (fake students, fake assessments) so tests don't need a real database full of real people. |
| `resources/views/` | All the Blade template files — the actual HTML/visual content of every page, organized into subfolders that match each feature (`students/`, `assessments/`, `reports/`, etc.), plus a `components/` folder for small reusable pieces (buttons, badges, modals) used across many pages. |
| `resources/css/` and `resources/js/` | The raw Tailwind CSS and Alpine.js source files, before Vite compiles them into what the browser downloads. |
| `routes/web.php` | The master map of every staff URL in the system and which Controller handles it. Every page behind the login is registered here. |
| `routes/student-device.php` | The student device's URLs (everything under `/s`), kept in their own file and their own middleware group, without the login, session and CSRF layers of `web.php`. See [Student Device Assessment](#student-device-assessment-scope-change). |
| `lang/en/student_device.php` | Every piece of text the student device can show, including the **placeholder** privacy notice. |
| `tests/` | The automated test suite — code that exercises the real system (creates a student, submits an assessment, checks the right things happened) and fails loudly if a change breaks existing behavior. |
| `config/` | Small settings files for the whole application — e.g., `config/ai.php` decides which AI Classification strategy is active, and `config/remote_assessment.php` holds the student device settings (address, expiry, consent screen, rate limits). |

**Why this structure?** It's Laravel's standard convention, and following convention matters more than it sounds: any developer who already knows Laravel can find their way around this codebase immediately, without needing a special onboarding explanation. The Controller → Service → Model split specifically exists so that business logic (Services) never gets tangled up with web-request handling (Controllers) — which makes it possible to write automated tests against the *logic* directly, without needing to fake an entire web request every time.

---

## Login & Account Security

There is **no public sign-up**. Every account is created by a Psychometrician through User Management — this is a closed staff system, not a public website. **Scope change (2026-10-08):** the one exception is the student device questionnaire under `/s` (see [Student Device Assessment](#student-device-assessment-scope-change)): it is reached without a login, by a one-time code, and it is the only thing outside the login. Every other page still sends a visitor who isn't signed in to the login page, and an automated test fails if any other route without a login ever appears.

**How login works:**
- Email + password, checked against a securely hashed (never stored in plain text) password.
- After **5 failed attempts** from the same email+IP combination, the system locks out further attempts for a cooldown period and logs the lockout in the Audit Log.
- A deactivated account (`is_active = false`) fails to log in with the exact same generic message as a wrong password.

**Why deactivated accounts fail silently, the same as a wrong password:** This is a deliberate security choice, not an oversight. If the system said "this account is deactivated" for a real account, but "wrong password" for a made-up email, an attacker probing the login form could tell which email addresses belong to real staff accounts just by watching which message comes back — a technique called *user enumeration*. Making every failure case look identical closes that gap.

**A related recent fix:** the login page originally showed a wrong-password error as a generic banner at the top of the page. It was changed to a floating tooltip that points directly at the Password field (the same tooltip style used for empty-field errors elsewhere in the app), for clearer, more specific feedback — but always pointing at Password, never revealing whether the problem was actually the email or the password, for the same enumeration-prevention reason above.

---

## Dashboards

Each role has its own dashboard, tailored to what they actually need to act on:

- **Psychometrician Dashboard:** total assessments, breakdowns by stress/anxiety/depression severity, an "Assessment Volume" chart (assessments submitted per month, last 6 months), a severity distribution donut chart, and a filterable table of all assessments.
- **Guidance Counselor Dashboard:** "Active Students" (registered students who are not archived), "Total Assessments" (every assessment, including those of archived students), today's assessments, flagged case counts, a "Flagged Cases Volume" chart, a breakdown by flag type (Counseling Endorsement vs. Awareness Notification), and the most recent assessments needing attention.

**Why separate charts per role?** A Psychometrician cares about overall testing volume and severity trends across the whole student population; a Guidance Counselor specifically cares about *flagged* cases — the subset that actually needs a human follow-up. Showing the Guidance Counselor a chart of *all* assessments (most of which are Normal/Mild and require no action) would bury the signal they actually need.

Both volume charts use **relative bar scaling** — each bar's height is calculated as a percentage of whichever month currently has the highest count in the visible 6-month window, not a fixed scale. This means early on, with low data volume, even a small number can look like a "full" bar — that's expected and self-corrects as more months of real data accumulate. Hovering over a bar shows a small styled tooltip with the exact count for that month, so the real number is never ambiguous even while the chart is still visually adjusting to low volume.

---

## Student Information Management

A record-keeping module for browsing, editing, and archiving student records.

**Key design decisions:**
- **`student_number` is always generated by the system** (year-prefixed, sequential) — it is never typed in by staff, on any form, anywhere. This guarantees no duplicate or malformed student numbers can ever be entered by mistake.
- **Students are archived, never deleted.** The **Archive** button doesn't erase the student's row — it soft-deletes it (sets `deleted_at`). An archived student no longer appears in the Students list, their profile can't be opened, and they can't use Take Again. **Archiving does not erase any data**, and it can be undone from the Archived tab (below). Everything attached to them stays: their assessments stay in Assessment History (and in its Print/PDF Student Assessment History Report), their counseling sessions and Counseling History stay open to the Guidance Counselor, and flagged cases and notifications about them are unchanged. This matters because a student's mental health assessment history has to be preserved for continuity of care, even if they've left the school.
- **Archived tab and Restore (Psychometrician only).** The Students page has two tabs, **Active** (the normal list) and **Archived** (`/students/archived`): archived students, most recently archived first, with their course, year level and section, number of assessments, latest assessment date and archive date, a name search, and an **Assessments** link to their history in Assessment History. **Restore** opens a dialog with an optional **Reason** (up to 255 characters; the dialog asks not to include sensitive personal details, since it is stored in the Audit Log). Restoring:
  - keeps the student's **original student number** (student numbers are never reused — the number is unique across all records, archived ones included);
  - returns the student to the Students list in their **original position** (the list is ordered by registration date, which a restore doesn't change) — the success message says so — and brings back their profile, Take Again, and the ability to book new counseling sessions;
  - changes nothing else: their assessments, results, flags, notifications and counseling history were never removed, so nothing is copied or re-attached;
  - writes exactly one Audit Log entry, action **Restore** (module *Student Information*), with the archive date in Old Values and the student's details plus `restore_reason` in New Values; the entry's page shows the reason (or "None given").
- **Restore is refused while an active student has the same name**, using the same rule as the New Assessment duplicate check (first name, middle initial and last name, ignoring case and extra spaces; see [Duplicate students](#duplicate-students-step-1-refuses-a-student-who-is-already-registered)), because restoring would leave two active records for the same person. The Archived tab shows this up front — **Restore** is disabled and the row says "Active record exists: *student number*" (a link to that record) — and a restore that is attempted anyway is refused with a message naming the active student number. This typically happens after someone used "Create a new student record" on the archived-name warning. Of two archived students with the same name, only the first can be restored. **There is no merge:** when an active record blocks the restore, the person's history stays split between the two student numbers, and both stay visible in Assessment History. Like the wizard's own check, this can't stop a restore and a New Assessment for the same name that finish within the same moment.
- **A course, year level or section archived in the meantime** stays on the restored student's record. The student's edit form keeps it as the selected option, marked "(archived)", so saving the form doesn't force a change; other students' forms don't offer it.
- **Newest registered student first.** The Students list is ordered by registration time, newest first (ties — students registered in the same second — broken by the most recently created record first), on every page, while searching, and in the live-search results alike. It is not ordered by student number. A "Take Again" retake or an edit doesn't change a student's registration time, so neither moves them up the list.
- There is **no manual "Add Student" form** here — new students only ever get registered through the New Assessment wizard (see below), because every real-world encounter with a student starts with running an assessment on them, not with data entry for its own sake.

---

## Questionnaire Management

Manages the DASS-21 questionnaire itself: its versions, and the individual questions within each version.

**Why versions exist at all:** A questionnaire's wording or question set might need to change over time (e.g., translated, or a question reworded for clarity), but every *already-submitted* assessment must keep showing exactly what it was scored against — you can't silently rewrite history. So a `Questionnaire` is a container, and each `QuestionnaireVersion` inside it is a frozen snapshot. Only one version can be `Active` system-wide at any time (that's the one the New Assessment wizard actually uses); older versions become `Archived` but stay attached to their historical assessments forever.

**Editing rules:** questions can only be added, edited, or removed while their parent version is still in `Draft` status. Once a version goes `Active` (meaning real students may have already been assessed against it), it's locked — this prevents someone from quietly changing a question's wording or scoring weight *after* real assessments have already been scored against the old wording.

**Deleting a question is permanent.** Unlike almost everything else in NORMI, a deleted question is removed from the database, not archived, so its item number and display order are free again: a Draft that lost item 4 can be given a new item 4 and then activated. (When questions were soft-deleted, the hidden row kept holding item 4, so such a Draft could never be activated.) This is safe because only Draft questions can be deleted, a version never goes back to Draft once activated, and answers are only ever saved against the Active version, so a Draft question has no answers to lose; the delete is refused if one ever did ("This question cannot be deleted because it has been answered in an assessment."), and the database's foreign key would refuse it too. The Audit Log keeps the deleted question's details (action *Delete*).

**Activating a version** (including reactivating an Archived one) is only allowed when it has the official DASS-21 layout: exactly 7 Depression, 7 Anxiety and 7 Stress questions, numbered 1 to 21 with no number missing or repeated, each item on its official subscale (see [Which questions belong to which subscale](#which-questions-belong-to-which-subscale); the mapping is kept in one place in the code, `DassQuestion::OFFICIAL_SUBSCALE_BY_ITEM`), and every one of them required. DASS-21 scoring (each subscale's raw sum × 2) and the official classification thresholds (top band ending at 42) are only valid for that layout: fewer questions silently cap a subscale below Severe, so a student could never be flagged; more can push a score past 42, where no threshold band matches; an item tagged with the wrong subscale counts toward the wrong score with no error at all (e.g. a translated version with two tags swapped); and an optional question left blank would make scoring fail. If the layout is wrong, activation is refused with one error that lists every problem — each wrong subscale count ("Depression has 5 questions; it needs exactly 7."), missing or out-of-range item numbers ("Items 6, 8 and 21 are missing.", "Item 25 is not a DASS-21 item; items must be numbered 1 to 21."), each mis-tagged item ("Item 4 must be Anxiety, but is Depression.") and any optional items — and the message stays on screen until it is dismissed. A Draft can hold any layout while it is being built — only activation is checked. What the check can't see is the wording: a version whose text is not actually DASS-21 but follows the same numbering and tags will activate.

**The questionnaire must be Active too.** A version can't be activated while its questionnaire's status is Inactive or Archived ("This version cannot be activated because its questionnaire is Inactive. Set the questionnaire to Active first."), and the reverse is refused as well: the questionnaire holding the Active version can't be set to Inactive or Archived ("This questionnaire has the Active version, so it must stay Active. Activate a version of another questionnaire first, then change this status."). Otherwise New Assessments would keep using a version whose questionnaire is marked as not in use.

**Both sides of an activation are audited.** Activating a version archives whichever version was Active before (it may belong to another questionnaire). The Audit Log records that archive exactly like a manual one (module *Questionnaire Management*, action *Update*, status Active → Archived), followed by the *Questionnaire Activation* entry for the new version.

**Version and question pages only open under their own questionnaire and version:** a URL that pairs a version with a different questionnaire (or a question with a different version) shows "Not Found" instead of acting on it.

**Deleting a Draft version** (the **Delete** button, Draft versions only) soft-deletes it; there is no Restore. Its questions are left in place but can no longer be reached.

**Archiving the Active version** is allowed, but it leaves no active version, so New Assessments are blocked until another version is activated; the confirmation dialog says so. Only the Active version can be archived.

**Archiving a questionnaire** (the **Archive** button on the Questionnaires list) soft-deletes it: it disappears from Questionnaire Management, with no archived view and no Restore button. This is different from setting its *status* to Archived on the edit form, which keeps it listed and can be changed back at any time. Archiving is blocked if any of its versions has ever been used by a real assessment ("Cannot archive a questionnaire with a version that has been used by an assessment.") — the system checks this automatically and shows a friendly error instead of silently corrupting historical data. It is also blocked while one of its versions is the Active one ("Activate another version before archiving this questionnaire."), since an archived questionnaire's version would otherwise keep being used for new assessments while being hidden from Questionnaire Management.

---

## The New Assessment Wizard

This is the core workflow: a 3-step guided process — **Student Information → Questionnaire → Review & Save**.

> **Student entry only (the default since 2026-10-09).** With `REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY` on, the Psychometrician no longer types a new student's details: **Step 1 is entered by the student** on the student PC, and the questionnaire is answered there too. "New Assessment" goes straight to the live page with the code. The manual Step 1 form and "answer on this PC" are gone, and refused by the server. Steps 1 and 2 below describe the manual flow, which is what you get with the flag **off** — the fallback when the student device can't be used. See [Student entry only](#student-entry-only-the-default).

### Why nothing is saved until the very end

Nothing is written to the database until the Psychometrician reaches the final step and clicks **Confirm & Save** or **Correct & Save**. Everything in between — the student's name/course/section, their answers to all 21 questions, even the AI's computed classification — lives only in the login session, not in any of the assessment tables. Sessions are stored server-side in the database's `sessions` table, so this data does sit there, in that session's row, until it is saved, cleared or expires (see [Data Encryption & Privacy](#data-encryption--privacy-ra-10173)).

**Scope change (2026-10-08): one temporary table.** When the questionnaire is sent to a student device, the student's answers can't live in the Psychometrician's session, because they arrive from another device. They are kept temporarily in `remote_assessment_drafts`: answers encrypted, no student number, no link to a student record, and no IP address. When the student fills in Step 1 on the device as well, the details they type are kept in the same row, also encrypted, and nowhere else (not in the Psychometrician's session) until Submit. That row is deleted when the Psychometrician submits, cancels or leaves the wizard, or when it expires. It is never audited. So nothing is written to the *assessment* tables until the final save, and an abandoned student-device run still leaves no trace once its draft is gone (see [Student Device Assessment](#student-device-assessment-scope-change)).

**Why build it this way?** An earlier version of this system *did* save the student record as soon as Step 1 was submitted — but that meant every time a Psychometrician started a wizard and then closed the tab, got interrupted, or made a mistake and restarted, a half-finished "student" record was permanently left behind in the database with no actual assessment attached to it. Dozens of these orphan records accumulated. Deferring all saving to the final step means an abandoned wizard — at any point, for any reason — leaves **zero trace** in the database.

### Step 1: Student Information

*(The manual Step 1, with `REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY` off. With it on — the default — the student enters these same fields on the student PC; see [Student entry only](#student-entry-only-the-default).)*

Captures First Name, Middle Name, Last Name (as three separate fields, not one "Full Name" field, to avoid ambiguous name-splitting), Gender, Course, Year Level, Section, and a privacy consent checkbox. When the questionnaire is then sent to a student device, this checkbox is the staff attestation, and the student also acknowledges the notice on their own screen (see [Student Device Assessment](#student-device-assessment-scope-change)).

Above the form, **Let the student fill this in on their device** lets the student type these details themselves on a student device, followed by the questionnaire (see [When the student fills in Step 1 too](#when-the-student-fills-in-step-1-too)). The same fields, rules and tidying below apply there; the checkbox moves to the live page's Submit form.

**Middle Name format rule:** the Middle Name field only accepts a single letter followed by a period (e.g., `P.`) — a middle *initial*, not a full middle name. The letter is A–Z or Ñ (a letter of the Filipino alphabet). Lowercase is accepted but automatically converted to uppercase before it's saved, so `p.` becomes `P.` and `ñ.` becomes `Ñ.` without the user needing to retype it. Some keyboards type Ñ as a plain N followed by a separate tilde mark; when the server's PHP has the intl extension enabled, that is combined into the single letter Ñ before the check, so it is accepted and saved the same way. Without intl it is left as typed and rejected with the normal format message (retyping the letter as a single Ñ works). If the format doesn't match, a floating tooltip explains the expected format with an example. Extra spaces in the three name fields are tidied up before anything else happens (`"Dela  Cruz "` becomes `"Dela Cruz"`).

### Duplicate students: Step 1 refuses a student who is already registered

A returning student must be assessed through **Take Again** (see below), never registered a second time — otherwise their assessments end up split across two student records. So when Step 1 is submitted, the system checks whether a student with the same name already exists.

**What counts as "the same student":** the same first name, middle *initial*, and last name, after trimming spaces, collapsing double spaces, and ignoring upper/lower case. Only the first letter of the middle name is compared, so `D.`, `D`, and `Dela` all match `D.`; a stored record with no middle name at all does not match an entered initial. `Ñ.` and `ñ.` match each other but not `N.` — they are different letters. Course, year level, section and gender are deliberately **not** compared: they change over time (a returning student is usually in a later year level), and a mistyped gender would otherwise hide a real duplicate. The logic lives in `app/Services/StudentDuplicateService.php`.

**What happens on a match:**

- **An active student has the name** → the wizard stops at Step 1 and nothing is staged or saved. A message (which stays on screen until closed with its × button) names the existing student — student number, course, year level, section, and how many assessments they have — with a **Take Again** button and a **View student record** link. When several students have the name, it lists one row per student, each with its own buttons, plus an "Open these in Students" link to the Students list searched by first + last name. **There is no way to continue as a new student.**
- **Only an archived student has the name** → a warning, not a block, because archived students can't use Take Again (they no longer appear in the Students list, and their profile can't be opened), so the user would otherwise be stuck. The warning says continuing will create a new, separate record, or that the student can be restored from Archived Students instead (see [Student Information Management](#student-information-management)), and links to the archived student's assessments in Assessment History. To continue, the Psychometrician must tick "I understand. Create a new student record." and submit again. The entered details and the privacy consent tick are kept when the warning appears, so ticking the box and clicking Continue is all that's needed; if that submit fails some other check, the warning and its tick stay on screen with the error rather than disappearing. That confirmation is recorded in the Audit Log as its own **"Archived Match Confirmed"** entry (module *Student Information*) against the newly created student, listing the archived student(s) it was confirmed against. It is written at the final save, not at Step 1 — consistent with the wizard writing nothing until the end, an abandoned run leaves no audit entry behind.
- If both an active and an archived student match, the active one wins: the wizard is blocked.
- **When the student typed the details on a student device**, the same check runs when they send the form and again on Submit, and the same panel appears, but only on the Psychometrician's live page; the student device shows only the generic message (see [When the student fills in Step 1 too](#when-the-student-fills-in-step-1-too)).

**The final save checks again.** Step 1's check alone isn't enough: the same student could be registered from another browser tab while this assessment is in progress, or a stale wizard session or a double submit could reach the final save. So `AssessmentService::save()` repeats the check inside its database transaction, just before registering the student. If an active student with the name now exists, nothing at all is saved (no student, assessment, result, flag or notification), and the Psychometrician is returned to Step 1 with a message explaining the student was registered while they were working, and a Take Again button. The questionnaire answers from that run are not kept — Take Again starts a fresh questionnaire. This check never applies to Take Again itself, which attaches to an existing student by design. Separately, the Step 3 Confirm & Save / Correct & Save buttons ignore every click after the first, so a double-click sends only one save.

**Known limitations:**

- **A different person with exactly the same first name, middle initial and last name cannot be registered through the wizard.** By design there is no override — a namesake is indistinguishable from a returning student at this point, and allowing one would reopen the duplicate problem. Since the wizard is the only way to register a student, such a student cannot be added until this rule is changed; staff should not work around it by entering a different middle initial, which would put incorrect data on record.
- **An archived match that appears after Step 1 is accepted without confirmation.** Archived matches never block at the final save. So if someone else archives a same-name student between this run's Step 1 and its final save, the save goes ahead without the user ever seeing the archived-student warning, and that archived student is not listed in any "Archived Match Confirmed" entry (only students confirmed at Step 1 are). This needs a second account to archive a same-name student within the same few minutes, and was accepted as a deliberate trade-off rather than discarding the user's answers over a warning.
- Typos ("Jon" vs "John"), swapped first/last names, and accent differences ("Peña" vs "Pena") are not treated as the same name.
- **A Take Again save is protected against double submits only by the browser.** The final-save re-check above guards *new* students; Take Again attaches to an existing student by design, so the server has nothing to re-check. The Step 3 buttons ignore every click after the first, which covers a double-click, but two save requests for the same retake arriving at the server at the same moment (e.g. with JavaScript disabled) would each save an assessment. Accepted as a known limitation; an extra assessment can be identified in the student's Assessment History.

### Step 2: Questionnaire

Shows the 21 DASS-21 questions from the currently Active questionnaire version. Every question must be answered with a value from 0–3, matching the official DASS-21 response scale:

| Value | Meaning |
|---|---|
| 0 | Did not apply to me at all |
| 1 | Applied to me to some degree, or some of the time |
| 2 | Applied to me to a considerable degree, or a good part of the time |
| 3 | Applied to me very much, or most of the time |

At the top of Step 2 the Psychometrician chooses where the student answers: **on this device** (the questionnaire below, unchanged), or **Send to student device** (see [Student Device Assessment](#student-device-assessment-scope-change)). Both the normal flow and Take Again offer the choice. With student entry only (the default), there is no "on this device" option: Step 2 says "The student answers on the student PC" and offers only **Send to student device**, and a same-device answer POST is refused.

Questions are listed in the version's display order, each shown with its item number. The response-scale instructions and the four rating labels above are fixed English text in the page itself (`assessments/create/_response-scale.blade.php`), not part of the questionnaire version — so a translated version shows its translated questions under English instructions and labels.

**The questionnaire version is pinned when Step 2 is submitted.** The answers are saved in the session together with the version they were given for — the one Active at that moment — and Step 3 and the final save always use that version, even if another version is activated in the meantime (from another tab or by another user). The saved assessment is linked to that version, and its answers all belong to that version's questions. Specifically:
- **Another version is activated after Step 2 was submitted** (before or after Step 3 is opened): nothing changes for this assessment; it is reviewed and saved under the version it was answered on.
- **Another version is activated while Step 2 is open** and the old form is then submitted: it is refused with one message, "The active questionnaire changed while you were answering. Please answer the questions below.", and the new version's questions are shown.
- **Going back to Step 2 after a switch**: the page shows the new version with a note that the questionnaire has changed; submitting it pins the new version, and Step 3 then uses that.
- **Take Again** works the same way.
- As a last safeguard, the final save checks that every answer belongs to a question of the pinned version and that the Step 3 review was computed for it. If not (e.g. a stale session), nothing is saved and the Psychometrician is sent back to Step 2: "Nothing was saved: the answers did not match the questionnaire version they were given for. Please answer the questionnaire again."

### Step 3: Review AI Classification (mandatory)

This is the step that makes the AI safe to use in a clinical context. Before anything is saved, the system:
1. Scores the 21 answers into three subscale scores (see [DASS-21 Scoring](#the-ai-classification-system-full-detail) below).
2. Sends those scores to the active AI Classification provider.
3. Shows the Psychometrician exactly what the AI concluded for Depression, Anxiety, and Stress.

The result is kept in the session, so refreshing Step 3, or going back to Step 2 and returning without resubmitting, shows the same classification without calling the AI again. The AI is called once per Step 2 submission: submitting Step 2 always clears the kept result, so resubmitting the same answers calls the AI again.

The Psychometrician must then either:
- **Confirm** — accept the AI's classification as correct, or
- **Correct** — override one or more subscales with a different severity level, optionally with notes explaining why.

Both buttons sit under the same three correction dropdowns, so the save checks that the choice and the dropdowns agree, and refuses (saving nothing, keeping the selections) when they don't:
- **Confirm & Save with any dropdown set** → "You selected corrections. Use Correct & Save to apply them, or set every subscale back to Unchanged to confirm." Corrections are never applied or dropped silently.
- **Correct & Save with no subscale actually changed** — every dropdown left at Unchanged, or every chosen level equal to the AI's own — → "Correct & Save needs at least one subscale set to a level different from the AI's. Choose a new level, or use Confirm & Save to accept the AI's classification." A level equal to the AI's, chosen alongside a real change, is accepted and recorded as chosen; it has no effect on flagging, since the effective level is the same.

**Nothing is saved until one of those two choices is made.** This decision is recorded permanently in the `prediction_feedback` table (shown in the Audit Log as "Feedback Loop Submission"; see [The mandatory pre-save review](#the-mandatory-pre-save-review--why-the-ais-raw-output-never-triggers-anything-by-itself) below) and can't be changed after saving — every single assessment has exactly one review decision, there is no way to skip this step and save without a decision on record.

**Why this exists:** DASS-21 classification is legally and clinically consequential — a wrong severity tier could mean a student who needs help doesn't get flagged, or a false alarm needlessly escalates a normal result. The system treats the AI as a *draft suggestion*, never a final verdict. A trained Psychometrician always has the final say, and the system is built so that saying so is not optional — it's baked into the save action itself.

---

## Student Device Assessment (Scope Change)

> **Scope change, added 2026-10-08 at the adviser's request.** Until then NORMI had no student-facing access at all: only the two staff roles could open any page, and the Psychometrician entered the student's answers on her own PC. This section describes the one student-facing page that now exists, what it can and cannot do, and the safeguards around it.

### How it works

There are two ways to start. This part describes the first, where only the questionnaire goes to the device; the second, where the student fills in Step 1 too, is described in [When the student fills in Step 1 too](#when-the-student-fills-in-step-1-too) and works the same way from step 3 on.

1. **Step 1** is unchanged. The Psychometrician enters the student's details and ticks the privacy consent box, which is now the *staff attestation* that the notice was explained.
2. **Step 2: Send to student device.** Instead of answering on her own PC, the Psychometrician presses **Send to student device**. This creates a temporary draft pinned to the Active questionnaire version. It also runs one extra duplicate check, which only warns: if an active student with the same name was registered since Step 1, the live page says so, and the final save will still refuse a second record as always.
3. **The live page** (Psychometrician only) shows:
   - the student address (e.g. `http://192.168.1.10/s`) and a short code like `2T29-MN1P`, both large, with one line: "On the student PC, open this address in Chrome (or use the desktop shortcut), then type the code. Or copy the link below and open it on the student PC." There is no QR code (removed on 2026-10-08: the student device is a guidance-office PC, never a phone or tablet);
   - a **Copy link** button that copies the link with its one-time token (`http://192.168.1.10/s/t/…`), next to the warning "Don't paste the link into a public chat. It works once and expires with this session. Typing the code avoids passing the link through a messaging service." (see [The link: what it is and what to watch](#the-link-what-it-is-and-what-to-watch));
   - when an allowlist is set (see [Only the guidance office's PC](#only-the-guidance-offices-pc-the-ip-allowlist)), which student PCs may open the address, and any invalid entries — on this page only, never on the student device;
   - a countdown;
   - the device status: waiting, connected and waiting for the privacy notice, answering, Done, or declined;
   - a warning when **another device tried to use the code**, and when the device was last seen;
   - whether the student acknowledged the privacy notice;
   - the student's answers, **read-only**, updated live.

   The page checks for changes every 1.5 seconds; when nothing changed, the server sends only a few small fields.
4. **On the student PC** the student opens the address (or the desktop shortcut opens it) and types the code — or opens the link the Psychometrician copied and presses **Begin**. They then acknowledge the privacy notice (when that screen is on), answer the questionnaire (every tap is saved immediately) and press **Done**. The page then shows only "Thank you … Please let the psychometrician know." and is locked: the student can't go back and change answers.
5. **The Psychometrician's actions:**
   - **Submit** works only once the student pressed Done. On a shared student PC, the live page recommends a guest or private browser window, and HTTPS outside a private LAN demo.
   - **Return to student** unlocks the answers so the student can edit them again.
   - **New code** gives a fresh code, keeps the answers and the acknowledgment, and stops the old device and the old code from working. Use it if the student's browser was closed in a private window, or the wrong device took the code.
   - **Restart on the new version** appears if another questionnaire version was activated meanwhile. It clears the answers and the student device reloads with the new questions.
   - **Cancel** discards everything and returns to the Step 2 choice.
6. **Submit** feeds the answers into the **existing** flow, exactly as if Step 2 had been answered on the Psychometrician's PC. The answers are checked with the same rules, the version is pinned the same way, the scoring and the AI classification are unchanged, the Step 3 review is mandatory as always, and the duplicate check runs again at the final save. The saved assessment records `administration_mode = student_device`; answers given on the Psychometrician's own PC leave it empty. After a student-device Submit, Step 2 shows the student's answers **read-only**: the Psychometrician can only continue to the review or send the questionnaire to the student device again, never change the student's answers.
   - While Step 3 is being prepared (the AI classification), the live page shows the same **"Analyzing responses…"** overlay as Step 2 (the shared `assessments/create/_analyzing-overlay` partial, inside the Submit form only — not for Return to student, New code, Restart, Cancel or Save corrections). Pressing Submit shows it at once, disables Submit against a double submit, and **stops the live page's polling**: Submit deletes the draft, so a later poll would only flip the page to "no longer available" behind the overlay, and on `php -S` (one request at a time) it would wait behind the AI call anyway. The logic is in `resources/js/remote-monitor.js` (`submitForReview()`, `restoreAfterBack()`), no inline script. If Step 3 shows an error, the browser has left the live page, so nothing stays stuck; if the Submit is refused (e.g. the attestation box is unticked), the live page is loaded fresh without the overlay; and pressing Back from Step 3 to a page the browser kept in its back/forward cache hides the overlay, re-enables Submit and resumes polling.

### What the student device shows, and never shows

It shows only:

- the privacy notice, with Accept and Decline;
- when the student fills in Step 1 too: the details form (First, Middle and Last Name, Gender, and the Course, Year Level and Section lists) at the top of the questionnaire page, with the statements locked below it until the details are saved, then only "Details saved ✓". It shows back only what the student typed on that device, and only when it needs correcting;
- the instructions and rating scale;
- each statement with its item number and four answer buttons;
- a small "*n* of 21 answered" counter;
- a save status line ("All answers saved" / "Saving…" / "Not saved — reconnecting…");
- **Done**;
- the thank-you message.

It never shows:

- any data that already exists in the system: no student record, no student number, no assessment count, no other student's name, and no hint whether a typed name is already registered (beyond the one "tell" described under [When the student fills in Step 1 too](#when-the-student-fills-in-step-1-too));
- the student's name or details once they have been sent, not even back to that student (the questionnaire has no name on it);
- the subscale of a statement;
- scores, severity levels, flags or the AI's result;
- a menu, sidebar or link to any other page;
- the app layout.

It uses its own minimal page layout. Anything wrong — an invalid, used, expired or cancelled code or link, a refused request, even a server error — shows one generic message: "This page is not available. Please ask the psychometrician for help." The only other message is a request to sign out when the browser is signed in to a staff account (see below).

### Every page the student device can reach

| Address | Who can open it | What it returns |
|---|---|---|
| `GET /s` | anyone | the code form (or straight back to the questionnaire for a device that already has one) |
| `POST /s` | anyone, same-origin only | uses the code: binds this device and opens the questionnaire, or the generic message |
| `GET /s/t/{token}` | anyone | the **Begin** page for the link (opening it uses nothing up, so a link preview can't spend it), or the generic message |
| `POST /s/t/{token}` | anyone, same-origin only | Begin: binds this device and opens the questionnaire, or the generic message |
| `GET /s/q` | the bound device | the privacy notice; the questionnaire (when the student fills in Step 1: with the details form on top and the statements locked until it is saved, then "Details saved ✓"); the thank-you message; or — when held — the generic message, replacing the whole page |
| `POST /s/consent`, `POST /s/decline` | the bound device | records the acknowledgment / ends the draft |
| `POST /s/identity` | the bound device, same-origin only | takes the student's own details once: always an empty redirect to `/s/q#questions`, whether or not the name matches anyone; or the same page again (422) with what was typed, a message per field and the statements still locked |
| `POST /s/answer`, `POST /s/done` | the bound device | saves one answer / locks the answers (or lists the item numbers still missing) |
| `GET /s/state` | the bound device | `{"state": …}` and nothing else: `consent`, `identity`, `answering`, `help` (held), `locked`, `reload` or `unavailable` |

**Every field the student device can send:** `code` (`POST /s`); `first_name`, `middle_name`, `last_name`, `gender`, `course_id`, `year_level_id`, `section_id` (`POST /s/identity`); `question_id`, `value`, `version` (`POST /s/answer`); `version` (`POST /s/done`, and `?version=` on `GET /s/state`). Anything else it sends is ignored. **Every JSON field it can receive:** `state`, `answered` (a count) and `missing` (item numbers).

### Why the student device can't reach anything else

- **No login and no session at all.** These routes run outside Laravel's normal `web` layer: no session, no CSRF token, no authentication. A request to `/s` therefore can never carry a staff identity, and it never creates a row in the `sessions` table.
- **Codes are secrets.** The short code is 8 characters and the link carries a 256-bit token; both are stored only as HMAC-SHA256 digests keyed by `APP_KEY`, so even the database doesn't hold a usable code or link. (The link was removed and then restored on 2026-10-08, with the QR code staying removed; see [The link](#the-link-what-it-is-and-what-to-watch) for the two migrations.)
- **Optionally, only listed PCs.** With an allowlist set, every other address gets the generic 404 before anything else runs (see [Only the guidance office's PC](#only-the-guidance-offices-pc-the-ip-allowlist)).
- **One device per code.** The first device to use a code is bound to it through a random secret in an HttpOnly, SameSite=Strict cookie limited to `/s`. Every other device is refused, and the live page counts the attempt.
- **Same-origin only.** Every POST must come from the page itself (the browser's `Sec-Fetch-Site`/`Origin`), which blocks cross-site requests.
- **Locked-down responses.** Every response, error pages included, is sent with `Cache-Control: no-store` (nothing is kept by the browser or a shared PC's cache), a strict Content-Security-Policy (no inline or foreign scripts), no framing (`X-Frame-Options: DENY`), `Referrer-Policy: same-origin`, `X-Robots-Tag: noindex` and `nosniff`.
- **Staff browsers are refused.** If the browser is signed in to a staff account (a live session or a "Remember me" cookie), the student page refuses to start and asks for a sign-out or a private window. A student could otherwise type a staff address into that browser.
- **Proven by tests.** Automated tests check that:
  - this is the exact list of student routes (eleven, with the two link pages), none of them with session, CSRF or login middleware;
  - with student entry only: there is no Step 1 form; New Assessment reaches the live page with one POST and a 303; a live draft (also a Take Again one) is resumed and never discarded; staged answers are resumed at Step 3 with Discard; a GET, Cancel, the refused requests and every error path never create a draft; the manual Step 1 POST and the same-device answers are refused; Take Again works on the student device only; the Guidance Counselor gets 403; the start route is behind the login (a device cookie alone is sent to the login page); with the flag off everything is as before;
  - opening the link never claims, even many times with link-preview and safe-browsing user agents; Begin claims once; whichever of the link and the code is used first wins and the other gets the generic 404; expired, revoked (New code), declined and cancelled links get the generic 404; a refused address gets the identical 404 on the link pages too; the token appears in no student-facing response (not even the Begin page) and nowhere in `storage/logs`, even when the link pages fail with a server error; and the restore migration works whether or not the drop ran;
  - with an allowlist, a listed address, range or IPv6 address gets in; any other address gets a 404 identical to an invalid code's (HTML and JSON, a wrong method included) before any database query or rate-limit count; a forged `X-Forwarded-For` doesn't get in; invalid entries fail closed; the list shows on the live page only; with an empty list nothing changes; and the typed code works end to end from a listed PC;
  - the live page has no QR code and no "scan" wording, and the link appears on it only in the Copy link button's data, never as text or as a link, and only until a device has the draft;
  - every other route needs a login, except the login, password-reset and health-check pages;
  - a device holding only its cookie is sent to the login page by every staff route;
  - no student response sets a session cookie or creates a session row;
  - every response carries the headers above;
  - no student response — page source, JSON, error pages (including a forced server error with debug output on) or headers — contains a student's name, number, course, year level, section, a score, a level, a flag or a link into the app. This includes a run where the student types the name of an already registered student: the saved student's course, year level and section are made inactive in that test, so if they appeared anywhere it would be a leak;
  - the details form lists exactly the Active course, year level and section rows, and a typed name that matches an active student, an archived student or nobody gets byte-for-byte the same reply, with the same number of database queries;
  - "Year Level" and "Female" are allowed only inside the open details form itself (one marked `<form>`, on a page whose statements are locked); a test fails if that marker or those words appear anywhere else — the questionnaire after saving, the held page, the thank-you page, error pages or JSON;
  - the server refuses answers and Done before the details are saved, the details can't be edited or reopened after saving (reload, New code, Return to student, a released hold), and a held page contains nothing of the questionnaire or the form.

### Privacy consent on the student's own screen (RA 10173)

- When `REMOTE_ASSESSMENT_STUDENT_CONSENT` is on (the default), the student acknowledges the privacy notice **on their own screen before the first question**, and the server refuses any answer until they have. The time of that acknowledgment is recorded as the assessment's privacy consent time. The Psychometrician's Step 1 checkbox stays, as the staff attestation that the notice was explained.
- Declining ends the draft: no answers are kept, the student is asked to let the psychometrician know, and the live page tells the Psychometrician "The student declined the privacy notice."
- **The notice text is a placeholder.** It lives in `lang/en/student_device.php`, is clearly marked "to be replaced by the DPO's approved text", and must be replaced with the privacy notice approved by Northern Mindanao Colleges, Inc.'s Data Protection Officer before real use.
- **For a student under 18, a parent's or guardian's consent must also be confirmed** before the assessment. The system does not do this.
- The adviser can switch the student's own acknowledgment off with `REMOTE_ASSESSMENT_STUDENT_CONSENT=false`. The questionnaire then starts straight away, and consent rests on the Step 1 attestation alone. **This never applies when the student fills in Step 1 too:** the notice is then always shown first, whatever the setting, so no detail is accepted before the student acknowledges it.

### When the student fills in Step 1 too

> **Second approved exception, 2026-10-08, at the adviser's request**, so that the Psychometrician no longer types the student's details. The device still never shows any data that already exists in the system.

**The flow:**

1. **Start from Step 1.** Above the Step 1 form, the Psychometrician presses **Let the student fill this in on their device**. This starts the wizard over, exactly like submitting Step 1: anything staged before and any earlier student-device draft are discarded. The live page opens with the code and QR code. Nothing about the student is kept in the Psychometrician's session: until Submit, the details exist only in the draft, encrypted.
2. **On the student device**, in this order: the code, the **privacy notice** (always, see above, its own screen), then **one page** with the details form on top and the questionnaire below it, then Done and the thank-you message. The server refuses details before the notice is acknowledged, and refuses answers and Done before the details are saved.
   - **Before the details are saved**, the page shows the details form with **Save details**, and below it every statement, but **locked**: all 21 sit in one disabled group (`<fieldset disabled>`), so nothing can be tapped or tabbed to. The group is dimmed, with the message "Complete your details first." (linked to it for screen readers). The bottom bar says "0 of 21 answered" and "Details not saved yet", and Done is disabled. The answer-saving script doesn't start; it only watches whether the session ends. Saving the details works without JavaScript, as a normal form.
   - **Save details** sends the form and comes back to the same page at `/s/q#questions`, scrolled to and focused on the Questionnaire heading. The details section then shows **only "Details saved ✓"**, with no fields and no values, the statements unlock, and the bar says "Details saved ✓". That is all the page ever shows of them again: after a reload, New code, Return to student or a released hold.
   - **The form is exactly Step 1's.** The fields come from the one partial that Step 1 and the live page's correction form also use (`assessments/create/_student-fields.blade.php`), so the fields, their order (First Name, Middle Name, Last Name, Gender, Course, Year Level, Section), labels, dropdown placeholders and options, and styling can't drift apart; a test compares all three. The only differences: a "Your details" heading, the button says **Save details**, and the staff checkbox "The student has acknowledged the data privacy consent notice for this assessment." is not there (it stays on the Psychometrician's Submit form; a test checks it appears nowhere on the student device). Autocomplete is off on the device's fields.
   - **A failed save** comes back as the same page (status 422) with what the student typed, Step 1's red message under each wrong field (each field points at its message with `aria-describedby`, on Step 1 too), and the **first wrong field focused** (`autofocus`, no script needed). There is no summary, as on Step 1. Without Alpine on the device, a message stays visible until the next save (on Step 1 it hides as soon as you type). The statements stay locked.
   - **For a screen reader** the page reads: the page heading, "Your details" with its labelled fields and Save details, then "Questionnaire" with the lock message and each statement as its own group (announced as unavailable while locked). The save status line is announced politely. There are no links on the page, so no skip link; the `#questions` return does that job.
   - **Screen sizes:** the existing responsive layout is unchanged. The student device is a PC provided by the guidance office. A field the browser scrolls to stays clear of the fixed bottom bar.
3. **The details form** has the same fields, rules and tidying as Step 1: First, Middle and Last Name, Gender, Course, Year Level and Section. The middle initial must be a letter (A–Z or Ñ) and a period; it is uppercased, a two-part Ñ is combined, and extra spaces are removed. The error messages are the same too.
   - **Lists:** the Course, Year Level and Section lists come only from the lookup tables managed in Course, Year Level and Section Management, never from a student record: the Active, unarchived rows, the same lists Step 1 shows (`code - name` for courses, the label for year levels, the name for sections). Gender has the three fixed choices. An inactive, archived or made-up id gets exactly the same reply as an empty choice ("Please select a Course."), so the form reveals nothing about other rows.
   - **Shared PCs:** autocomplete is off on the form and on every field.
   - **Once only:** the details are accepted once per draft. After that the device never shows them again, only "Details saved ✓", not even after New code, Return to student, a released hold or a reload.
4. **The live page** says "Waiting for the student to enter their details" until they arrive. Then it shows them, with the time they were entered, and a **Save corrections** form with Step 1's rules, so the Psychometrician can fix a typo with the student. The answers stay read-only: the correction form only ever sends the Step 1 fields, and anything else in it is ignored. The page's polling carries only "details received" and "held" flags, never the details themselves; the page reloads to show them.
5. **The duplicate check, without telling the student.** When the student sends the form, the server runs the same check as Step 1. Whatever the result, the device gets exactly the same reply: an empty redirect to its own page (`/s/q#questions`), with the same headers, after the same database work. The duplicate check runs here, when the details are saved, never at Done, so a student never answers 21 questions before a match is found. Tests check that the reply and the number of database queries are identical for an active match, an archived match and no match.
   - **No match, or only an archived student:** the questionnaire follows as usual. An archived match is handled at Submit, as at Step 1: the archived-student warning appears on the live page, the Submit form gets the box "I understand. Create a new student record.", and the confirmation is recorded as "Archived Match Confirmed" at the final save.
   - **An active student has the name:** the draft is **held**. The device shows only the generic message, "This page is not available. Please ask the psychometrician for help." — word for word the same as for any other failure — and refuses answers and Done. The live page says "Stopped: the name the student entered matches an existing active student", and shows the same panel as Step 1, with the student's record, number, course and assessment count, and Take Again.
   - **Then:** the Psychometrician either uses **Take Again** from the panel, or **corrects the name** if it was mistyped. Take Again discards this draft, so the device keeps showing the generic message; the retake is sent to the device again with a **new code**, and shows only the notice and the questionnaire. After a correction, if no active student has the corrected name, the device continues by itself within a few seconds.
6. **Submit** needs the staff attestation box (Step 1's privacy checkbox, moved here), and works only once the student pressed Done. It checks the details again with Step 1's rules and runs the duplicate check again, exactly as Step 1 does: an active match blocks, and an archived match needs the confirm box. It then stages the details as Step 1 would, with the student's own on-device acknowledgment time as the privacy consent time, and continues into the unchanged Step 3. The final save repeats the duplicate check as always. **Nothing is written to the `students` table until Confirm & Save or Correct & Save.**

**Take Again is unchanged.** The Psychometrician picks the existing student on their own PC. A retake sent to the device never shows the details form, and refuses details.

#### The one remaining "tell" (for the adviser and the DPO to decide)

A held screen tells the person at the device that **the name they typed belongs to a registered, active student**. The words are the generic ones, but the whole page turning into that message right after Save details, instead of unlocking the questions, is itself the signal. Nothing else leaks: not which student, their number, course or history, nor whether an archived student has the name. To someone reading the network traffic in the browser's developer tools, the held page also differs slightly from a dead code: it returns 200 rather than 404, and its state reads `help`.

**It is limited to one probe per staff-issued code.** The details are accepted once per draft; a second try with another name changes nothing and gets the same reply. Each code is handed out in person by the Psychometrician, and every hold shows on their live page. Trying more names means asking the staff for more codes.

**An alternative would remove the tell entirely:** let the device continue to the questionnaire on a match, and let the Psychometrician resolve it at Submit, which blocks a duplicate anyway. That was not chosen, because the requirement was for the device to stop. Whether to accept the tell is a decision for the adviser and the Data Protection Officer.

#### Privacy risks to weigh

- **Names now cross the network to a page without a login.** They are protected by the one-time code, the one-device binding, the same-origin rule and `no-store`, but over plain HTTP they travel unencrypted. **Use HTTPS outside a private LAN demo**; the live page says so.
- **Shared student PCs.** Autocomplete is off, and nothing is cached, but a browser can still keep form history or offer to save an address; Chrome doesn't always honour "autocomplete off". **Use a guest or private window on the student PC**; the live page says so.
- **Impersonation.** A student could type someone else's name, and the system can't tell who is at the device. The Psychometrician must **verify the student in person**, correct the details if needed, and tick the attestation on the Submit form.
- **The draft now holds a name.** It is encrypted with `APP_KEY` and deleted on every exit, but a leaked `APP_KEY` would also expose the details of drafts still in progress.
- **The consent text is a placeholder** pending the DPO's approved text, and **a parent's or guardian's consent for a student under 18 must still be confirmed** outside the system (see above).
- **The Audit Log records only the final student record**, created at Confirm & Save (with the assessment, its review and, where one was confirmed, the "Archived Match Confirmed" entry). It never records the draft, the details typed on the device, a correction, a hold or a cancelled run.

### Expiry and cleanup

- A draft lasts a fixed **60 minutes** from when it was created (`REMOTE_ASSESSMENT_TTL_MINUTES`); activity doesn't extend it, and New code keeps the same expiry. When the student presses Done, the draft is kept for at least **15 more minutes** (`REMOTE_ASSESSMENT_LOCKED_GRACE_MINUTES`) so the Psychometrician has time to review and submit; this never shortens a longer remaining time.
- The draft is deleted on:
  - Submit or Cancel;
  - starting a new Step 1 or a Take Again;
  - answering on the Psychometrician's own PC instead;
  - the final save, including a duplicate-student refusal;
  - logging out (only the draft started in that login session);
  - Force Logout or deactivation of the Psychometrician's account;
  - expiry: every lookup refuses an expired draft, expired drafts are removed whenever a draft is created and on every live-page check, and `model:prune` runs every minute from the scheduler.
- Details typed on the device are in the same row, so every one of these deletes them too. A declined draft is kept, stripped of its code, answers and any details, only until it expires, so the live page can say so.
- Drafts are never written to the Audit Log, so an abandoned run leaves no trace once its draft is gone.
- **The scheduler only runs if something starts it.** On a Windows server, create a Windows Task Scheduler task that runs `php artisan schedule:run` in the project folder every minute (or keep `php artisan schedule:work` running). Without it, expired drafts are still refused and still removed on the next create or check, just not on a timer.

### Rate limits (and a class behind one school NAT)

- Failed code attempts are limited to **10 per minute per IP** (`REMOTE_ASSESSMENT_FAILED_ENTRY_PER_MINUTE`). **Successful claims don't count**, so a whole class whose PCs share one public IP behind the school's NAT is never locked out by its own successful logins.
- Page loads and actions are limited **per device**, not per IP (60 page loads and 180 actions a minute; a browser without a device cookie gets 300 page loads a minute per IP). The live page's checks are limited per Psychometrician (120 a minute).
- All limits are configurable in `.env` (see `.env.example`).
- The limiter's counters (`X-RateLimit-*` headers) are never sent to the student device; a `429` still carries `Retry-After`. Addresses refused by an allowlist never reach the limiters.

### Network requirements

The student PC must be able to reach the server over the network:

- **Not `127.0.0.1` or `localhost`.** Those addresses mean "this same computer": on the student PC they point at the student PC itself, so nothing loads. Set `REMOTE_ASSESSMENT_URL` to the server's LAN address or domain. The live page warns when the address is a loopback address.
- **On a school LAN:**
  - the server must listen on its LAN address, not only on `127.0.0.1`;
  - Windows Firewall needs an inbound rule for the web server's port;
  - the server needs a **fixed IP** (a static address or a DHCP reservation), or the address changes;
  - many school Wi-Fi networks use **client isolation**, which stops two Wi-Fi devices from reaching each other; use a wired connection or ask IT to allow the server.
  - Herd's `*.test` names only work on the server PC itself, so use the IP address.
- **Use HTTPS** even on a LAN if possible (e.g. a locally trusted certificate). Over plain HTTP the student's answers cross the network unencrypted, and on HTTPS the device cookie is also marked Secure.
- **Build the assets** with `npm run build`. Never use `npm run dev` for a student device: the dev server's files are served from `localhost:5173`, which the student PC can't reach.
- **`APP_DEBUG` must be `false`** on any server another device can reach. With debug on, Laravel's debug tool (Ignition) has its own routes without a login.
- **On a deployed (internet) server** the student page works from anywhere over HTTPS. It is then the system's only page reachable without a login, so the rate limits above matter more. `trustProxies` must match the real proxy, so that per-IP limits see the real address.

### Student entry only (the default)

> **Added 2026-10-09 at the adviser's request:** the Psychometrician stops typing student details.

`REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY` (`config/remote_assessment.php`, `.env.example`) is **on by default**. While it is on, **Step 1 is entered by the student** on the student PC:

- **New Assessment** in the sidebar is a button (a CSRF-protected POST, `assessments.create.start`, Psychometrician only) that goes **straight to the live page** (no page heading; the step indicator reads "Student details (by the student) → Questionnaire → Assessment Result") with the code and address — no Step 1 form, no "Who will fill in the details?". It **resumes before it creates**, in this order:
  1. a **live draft** of this Psychometrician (a student-device run, or a Take Again sent to the student device) — its live page; a live draft is **never discarded** (an expired or declined one is replaced). A live student-entry draft left from an earlier login session is taken over; if no device has used it yet, its code and link are renewed (their plain values were only in the old session);
  2. **answers waiting for Step 3** — Step 3, with "This assessment's answers are waiting for your review…" and a **Discard and start a new assessment** button (nothing is ever wiped silently; nothing of it was saved);
  3. otherwise a new student-entry draft and its live page.
- **`GET /assessments/create` never creates anything.** With a live draft it goes to that live page; otherwise it shows a minimal page with any error, the save-time duplicate panel, and one **Start a new assessment** button. Only typed addresses, bookmarks, Cancel and error redirects land there — so a link, a prefetch, a cross-site page, Cancel or an error can never start a draft by itself.
- **Refused on the server, not only hidden:** the manual Step 1 POST (before any validation: nothing is staged and no student is created; "Student details are entered by the student on the student device. Use New Assessment to start."), the same-device Step 2 answers ("The student answers on the student device. Use Send to student device."), and sending a staff-typed new student that was staged before the flag was turned on.
- **Take Again is unchanged** — the Psychometrician picks the existing student on their own PC — except that its Step 2 has no "answer on this PC" option (the student uses a different PC): only Send to student device, and its Back to the student profile. The student device shows only the privacy notice and the questionnaire.
- **The step indicator** reads **1 Student details (by the student) → 2 Questionnaire → 3 Assessment Result**; a new student's Step 2 has no Back link (Step 1 happened on the device).
- **Unchanged:** the live page, the correction form, Submit, the duplicate hold, every student-device protection, the leak tests, the 11 student routes and the allowlist.

**The fallback: turn it off.** `REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY=false` brings back the manual Step 1 (the Psychometrician types the details), "answer on this PC" on Step 2, and the sidebar link — for when the student PC or the network can't be used. The automated test suite runs with it off (`phpunit.xml`), so the tests written for the manual flow are unchanged; `StudentEntryOnlyTest` switches it on. After changing it, run `php artisan config:clear` (or `config:cache` again).

**CSRF on the New Assessment button has to be checked in a browser or with an HTTP client**, because Laravel turns CSRF checking off inside PHPUnit, so no automated test can show it. The manual check: while signed in, submit `POST /assessments/create/start` from another origin or without a token, and expect **419**, with no draft created; the button itself gives a 303 to the live page. Done on 2026-10-09 with `php -S` on a throwaway database and curl: no token, a foreign `Origin` with no token, and a wrong token each gave 419 with no draft; the page's own token gave a single 303 to the live page; repeating it resumed the same draft; `GET /assessments/create` created nothing; the manual Step 1 POST with a valid token was refused.

### The link: what it is and what to watch

Besides the typed code, the Psychometrician can send the student PC a **link**: the live page's **Copy link** button copies `REMOTE_ASSESSMENT_URL/s/t/<token>`, where the token is a fresh 256-bit random value (stored only as a digest).

- **Opening the link uses nothing up.** It only shows a **Begin** page. Only pressing Begin (a form POST) claims the draft for that PC. A link preview (a chat app showing a preview card) or a safe-browsing scanner that merely opens the link can't spend it; tests open it many times with such user agents.
- **One device, whichever comes first.** The code and the link belong to the same draft: whichever is used first claims it, and the other then gets the generic message (and the live page counts "another device tried"). They share one expiry and one cleanup; **New code** replaces both, and the old link stops working.
- **Where the link appears.** On the staff live page only, inside the Copy link button (never as visible text or as a clickable link), only until a device has the draft, never in the page's background checks, and never on the student device: the Begin page's form posts back to its own address, so the token is in none of its HTML. On the student PC it is visible only in the address bar of the Begin page, and that page redirects away from it as soon as Begin is pressed.
- **Copying on plain HTTP.** Browsers only allow the modern clipboard on HTTPS (or `localhost`). Over `http://` on the LAN the button falls back to the older copy command; if that fails too, it shows the link in a read-only field to copy with Ctrl+C.
- **The risk of sending it.** A link pasted into a third-party messaging service (Messenger, Viber, email…) **passes the token through that service**: it is stored on their servers, may be previewed, and may be scanned. **The typed code avoids this entirely**, because the code is read off the screen and typed, never sent. If the link is used, send it through an internal channel, only just before the student begins, and never in a public or group chat. It works once and expires with the session.
- **Scanners that press buttons.** A rare security scanner that also submits forms could press Begin before the student. The student would then see the generic message and the live page would show a device as connected: press **New code** and use the code. The allowlist (below) stops scanners from outside the school entirely.
- **What NORMI never logs, and what the web server does.** NORMI's own logs (`storage/logs`) never contain the token: exception stack traces are written without function arguments (`zend.exception_ignore_args`, set in `AppServiceProvider`; a test forces errors on the link pages and searches `storage/logs`). But two things outside NORMI do see it:
  - **The web server's own access log** — `php -S` output, Apache, nginx or IIS — records every request line, including `/s/t/<token>`.
  - **The same-origin `Referer`.** The Begin page's own requests (its stylesheet and script) carry the Begin address, token included, in their `Referer` header to the same server, so they appear in that access log too.

  So **the server's access log must not be shared** (keep it readable by the administrator only). A token in an access log is useless once its link has been used or has expired.
- **Its column.** The link's `token_hash` column was dropped by migration `2026_10_08_130000_drop_token_hash_…` when the link was briefly removed, and re-added by `2026_10_08_140000_restore_token_hash_…`. The drop is left untouched, because it may already have run somewhere. The restore is guarded with `Schema::hasColumn`: it adds the column only when missing, and its rollback removes the column whenever present. Tested on databases where the drop had and had not run: `migrate` always ends with the column present and unique; one rollback leaves the state after the drop, and a second brings the original column back. The drop migration itself can't be guarded without editing it; it needs no guard in normal order, but a manual, out-of-order rollback of the drop alone (while the restore is still applied) would fail with a "duplicate column" error.

### Only the guidance office's PC: the IP allowlist

The student device is a PC provided by the guidance office, never a phone or tablet, and never the Psychometrician's own PC. To make the student page open only there, set **`REMOTE_ASSESSMENT_ALLOWED_IPS`** in `.env`: comma-separated IPv4 or IPv6 addresses or CIDR ranges, e.g. `192.168.1.20` or `192.168.1.20,192.168.1.21` or `192.168.1.16/28`. Empty (the default) means no restriction, exactly as before.

- **How it refuses.** Every request to `/s` is checked first, before anything else runs: no cookie is read, no staff-browser check, no rate limiter, no database query. An address not on the list gets exactly the reply an invalid code gets: the same 404 status, headers and generic page ("This page is not available. Please ask the psychometrician for help."), or `{"state":"unavailable"}` for the page's background requests. Even a wrong-method request or an unknown `/s` path gets that 404. Nothing in the reply hints that an allowlist exists (this is also why no student-device response carries `X-RateLimit-*` headers any more). Tests check all of this.
- **Only the request's real address counts — never the browser's "user agent"**, which anyone can change. The address is the one the server sees directly. Only a reverse proxy on the server itself (`127.0.0.1`, `trustProxies` in `bootstrap/app.php`) may say who the client is; an `X-Forwarded-For` or similar header from anywhere else is ignored (tested). **`trustProxies` must never be set to `*` while an allowlist is used**: anyone could then claim to be the student PC.
- **Typos fail closed.** An entry that isn't a valid address or range matches nothing; a list of only typos locks every PC out. The live page lists the allowed addresses and any invalid entries, for the Psychometrician only.
- **Rate limits.** A refused address uses up nothing (it never reaches the limiters); listed PCs are limited as before.
- **NAT.** On the school LAN the server sees each PC's own private address, so the list can name one PC. If NORMI were hosted on the internet, every school PC would share the school's one public address, and the list could only say "from the school".
- **If the PC's address changes** (DHCP), that PC gets the generic page everywhere, and the live page stays at "Waiting for the student device". Give the student PC a **fixed IP or a DHCP reservation**. If Windows connects over IPv6, list that address too. After changing `.env`, run `php artisan config:clear` (or `config:cache` again if the config is cached).
- **Testing on one PC.** Opening the student address on the server PC itself through its LAN address (e.g. `http://192.168.1.10/s`) makes the request come from that **LAN address, not `127.0.0.1`**: list the server's own LAN IP for such a test, or leave the list empty.
- **What it is, and what it isn't.** "Guidance-provided PC only" is enforced by **this allowlist plus the office's procedure**, not by the browser. It identifies a **machine, not a student**: who sits at that PC is still checked in person by the Psychometrician, who ticks the attestation on Submit.

### Setting up the guidance office (checklist)

**The server (the Psychometrician's PC, or the PC that runs NORMI):**
- The web server listens on all addresses (`0.0.0.0`) or on its LAN address, not only on `127.0.0.1`.
- A Windows Firewall inbound rule allows the web server's port on the **Private** profile only, and the school network is set to Private on that PC. For example, in an administrator PowerShell: `New-NetFirewallRule -DisplayName "NORMI web" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private` (use 443 for HTTPS).
- `.env`: `REMOTE_ASSESSMENT_URL=http://<server LAN IP>` (e.g. `http://192.168.1.10`), `APP_DEBUG=false`, and `REMOTE_ASSESSMENT_ALLOWED_IPS=<student PC IP>`.
- **HTTPS is recommended**, with a locally trusted certificate (e.g. made with mkcert, its root certificate installed on the student PC). Over plain HTTP the student's details and answers cross the network unencrypted, and the Copy link button falls back to the older copy method.
- **Keep the web server's access log private.** `php -S` output, and Apache, nginx or IIS access logs, record the link's token when a link is opened (also via the same-origin `Referer` of the Begin page's own requests). Don't share or publish that log; it only matters until the link is used or expires.
- `npm run build` has been run (never `npm run dev` for the student PC), and the scheduler task runs `php artisan schedule:run` every minute (see Expiry and cleanup).

**The student PC (provided by the guidance office):**
- A **fixed IP or a DHCP reservation**, the one listed in `REMOTE_ASSESSMENT_ALLOWED_IPS`.
- A desktop shortcut that opens the student address in a private, full-screen window, e.g. target: `"C:\Program Files\Google\Chrome\Application\chrome.exe" --incognito --kiosk http://192.168.1.10/s`. The student then only types the code. **Close it with Alt+F4** (kiosk mode has no close button).
- **Prefer the typed code to the link.** If the link is used, get it to the student PC through an internal channel (never a public or group chat, never a third-party messaging app if it can be avoided), only just before the student begins; open it in the incognito window; the student presses Begin.
- **Between students:** close the window and open the shortcut again. Closing an incognito window throws away its cookies and everything typed in it; a student who closes it mid-way needs **New code**. If the PC's browser is ever used without incognito, clear its browsing data (Ctrl+Shift+Delete → cookies and site data, autofill form data) before the next student.
- No staff account is signed in on that browser (the student page refuses a browser signed in to staff, and incognito avoids it).

### What happens when…

- **…the student closes the tab and opens it again:** the device cookie brings them back to their answers. After a closed *private* window the cookie is gone, so the device is refused; press **New code**.
- **…the Psychometrician cancels or starts Step 1 again:** the draft is deleted, and the student device shows the generic message within a few seconds.
- **…another questionnaire version is activated mid-answer:** the live page warns, Submit is refused, and **Restart on the new version** clears the answers. The device reloads with the new questions.
- **…the time runs out:** the draft and its answers are gone; send to the student device again.
- **…two devices try the same code:** the first wins; the second gets the generic message, and the live page shows "Another device tried to open this code".
- **…the student PC is shared:** nothing is cached, the answers are removed from the page after Done, and Back shows only the thank-you message. Once the draft ends, the cookie is useless. A browser signed in to a staff account is refused. A kiosk or private browser profile on the student PC is still recommended.
- **…the network drops:** the answer stays on screen, the status line says "Not saved — reconnecting…", it is retried automatically, and Done stays disabled until everything is saved. The live page shows when the device was last seen.
- **…another PC, or the Psychometrician's own PC, opens the address while an allowlist is set:** it gets the generic message, the same as for an invalid code.
- **…the student PC's address changed:** it gets the generic message everywhere; fix the DHCP reservation or `REMOTE_ASSESSMENT_ALLOWED_IPS` (see the allowlist section).
- **…the student taps an answer before the page has finished loading:** the page sends that answer as soon as its script starts, so it isn't lost.
- **…the student mistypes their name:** the Psychometrician corrects it on the live page before submitting; the answers are kept as they are.
- **…the student types a name that is already registered:** the device stops with the generic message, and the live page shows the record with Take Again (see [When the student fills in Step 1 too](#when-the-student-fills-in-step-1-too)).
- **…the student presses Back or reloads after saving the details:** nothing is cached, so the page is fetched again, and the server shows only "Details saved ✓" above the questions, never the form. (Checked in Chrome only.)
- **…the student tries to answer before saving the details:** the statements are disabled and nothing is sent; even a request sent by hand is refused by the server.

---

## The AI Classification System (Full Detail)

This is the part of the system doing the actual "AI" work the capstone is built around, so it's documented in full technical detail here.

### The Strategy Pattern: two interchangeable classification engines

The system defines one contract — `AIProviderInterface` — with a single method, `classify()`. Two different implementations of that same contract exist, and the system can swap between them with one line in a config file (`config/ai.php`, controlled by the `AI_PROVIDER` environment variable), without touching any Controller, Service, or database schema:

1. **`RuleBasedDASSProvider`** (the default) — a deterministic decision engine. It looks up each of the three final scores against the `classification_thresholds` database table and returns whichever severity tier's [min, max] range contains that score. No hardcoded cutoff numbers exist anywhere in the code — every number comes from the database, which means changing an official threshold in Settings takes effect immediately, system-wide, with no code change.
2. **`ClaudeAIProvider`** — sends the scores to Anthropic's Claude API and asks it to classify them, described in full below.

**Why build a deterministic rule-based engine at all, instead of just using the AI?** Because DASS-21 classification is fundamentally a lookup, not a judgment call — the official scoring manual publishes exact numeric cutoffs. A rule-based lookup against those exact cutoffs is *always* correct by definition, and it's what makes the Claude provider's own output verifiable (see the safeguard below). It also means the system is never fully dependent on an external, paid, internet-connected API — it has a fully working, self-contained classification engine even if `AI_PROVIDER` is set to `rule_based`, or if the Claude API is ever unreachable.

### How the Claude provider works, step by step

1. **Compute the three subscale scores** the normal way (see DASS-21 Scoring below).
2. **Build the official thresholds object** fresh from the database (never hardcoded) — including converting the top severity tier's upper bound to `null`, to correctly represent that DASS-21's highest tier is open-ended ("28 and above," not "28 to some arbitrary cap") even though the database internally stores a numeric cap for query purposes.
3. **Send one request to Claude's Messages API** containing:
   - A **system prompt** — strict instructions on exactly what the AI is and is not allowed to do.
   - A **user message** — the actual scores plus the official thresholds, as JSON.
   - A **forced tool call** — instructing Claude that it *must* respond by calling a specific function with a specific structure, rather than replying with free-form text.
4. **Extract and validate the structured reply.**
5. **Cross-check it against the rule-based engine's own answer for the same input** (explained in detail below).

**Choosing `CLAUDE_MODEL`:** the configured model (`claude-sonnet-5`) must support a *forced* tool call (`tool_choice` naming a specific tool), which step 3 depends on. Not every Claude model does — some newer models reject a forced tool call outright. On such a model every request fails, and because the provider falls back to the rule-based engine on any failure, the app would keep working while silently never using Claude (every result recorded as `rule_based`, with a "Claude AI classification failed" warning in the log). So before changing `CLAUDE_MODEL`, verify the new model with a live check: with `AI_PROVIDER=claude`, complete one test assessment and confirm its result shows `ai_provider = claude` and no such warning was logged.

**Timeouts (`CLAUDE_TIMEOUT`, `CLAUDE_CONNECT_TIMEOUT`):** the Claude request gives up after `CLAUDE_TIMEOUT` seconds in total (default **12**) and after `CLAUDE_CONNECT_TIMEOUT` seconds if it can't even connect (default **4**). A request that gives up falls back to the rule-based result like any other failure, so Step 3 then opens with "Classified by: rule_based" and a "Claude AI classification failed … cURL error 28: Operation timed out" warning in the log. The total is **capped at 20 seconds in code** (`ClaudeAIProvider::MAX_TIMEOUT_SECONDS`), whatever `.env` says, and the connect timeout never exceeds the total; a blank, non-numeric or zero value uses the default. The cap exists because PHP's own time limit (`max_execution_time`, 30 seconds under `php -S`; on Windows it counts time spent waiting on the network) is a fatal error, not an exception: before the cap, a slow API reply turned Step 3 into a "Server Error" page after 30 seconds instead of falling back. Checked on 2026-10-09 with `php -S` against a local endpoint that never answers: before, HTTP 500 after 30.7 s; after, a clean `rule_based` result after 12.3 s (default) and 20.3 s (`CLAUDE_TIMEOUT=45`). While Step 3 waits on Claude, `php -S` (one request at a time) also holds back the student PC's and the live page's requests, which is another reason to keep the timeout short.

### The exact JSON payload sent to Claude

This is the literal structure of the `user` message content, built fresh from real data on every single classification request:

```json
{
  "assessment": {
    "depression_score": 8,
    "anxiety_score": 6,
    "stress_score": 10
  },
  "official_thresholds": {
    "depression": {
      "normal": [0, 9],
      "mild": [10, 13],
      "moderate": [14, 20],
      "severe": [21, 27],
      "extremely_severe": [28, null]
    },
    "anxiety": {
      "normal": [0, 7],
      "mild": [8, 9],
      "moderate": [10, 14],
      "severe": [15, 19],
      "extremely_severe": [20, null]
    },
    "stress": {
      "normal": [0, 14],
      "mild": [15, 18],
      "moderate": [19, 25],
      "severe": [26, 33],
      "extremely_severe": [34, null]
    }
  }
}
```

*(The score values above are just an example — the real values are whatever that specific student actually scored. The threshold ranges shown are the system's real, currently-configured official DASS-21 cutoffs, pulled live from the database at request time.)*

### The system prompt (the AI's exact instructions)

This is the literal text sent as the `system` parameter of the API call — it's what tells Claude what its job is and, critically, what it is *not* allowed to do. It is sent as JSON, built from a PHP array with `json_encode()`: each string value is one line of the instructions, unchanged and in order (bullet markers included), and the four keys `role`, `background`, `task` and `rules` are the only additions:

```json
{
    "role": "You are a strict classification lookup engine for a DASS-21 (Depression, Anxiety, Stress Scale) mental health assessment system.",
    "background": [
        "Background (context only; it does not change your task):",
        "- The DASS-21 is a 21-item self-report questionnaire with 7 items for each of three subscales: Depression, Anxiety and Stress. Each item is answered on a 0-3 scale.",
        "- A subscale's score is the sum of its 7 answers multiplied by 2, giving a final score from 0 to 42.",
        "- The scores in the user message are already these final, doubled scores. Use them exactly as given; do not halve, double or otherwise recompute them.",
        "- The five severity tiers, from least to most severe, are: Normal, Mild, Moderate, Severe, Extremely Severe.",
        "- The DASS-21 is a screening instrument, not a diagnosis. Your classification is a screening result that a qualified professional reviews."
    ],
    "task": "Your ONLY task is to classify three subscale scores (depression, anxiety, stress) into their official severity tier by looking up which range in the \"official_thresholds\" object of the user's message contains each score. A score belongs to a tier when it falls within that tier's inclusive [min, max] range; a null max means the range is unbounded upward.",
    "rules": [
        "Rules you must follow exactly:",
        "- Use ONLY the threshold ranges provided in the user message. Do not use any outside knowledge of DASS-21 cutoffs, even if it seems to conflict with the provided ranges.",
        "- Do not guess, estimate, round, or reason clinically about the scores. This is a literal lookup, not a clinical judgment.",
        "- The keys in \"official_thresholds\" use snake_case tier names (e.g. \"extremely_severe\"). Report your classification using the Title Case form of that same tier name (e.g. \"Extremely Severe\").",
        "- You must report your classification by calling the classify_dass_subscales tool. Do not respond with any other text."
    ]
}
```

**Why word it this strictly?** Large language models are trained on huge amounts of general text, which can include generic (and possibly outdated, or differently-sourced) DASS-21 cutoff numbers "baked in" from training. The instruction to use *only* the ranges provided — even if they "seem to conflict" with what it might otherwise assume — exists specifically to force it to defer to *this school's actual configured thresholds* (which an administrator can adjust in Settings) rather than some generic memorized version. The "this is a literal lookup, not a clinical judgment" line exists to stop the model from trying to be clever — e.g., second-guessing a boundary score — when the whole point is a mechanical, reproducible lookup.

**Why include DASS-21 background at all?** So the model understands what the numbers *are* — final, already-doubled subscale scores on a 0–42 scale, with five named tiers — and treats its output as a screening result rather than a diagnosis. The background deliberately contains **no cutoff numbers**: every tier boundary still comes only from the `official_thresholds` JSON, built from the database on each request, so an administrator's threshold change in Settings still takes effect without touching the prompt. It also explicitly forbids recomputing the scores, since knowing about the "× 2" could otherwise tempt the model to halve them before the lookup.

### The forced tool-use JSON Schema (requesting structured output)

Rather than just *asking* Claude to reply in JSON (which can still occasionally produce malformed or explanatory text around the JSON), the request uses Claude's **tool use** feature: it defines a "tool" (essentially a function signature) and forces Claude to call it, with `tool_choice` explicitly set to require exactly that tool, so Claude replies with structured data shaped by this schema rather than prose. The request sets no `strict` mode, so the app does not rely on the API to enforce the schema — its own check of every reply (see "Valid values, checked by the app" below) is what enforces it:

```json
{
  "name": "classify_dass_subscales",
  "description": "Report the classified DASS-21 severity tier for each subscale, determined strictly by looking up each score against the official_thresholds ranges provided in the user message.",
  "input_schema": {
    "type": "object",
    "properties": {
      "depression_level": {
        "type": "string",
        "enum": ["Normal", "Mild", "Moderate", "Severe", "Extremely Severe"]
      },
      "anxiety_level": {
        "type": "string",
        "enum": ["Normal", "Mild", "Moderate", "Severe", "Extremely Severe"]
      },
      "stress_level": {
        "type": "string",
        "enum": ["Normal", "Mild", "Moderate", "Severe", "Extremely Severe"]
      }
    },
    "required": ["depression_level", "anxiety_level", "stress_level"]
  }
}
```

The full request also sets:
```json
"tool_choice": { "type": "tool", "name": "classify_dass_subscales" }
```
which tells Claude it is *not allowed* to answer in any other way — it must call this exact function with these exact fields.

### Why JSON was used instead of plain text

- **Unambiguous parsing.** A plain-text reply like *"The depression score of 8 falls in the Mild range"* would need to be parsed with guesswork (what if the wording varies slightly? what if it says "mild" in lowercase, or "10-13" instead of "Mild"?). A JSON object with an `enum`-constrained field has exactly one valid shape — the code either finds a valid value or it doesn't, with no ambiguity in between.
- **Valid values, checked by the app.** The schema's `enum` lists the five real severity tier names, but what actually enforces them is the app's own check of the reply (`ClaudeAIProvider::extractToolInput()`), not the API: the reply must contain a `classify_dass_subscales` tool call with all three fields present, each exactly one of the five Title Case names (`Severe` passes; `severe` or `Critical` does not). The request sets no `strict` mode. Any reply that fails the check is treated as a failed call, logged, and replaced by the rule-based result.
- **Industry best practice for AI-to-system integration.** Whenever an AI's output needs to be consumed by another program (rather than read by a human), forcing structured output is the standard, recommended approach specifically because it removes the need for fragile text-parsing logic, which is one of the most common sources of bugs in AI-integrated systems.
- **Machine-checkable.** Because the expected shape is fixed, the response can be validated with simple code (checking three fields exist and are one of five valid strings) rather than complex text-pattern matching that could silently misinterpret a reply.

### The accuracy safeguard: cross-checking against the rule-based engine

Because the rule-based lookup is deterministic and always correct by definition (it's a direct database lookup against the exact same numbers Claude was given), **every single Claude response is automatically compared against what the rule-based engine computed for the exact same input**, before it is trusted:

- **If they agree** on all three subscales → the Claude result is used, and `dass_results.ai_provider` is recorded as `"claude"`.
- **If they disagree on even one subscale** → the discrepancy (including both results and the input scores) is written to the application log for review, and the system silently falls back to the rule-based result instead. `ai_provider` is recorded as `"rule_based"`.
- **If the Claude API call fails outright** (network error, timeout — `CLAUDE_TIMEOUT`, default 12 s, capped at 20 s; see "Timeouts" above — malformed/missing tool call, API error) → the same fallback happens, also logged.

These warnings go to the application log (`storage/logs/laravel.log`). Their `assessment_id` is always empty, because classification runs before the assessment is saved; a log entry can be matched to an assessment by its time and scores.

The rule-based lookup itself runs first, outside this fallback. If a score falls in no threshold band, it stops with an error rather than guessing, in either mode: Step 3 shows a server error page and nothing is saved. Override Mode refuses any change that would leave such a gap (see [Classification Thresholds & Settings](#classification-thresholds--settings)), so this can only happen if the table is edited directly in the database.

**Why this matters:** it means an incorrect AI classification can *never* actually reach the Psychometrician's review screen or be saved to the database — the worst thing a Claude malfunction can do is silently fall back to the (always-correct) rule-based answer. The `ai_provider` column on every saved result is a truthful record of which engine's answer was *actually used*, which matters both for transparency and because a capstone/thesis needs to be able to demonstrate this safeguard is real, not just claimed.

### The mandatory pre-save review — why the AI's raw output never triggers anything by itself

This is worth restating clearly because it's a core safety property of the whole system: **whichever classification comes out of the AI Classification step above — Claude or rule-based — is never what decides whether a case gets flagged or a Guidance Counselor gets notified.**

The actual sequence is:
1. AI Classification produces a raw severity per subscale (Depression/Anxiety/Stress).
2. The Psychometrician reviews it at Step 3 and either **Confirms** it as-is, or **Corrects** one or more subscales.
3. The system computes an **effective result**: for any subscale that was confirmed, the effective level is the AI's raw level; for any subscale that was corrected, the effective level is the Psychometrician's corrected level instead.
4. **Differentiated Flagging (below) is evaluated only against this effective result — never against the AI's raw output directly.**

So if the AI raw-classifies a subscale as *Extremely Severe*, but the Psychometrician corrects it down to *Normal* based on their own clinical judgment, **no flag is created and no Guidance Counselor is notified** — because the thing that actually happened, as far as the system is concerned, is what the Psychometrician decided, not what the AI initially suggested. The AI's original raw output is still permanently saved for the audit trail — it's never overwritten — and the Psychometrician can still see it, but it is not what drives any downstream action, and the Guidance Counselor's screens never show it: they show the reviewed level instead (see [What the Guidance Counselor sees about a review](#what-the-guidance-counselor-sees-about-a-review) below).

**Why build it this way?** If the AI's raw output directly triggered flags/notifications, then a Psychometrician correcting an AI mistake would be pointless — the (possibly wrong) alarm would already be out the door. Routing every downstream consequence through the human-reviewed, *effective* result is what makes the mandatory review step meaningful rather than a rubber stamp.

### What the Guidance Counselor sees about a review

The Guidance Counselor works from the **reviewed** classification, not the AI's raw one:

- **Reviewed levels on every Counselor screen.** The Notifications inbox, the Flagged Cases list, the assessment page, the Guidance Counselor Dashboard's Recent Assessments, the counseling session pages (including the Related Assessment picker on the session form), Assessment History, and the Counselor's printed/PDF copies of the Assessment Report and Student Assessment History Report all show the reviewed severity level — the Psychometrician's correction where one was made, the AI's level otherwise. The same rule decides flagging, so what the Counselor sees always matches the flags.
- **Notification text.** Each notification names the reviewed level of the subscale that raised the flag (e.g. "… was assessed with Severe Stress …").
- **A "Corrected by Psychometrician" badge** appears in the inbox, on the Flagged Cases list, and at the top of the assessment page when the Psychometrician really changed the AI's classification: the review was a Correct and at least one subscale was set to a level different from the AI's. A Confirm, a Correct whose picks all equal the AI's levels, or an older Confirm that happens to have corrections stored, shows no badge. The badge marks the whole assessment; it never says which subscale changed, what the AI's level was, or whether the level went up or down.
- **Scores stay visible.** The numeric DASS-21 scores are still shown to the Counselor. Because the AI's classification is a direct lookup of each score against the published cutoffs, someone who knows the cutoff table could work out the AI's level for a corrected subscale from its score.
- **"Classified by" and the Non-Official Thresholds badge.** The assessment page's scores card shows both roles which engine produced the saved classification ("Classified by: claude" or "rule_based") and, when it applies, the "⚠ Non-Official Thresholds" badge. Neither reveals the AI's level.
- **The Prediction Feedback card** on the assessment page is shown to both roles as before: "Confirmed" or "Corrected" by the Psychometrician, the corrected level for each changed subscale, and any notes. It does not list the AI's levels.

**What stays the same for the Psychometrician:** every Psychometrician screen — the Psychometrician Dashboard and its counts and charts, the student profile, Assessment History, the assessment page and both reports — still shows the AI's raw levels, alongside the review in the Prediction Feedback card. The **Assessment Summary Report** (both roles) counts each subscale's severity breakdown on a different basis per role, and says so on the report, on screen and in its print/PDF: the Guidance Counselor's copy counts the **reviewed** levels ("Counts use the reviewed classification…"), so its Severe/Extremely Severe counts agree with its Counseling Endorsement and Awareness Notification totals; the Psychometrician's copy keeps counting the **AI's** levels ("Severity counts use the AI's classification before review…"), so those counts can differ from the flag totals where a level was corrected. The flag totals themselves always come from the flags, which follow the reviewed levels.

Before any of the classification above happens, the 21 raw answers first have to become three subscale scores — that calculation is its own dedicated step, covered in full next.

---

## DASS-21 Scoring — How Raw Answers Become Final Scores

This is the calculation that turns 21 individual answers into the three numbers (one per subscale) that everything else in the AI Classification section above actually operates on. It happens **before** any AI or threshold lookup — scoring and classification are two separate steps, not one.

### Where this logic lives in the code

It's implemented in a single, small, self-contained class: **`app/Services/DassScoringService.php`**. This class does arithmetic only — it never talks to the AI, never queries the `classification_thresholds` table, and never decides a severity level. It takes answers in and hands raw numbers back out, nothing more.

**Why keep it so deliberately separate?** Scoring is pure, uncontroversial arithmetic defined by the official DASS-21 manual — it should never be capable of being "wrong" in the way an AI classification theoretically could be, and it should be trivially easy to test and trust on its own. Bundling it into the same code that talks to an external AI API would blur that line; keeping it as its own dependency-free class means it can be verified completely independently of anything AI-related, and it never changes no matter which AI Classification provider (rule-based or Claude) happens to be active.

### Which questions belong to which subscale

Every one of the 21 DASS-21 questions belongs to exactly one of the three subscales. This assignment is fixed by the official DASS-21 instrument itself (it's part of the seeded questionnaire data, not something staff configure), and matches the item numbers below:

| Subscale | Question (item) numbers |
|---|---|
| **Stress** | 1, 6, 8, 11, 12, 14, 18 |
| **Anxiety** | 2, 4, 7, 9, 15, 19, 20 |
| **Depression** | 3, 5, 10, 13, 16, 17, 21 |

Each subscale has exactly **7 questions**. For example, Question 1 ("I found it hard to wind down") counts toward Stress; Question 3 ("I couldn't seem to experience any positive feeling at all") counts toward Depression; Question 2 ("I was aware of dryness of my mouth") counts toward Anxiety — and so on for all 21.

### The calculation, step by step

Every question is answered on the same 0–3 scale described earlier (0 = *Did not apply to me at all* … 3 = *Applied to me very much, or most of the time*).

**Step 1 — Raw score:**
```
raw score = sum of that subscale's 7 answers
```
For each subscale, add up the answer values (0–3 each) of that subscale's 7 questions. Since each of the 7 answers can be 0–3, a raw score always falls between **0 and 21** for each subscale.

**Step 2 — Final score:**
```
final score = raw score × 2
```
The official DASS-21 scale requires **doubling** the raw score — this is a standard part of the instrument itself (the DASS-21 is a shortened version of the original 42-question DASS, and doubling the 21-question raw total puts it back on the same scoring scale the official severity cutoffs were originally published against). A final score therefore always falls between **0 and 42** — which is exactly the range the official severity threshold table (see the AI Classification section above) is calibrated against.

### Worked example

Say a student's 7 Stress-subscale answers are: `1, 2, 1, 3, 2, 1, 2`.

- Raw Stress score = 1+2+1+3+2+1+2 = **12**
- Final Stress score = 12 × 2 = **24**

That final score of 24 is what actually gets sent into AI Classification and looked up against the Stress threshold table (e.g., landing in the "Moderate" range of 19–25, per the currently configured official thresholds). The exact same process runs independently for Depression and Anxiety, using their own 7 questions each.

### Why this two-step split (raw, then final) matters

Both numbers are kept, not just the final one — `dass_results` stores `depression_raw_score` alongside `depression_final_score` (and the same for the other two subscales). Keeping the raw score on record, not just the doubled final score, means the actual original answers-total is always independently auditable later, rather than only ever seeing the already-transformed number.

---

## Differentiated Flagging

After an assessment is saved, the system automatically checks the *effective* (Psychometrician-reviewed) severity of each of the three subscales **independently** and creates a "Flagged Case" for any that reach Severe or Extremely Severe:

| Subscale hits Severe/Extremely Severe | What happens |
|---|---|
| **Stress** | A `counseling_endorsement` flag is created — this is the more urgent category. |
| **Depression** | A `awareness_notification` flag is created. |
| **Anxiety** | A separate `awareness_notification` flag is created. |

Because each subscale is checked independently, a single assessment can produce **zero, one, two, or three** flagged-case rows. Every newly-created flag sends a notification to every active Guidance Counselor — **never to the Psychometrician**, since she already sees the result immediately on the review screen and doesn't need a separate alert about her own just-completed assessment.

**Why differentiate Stress from Depression/Anxiety at all, instead of one generic "flagged" status?** Because they call for different kinds of follow-up in practice — severe Stress specifically warrants a counseling referral, while severe Depression/Anxiety warrant the guidance office simply being made aware, which is a real, meaningful clinical distinction the system's labels reflect directly.

---

## The "Take Again" Retake Feature

Lets a Psychometrician run a brand-new assessment on a student who is **already registered** in the system, without re-typing their name, course, section, or year level — and without creating a duplicate student record.

**How it works:** clicking "Take Again" on an existing student's row stages that student's existing information into the wizard session and skips straight to Step 2 (Questionnaire) — Step 1 is bypassed entirely since the student's info is already known. Because Step 1 is skipped, the student's privacy consent (normally captured on Step 1) is instead captured on Step 2 for a retake — the retake flow adds a required consent checkbox there specifically to cover this gap. When a retake is sent to a student device, that checkbox moves onto the live page's **Submit** form, and is required there. The student device then shows only the privacy notice and the questionnaire, never the details form: the student is picked on the Psychometrician's own PC. With student entry only (the default), the retake's Step 2 offers only Send to student device — the student answers on a different PC.

**Why this exists:** without it, every retake would either (a) require manually re-typing a returning student's full information every time, inviting typos and duplicate near-identical student records, or (b) require a "search for existing student" step baked into every single New Assessment run, slowing down the much more common case of a brand-new student encounter. The regular wizard always registers a new student, and retake is a separate, explicit entry point, which keeps both paths simple. The two are tied together by the duplicate check on Step 1 (see [Duplicate students](#duplicate-students-step-1-refuses-a-student-who-is-already-registered)): if the Psychometrician types in a student who is already registered, the wizard stops and sends them to that student's Take Again instead. Take Again itself is never subject to that check. It isn't available for archived students (the link returns "not found"), which is why an archived name match only warns rather than blocks.

---

## Assessment History

A shared, searchable, read-only listing of every completed assessment — available to both roles, since both legitimately need to look up past results (the Psychometrician for record-keeping, the Guidance Counselor for case history).

### Back links on the assessment page

Many pages link to the same assessment page, so it offers a "back" link only when it can tell where the user actually came from. It works this out from the browser's *Referer* (the address of the previous page), with the same safety rules for every link:

- The previous page must be on this app; an outside site never counts.
- Its address must match the page the link leads back to **exactly** — not just end the same way (for example, a student's Counseling History page, `/counseling-sessions/students/5`, is never mistaken for that student's profile, `/students/5`).
- The link is always rebuilt by the app itself, never copied from the previous address. Only a fixed list of that page's own filters is carried back (listed below); anything else in the address is dropped, and a page number is only kept when it is a whole number of 2 or more.
- It only appears for a user whose role can open the page it leads back to.

If the browser sends no Referer (some privacy settings or extensions strip it), no back link is shown and the user simply uses the browser's own Back button.

| Arrived from | Who | Link shown | Carried back |
|---|---|---|---|
| Their own dashboard | Both (own dashboard only) | **← Back to Dashboard** | Psychometrician: period, course, year level, severity card filter, and the All Assessments table's page. Guidance Counselor: nothing (that dashboard has no filters or pages) |
| The profile of the student this assessment belongs to | Psychometrician | **← Back to Student Profile** | Page of the profile's Assessment History. Another student's profile, or an archived student, never produces it |
| Assessment History | Both | **← Back to Assessment History** | Name search, student number filter, page |
| Flagged Cases | Guidance Counselor | **← Back to Flagged Cases** | Tab, name search, course, year level, section, date range, page |
| Notifications (opening a notification) | Guidance Counselor | **← Back to Notifications** | Whether the archived view was on, page |
| A counseling session linked to this assessment | Guidance Counselor | **Back to Counseling Session** | — |
| A student's Counseling History page, when one of their sessions is linked to this assessment | Guidance Counselor | **Back to Counseling History** | — |

**Only one back link can ever apply.** Each link matches one specific page's address exactly, and a request has only one previous address, so no two links can match at the same time — there is no "which one wins" rule to apply.

**Notifications goes through a redirect.** Opening a notification first marks it as read, then redirects to the assessment. Browsers keep the Notifications page as the previous address across that redirect (checked in headless Chrome and Edge before this link was built), which is what lets "← Back to Notifications" work.

Straight after saving a new assessment, no back link is shown — the wizard is finished, so there is nothing sensible to go back to.

---

## Flagged Cases (Guidance Counselor)

The Guidance Counselor's main working list — every assessment that has at least one flagged case attached to it, organized into tabs:

- **All** — every flagged assessment.
- **Endorsement** — assessments with a `counseling_endorsement` flag (Severe/Extremely Severe Stress).
- **Notification** — assessments with an `awareness_notification` flag but *no* endorsement flag.
- **Normal** — assessments with *no* flags at all (useful as a "confirmed clear" reference view).

Supports filtering by course, year level, section, and a date range, on top of name search — all combinable at once (see the [Search & Filter](#search--filter-system--how-it-works-everywhere) section for exactly how that combination works).

The Stress, Anxiety and Depression columns show the reviewed severity levels, and a "Corrected by Psychometrician" badge appears under the flag when the AI's classification was really changed at review (see [What the Guidance Counselor sees about a review](#what-the-guidance-counselor-sees-about-a-review)).

---

## Notifications

The Guidance Counselor's inbox for flagged-case alerts. Each notification links directly to the assessment that triggered it, names the reviewed severity level that raised the flag, and carries a "Corrected by Psychometrician" badge when the AI's classification was really changed at review (see [What the Guidance Counselor sees about a review](#what-the-guidance-counselor-sees-about-a-review)). The unread count in the sidebar is unaffected by corrections.

**Archive / Unarchive:** a notification can be archived to hide it from the default inbox view without deleting it — the row stays in the database permanently (for accountability — there's no way to make a real notification simply vanish), and a separate "View Archived" toggle shows them again on demand. Archiving an already-archived notification is a safe no-op (it doesn't reset the archive timestamp). Viewing a notification automatically marks it as read and redirects straight to the underlying assessment.

**Why archive instead of delete?** A Guidance Counselor needs to be able to clean up their working inbox without losing the historical record of "a notification about this case existed and was seen" — which matters for accountability if a case is ever reviewed later.

---

## Counseling Sessions

Where a Guidance Counselor records an actual counseling session held with a student — session date/time, notes, follow-up requirements, and confidentiality level — optionally linked to the specific assessment that prompted it.

Creating a session starts with searching for the student by name (the same shared search pattern used everywhere — see below); if the search matches exactly one student, that student is pre-selected automatically to save a click.

**Archived students:** a new session can't be created for an archived student (the search doesn't list them, and the save refuses one). Sessions recorded before the student was archived stay fully viewable and editable, and the student's Counseling History page stays open. A session's student can never be changed after it is created, and a session can only be linked to an assessment of its own student.

**Completed sessions can't be in the future:** a session marked Completed cannot have a session date after today ("A Completed session cannot be dated after today. Set the status to Scheduled, or correct the date."). The check is by date, not time, so a session completed today is accepted whatever its time. It applies on both create and edit, and only to Completed — a Scheduled session can be booked for any future date. Because follow-up status is based on the latest Completed session, this keeps "Needed"/"Overdue" tied to a session that actually took place. Sessions saved before this rule existed are not changed automatically: a Completed session that is still dated in the future must have its date corrected (or its status set back to Scheduled) the next time it is edited.

**Follow-up date:** when "Follow-up required" is ticked, a follow-up date is required and cannot be earlier than the session date (the same day is allowed). Unticking it clears any follow-up date on save, so a session never keeps a stale date.

**By Student tab and follow-up status:** besides the flat "All Sessions" list, the module has a "By Student" tab with one summary row per student who has at least one session — session count, last Completed session, next Scheduled session, and a follow-up status — and a per-student history page listing all of that student's sessions, most recent first. Archived students are included, because their history must stay viewable. Follow-up status is worked out on the fly, never stored:

- **Needed** — the student's latest *Completed* session has "Follow-up required" ticked, and no Scheduled or Completed session exists after it.
- **Overdue** — as Needed, and that session's follow-up date has already passed.
- **None** — otherwise.

Cancelled and No-Show sessions never count as the "last session" and never satisfy a pending follow-up. The list is sorted Overdue first, then Needed, and can be filtered to students needing follow-up (Needed includes Overdue) or Overdue only. Everything is computed in SQL from unencrypted columns, so session notes are never read for this list.

**Deleting a session** (Guidance Counselor; a Restricted session only by its own counselor) soft-deletes it: the row stays in the database with `deleted_at` set, but there is no Restore button, so to the Counselor it is gone for good — the dialog says "This action cannot be undone." It disappears from All Sessions, the By Student tab (its session count, last and next session), the student's history page, the Counseling Report and its PDF, and the assessment page's list of linked sessions. A student whose only session is deleted drops off the By Student tab entirely. Because follow-up status is recalculated from the sessions that remain:

- deleting the latest Completed session makes the Completed session before it count as the "last session" — its follow-up, if it had one, applies again (as Needed or Overdue), and a follow-up that only the deleted session required disappears;
- deleting the Scheduled or Completed session that satisfied a pending follow-up brings that follow-up back as Needed, or Overdue if its date has passed.

The Audit Log keeps a *Delete* entry with every field of the deleted session except the notes, which are shown only as `[changed]` (see Audit Logs below); the notes themselves remain only in the soft-deleted row.

**Per-student PDF:** the history page offers a print view and PDF download for that one student. It reuses the Counseling Report (filtered by `student_id`), so Restricted session notes stay redacted exactly as they are in the full report.

---

## Reports

A set of printable/downloadable (PDF) reports, all built from the same underlying filter system used throughout the app:

| Report | Who can see it | What it shows |
|---|---|---|
| **Assessment Summary Report** | Both roles | Institution-wide totals and per-condition breakdowns, filterable by course/year level/gender/date range. The severity breakdown counts the reviewed levels for the Guidance Counselor and the AI's levels for the Psychometrician; a note on the report states which. |
| **Flagged Students Report** | Guidance Counselor only | A consolidated view of flagged cases, filterable by flag type plus the usual course/section/date filters. |
| **Assessment Report** | Both roles | A single assessment's full detail, reached from Assessment History. |
| **Student Assessment History Report** | Both roles | One specific student's full history, reached from their profile page or from Assessment History filtered to that student. Archived students are included, the same as in Assessment History. |
| **Counseling Report** | Guidance Counselor only | A record of counseling sessions held, reached from the Counseling Sessions module. |

**Why PDF, and why reuse the same Blade views?** Every report has both a "Print" and a "PDF" version that render from the *exact same* Blade template — the PDF version just runs that same HTML through the `laravel-dompdf` library instead of sending it straight to the browser. This guarantees the on-screen preview and the downloaded PDF can never drift out of sync with each other, since they're not two separately-maintained versions of the same report.

---

## Classification Thresholds & Settings

Lets the Psychometrician view and, if necessary, override the official DASS-21 severity cutoff numbers that both AI Classification engines read from — and restore them back to the official published values at any time.

**Why allow overriding official, published clinical cutoffs at all?** It doesn't happen casually — every change is captured as a single, consolidated Audit Log entry showing exactly which rows changed and their old vs. new values, and the system displays a persistent warning banner anywhere thresholds are in effect that don't match the official values, so it's never silently forgotten that non-standard cutoffs are active. The override capability exists for edge cases (e.g., a future, revised, officially-published DASS-21 cutoff table) without needing a code deployment to update it — but the constant visible warning and full audit trail mean it can never be changed by accident or without a permanent record of who changed what.

**Every score must have exactly one level.** Saving in Override Mode is refused, with a message naming the subscale, the bands and the scores affected, unless each subscale's five bands, in severity order (Normal → Extremely Severe), start at 0, follow on from each other with no gap and no overlap, and the top band (Extremely Severe) ends at 42, the highest possible score. The top band still covers every score from its minimum up: 42 is only the stored cap, and the Claude provider still reports it as open-ended. Rows not included in a submission are checked at their current values. Without this rule a gap (e.g. Severe ending at 29 while Extremely Severe starts at 34) would leave scores 30 to 33 with no level, and Step 3 of a New Assessment would fail with a server error for any student scoring there. Restore Official Values and the official values themselves are unchanged.

The Settings area also manages the lookup tables everything else depends on: Courses, Year Levels, and Sections. Each can be set Inactive on its edit form (still listed, no longer offered on the New Assessment form) or **archived** with the **Archive** button (soft-deleted: removed from the list, with no Restore). Archiving is blocked while any active student uses the record ("Cannot archive a course used by active students. Set its status to Inactive instead.") — the same "in use" guard pattern used for Questionnaires. Archived students don't count, and still show the archived course, year level or section on their records.

---

## User Management

Psychometrician-only. Creates, edits, deactivates/reactivates, and resets passwords for staff accounts.

**Why deactivate instead of delete?** User accounts have no delete function at all — only `is_active` toggling. Every assessment, counseling session, and audit log entry permanently references *who* performed it; deleting a user account would either break those historical references or require silently reassigning history to someone else, both bad options. A user who leaves the school gets deactivated (immediately blocked from logging in) while every record they ever touched stays intact and correctly attributed.

There's also a built-in safety net preventing a user from deactivating their own account — closing off a way to accidentally lock yourself out with no other admin available to reverse it.

---

## Audit Logs

A permanent, read-only (Psychometrician-only) trail of significant actions across the system — records created, updated, archived, deleted, restored, and login lockouts — each entry capturing who did it, what module/action it was, and (where relevant) the actual before/after values that changed.

Entries labelled module "Feedback Loop", action "Feedback Loop Submission" are the Psychometrician's review decision (Confirm or Correct) recorded at Step 3 of the New Assessment wizard — the label is kept from the earlier standalone Feedback Loop page so old and new entries stay consistent.

**How it writes itself automatically:** rather than every single Controller action having to remember to manually write a log entry (an easy thing to forget, and inconsistent if some developers remember and others don't), a single shared "Observer" is attached to every model that needs auditing. It listens for the model's own create/update/delete database events and writes the log entry itself, automatically, every time — this guarantees consistent coverage across the whole system rather than depending on every feature remembering to log itself individually.

**Restore.** Restoring an archived student (Students → Archived) writes one entry with action **Restore**: the archive date in Old Values, the student's details and the optional `restore_reason` in New Values, and a **Reason** line on the entry's page.

**Archive vs. Delete.** Archiving a student, course, year level, section or questionnaire is logged with action **Archive**; everything else that is removed (a counseling session, a Draft version, a question, or a real hard delete of any record) is logged **Delete**. This split started on 2026-10-08. Entries written before then are left exactly as they were and say **Delete** for archives too; they were not relabelled, because an old "Delete" entry can't show whether the record was archived through the app or removed from the database some other way (in the development database many were). So filtering by action **Delete** still finds archives from before that date, and filtering by **Archive** finds only later ones.

**Encrypted fields never reach the log.** For every encrypted field (see [Data Encryption & Privacy](#data-encryption--privacy-ra-10173)), the log stores the fixed marker `[changed]` instead of the value — never the plaintext and never the ciphertext. A Create or Delete entry shows `"session_notes": "[changed]"`; an Update entry shows it on both sides only when the notes were actually edited, and leaves the field out when they weren't. So the log records *that* notes were written or changed, never *what* they said. Today only `counseling_sessions.session_notes` passes through the log (the score and answer tables aren't audited), but the rule covers every encrypted column automatically.

**Student device drafts are never audited.** Creating, claiming, answering, new codes, cancelling and expiry of a [student device](#student-device-assessment-scope-change) draft write no Audit Log entry, so an abandoned run leaves no trace. That includes the details a student types on the device, the Psychometrician's corrections and a hold over a duplicate name: the Audit Log records only the **final student record**, created at Confirm & Save, never the draft. The assessment it produces is audited as usual when it's saved, and records `administration_mode = student_device`.

Filterable by module, action, and a date range (see below for exactly how that filtering works).

---

## Data Encryption & Privacy (RA 10173)

Because NORMI handles student mental health data — sensitive personal information under the Philippine Data Privacy Act (RA 10173) — the most clinically sensitive columns are encrypted at the application level, in addition to the role-based access controls described throughout this document.

### What is encrypted at rest

The following columns are encrypted transparently with AES-256-CBC, authenticated with an HMAC so tampering is detectable, using the app's `APP_KEY` — the model API is unchanged; Eloquent decrypts on read and encrypts on write automatically. The answer and score columns use a small custom cast, `App\Casts\EncryptedInteger`, built on the same primitive as Laravel's built-in `encrypted` cast (`Crypt::encryptString()`), which decrypts back to an integer rather than a string; `session_notes` uses Laravel's built-in `encrypted` cast:

| Table | Column(s) | Why this one |
|---|---|---|
| `dass_responses` | `answer_value` | The raw answer to an individual DASS-21 question — the most granular clinical data point in the system. |
| `dass_results` | `depression_raw_score`, `anxiety_raw_score`, `stress_raw_score`, `depression_final_score`, `anxiety_final_score`, `stress_final_score` | The computed DASS-21 subscale scores. |
| `counseling_sessions` | `session_notes` | Free-text clinical notes from a counseling session. |
| `remote_assessment_drafts` | `responses`, `identity` | *(Scope change, 2026-10-08.)* A student-device questionnaire's answers while it is being answered, and — when the student fills in Step 1 on the device — the details they typed (name, gender, course, year level, section), both stored with Laravel's built-in `encrypted:array` cast. The row holds no student number and no link to a student record, and is deleted on submit, cancel, every wizard exit or expiry (see [Student Device Assessment](#student-device-assessment-scope-change)). |

The draft's one-time code, link token and device secret are not stored at all, only as HMAC-SHA256 digests keyed by `APP_KEY`.

### What is intentionally left unencrypted, and why

Student names, gender, and DASS-21 severity *labels* (e.g. "Severe," "Extremely Severe" — as distinct from the numeric scores above, which are encrypted) are **not** encrypted at the column level. This isn't an oversight — the app's search, filtering, and Dashboard features depend on the database being able to compare these values directly:

- Name search (Students, Assessment History, Flagged Cases, Counseling Sessions, Reports — see the Search & Filter section below) does a "contains anywhere" SQL match against `first_name`/`last_name`/`middle_name`. Laravel's `encrypted` cast produces different ciphertext every time the same value is encrypted (a fresh random IV each time, which is what makes it cryptographically strong), so an encrypted column can never be matched with SQL `LIKE` — only after the whole table is decrypted in PHP first, which would require rewriting every search feature in the system and giving up server-side pagination for these lists.
- The Psychometrician Dashboard's stat cards and severity filter run SQL-level `WHERE ... IN (...)` queries directly against the severity-level columns to count/filter assessments — the same encryption limitation applies.
- The Assessment Summary Report's gender filter does a direct SQL equality match against `gender`.

Encrypting these fields would require a substantial rewrite (either dropping partial-name search in favor of exact-match lookups, or moving all of the above filtering into PHP after fetching every row) — judged not worth the risk for this project at this time.

### Recommended complementary control: encryption at rest

For the fields above that stay in plain text at the column level (and as defense-in-depth for everything else), the recommended complementary control is **database-level encryption at rest** — e.g. MySQL/MariaDB Transparent Data Encryption (TDE), or simply hosting the database on an encrypted disk/volume. This protects the entire database file (every table, every column, including the ones above) from anyone who gains access to the raw database files or a backup, without requiring any application code changes and without breaking any search/filter/dashboard functionality, since decryption happens transparently at the storage layer rather than per-column. Applying this is an infrastructure/deployment decision (it depends on the hosting environment), not something the application code enforces — but it's the natural complement to the column-level encryption already in place, and worth documenting as part of the system's overall RA 10173 data-protection posture.

### A few implementation notes

- Because AES ciphertext runs several times longer than the plain value it replaces, the encrypted columns above were widened to `TEXT` (they held `INTEGER`/`TINYINT` values before).
- Encryption and decryption both depend on the app's `APP_KEY`. If that key is ever lost, every encrypted value becomes permanently unrecoverable — `APP_KEY` must be backed up securely and never committed to version control.
- **Wizard data in the `sessions` table.** Sessions are stored in the database (`SESSION_DRIVER=database`). While a New Assessment is in progress, its data — the student's name, course and section, the 21 answers, the scores and the AI's proposed levels — is kept in that session's row in the `sessions` table, not in the encrypted columns above, until it is saved, cleared (e.g. by starting over or logging out) or the session expires (`SESSION_LIFETIME`, 120 minutes). With `SESSION_ENCRYPT=false` that payload is only base64-encoded, so anyone who can read the `sessions` table can read it; with `SESSION_ENCRYPT=true` it is encrypted with `APP_KEY` (AES-256-CBC with an HMAC, like the columns above). **Production should use `SESSION_ENCRYPT=true`** (`.env.example` still ships `false`, so set it explicitly when deploying).
  - **What stays readable:** only the payload is encrypted. The row's `id`, `user_id`, `ip_address`, `user_agent` and `last_activity` stay plain. The one-session-per-account check at login, the per-request check that ends a second session (e.g. one that came back through "Remember me"), and the administrator's Force Logout (which deletes that user's rows) use only `user_id` and `last_activity`, so they work the same either way.
  - **Turning it on:** sessions created before the switch can't be decrypted. Each one is logged out on its next request, losing any New Assessment in progress, and its row is then rewritten encrypted and without a user. Until that request happens, or until the session expires (`SESSION_LIFETIME`), it still counts as that user's active session, so a login from a different browser is refused. Switch while nobody is signed in, or clear the `sessions` table at the same moment (or use Force Logout for anyone affected).
  - **Cost:** about 0.1 ms to decrypt and 0.1 ms to encrypt per request, and the stored payload grows about 2.5×, e.g. ~3 KB of wizard data becomes ~7 KB in the column.
  - **Student device:** the student device never gets a session at all, so it adds no rows here. While a questionnaire is out on a student device, the Psychometrician's session holds the plain one-time code and link token (for the live page) until the draft ends — one more reason for `SESSION_ENCRYPT=true` in production.
- **The Audit Log never stores encrypted fields** — it writes `[changed]` in their place (see [Audit Logs](#audit-logs)). Before 2026-10-08 it did not: Create and Delete entries for counseling sessions stored the notes' ciphertext (or the plaintext, for entries written before the notes were encrypted), and every Update entry stored the session's previous notes in **plaintext**, because the observer read them through `getOriginal()`, which decrypts. Those older entries have not been changed or redacted; redacting them is a separate, deliberate decision, to be taken after a database backup.
- A one-time backfill command (`php artisan security:encrypt-sensitive-data`) encrypts any rows that were written before this feature was added; it's idempotent (safe to re-run) and writes directly via the query builder rather than through Eloquent, specifically so it doesn't flood the Audit Logs with thousands of "Update" entries for what is a one-off maintenance operation.

---

## Search & Filter System — How It Works Everywhere

This section explains, in plain terms, the exact underlying approach used for *every* search and filter feature across the whole system — because the same handful of patterns are deliberately reused everywhere rather than each page inventing its own approach.

### How name search works

Nearly every "search a person by name" feature in the system (Students, Assessment History, Flagged Cases, Counseling Sessions) uses the **exact same four-part check**. Typing a search term matches if **any** of these are true for a record:
1. The **first name** contains the typed text, anywhere in it.
2. The **last name** contains the typed text, anywhere in it.
3. **First name + last name combined** (with a space between) contains the typed text.
4. **First name + middle name + last name combined** contains the typed text.

In plain terms: typing `"Juan"` matches anyone whose first name contains "Juan"; typing `"Dela Cruz"` matches because it checks the combined first+last name too; typing part of a full three-name combination works because of the fourth check. This is a "contains anywhere" match (not "starts with" and not "exact match") — so a partial, misremembered, or partially-typed name still finds the right person, which matters a lot for a fast-paced front-desk/intake tool where staff are typing quickly and may not remember exact spelling.

User search (in User Management) uses a simpler two-part version of the same idea — name **or** email, since a staff account is more often looked up by either.

### How multiple filters combine (AND logic)

Every page that has more than one filter (e.g., Flagged Cases: search + course + year level + section + date range, all at once) combines them with **AND logic** — a record must satisfy *every* filter that's currently set, not just one of them. Each filter is also independently optional: leaving a filter blank simply skips that check entirely rather than excluding everything.

**Why AND instead of OR?** Filters are meant to *narrow down* a list, not widen it. If a Guidance Counselor sets Course = "BSIT" and Year Level = "3rd Year," they expect to see 3rd-year BSIT students specifically — not every BSIT student *plus* every 3rd-year student from any course. AND logic is what matches that real intent.

### How each specific filter works

| Page | Filters available | How each one works |
|---|---|---|
| **Students** | Name search | The 4-part name match described above. |
| **Users** | Name/email search | Matches name OR email, contains-anywhere. |
| **Assessment History** | Name search | The 4-part name match. |
| **Flagged Cases** | Tab (All/Endorsement/Notification/Normal), name search, Course, Year Level, Section, date range | The tab filter checks which *type* of flag (if any) exists on the assessment; the rest are straightforward exact-match (course/year level/section) or "on or between these dates" (date range) checks, all AND-combined with the tab and search. |
| **Notifications** | Archived / not archived toggle | A true/false switch on whether `archived_at` is set — two completely separate lists, not a filter narrowing one list. |
| **Audit Logs** | Module, Action, date range | Module and Action are exact-match dropdowns (populated from whatever distinct values actually exist in the log, so the dropdown can never offer a module/action that has zero matching entries); date range is an "on or after / on or before" check. |
| **Counseling Sessions** | Name search | The 4-part name match. |
| **Reports** | Course, Year Level, Section, Gender, date range, flag type (report-specific) | Same exact-match/date-range approach as above; each report only reads the specific filters relevant to it. |

### Pagination

Every list in the system uses the same "page 1, page 2, ..." style pagination rather than infinite scroll — a deliberate choice for an admin/records tool, since staff often need to reference "page 3 of the flagged list" in conversation or return to roughly the same spot, which is harder to do with an infinite-scrolling list.

---

## Page Loading Without a Flash

NORMI is a normal multi-page site: every sidebar click loads a complete new page from the server. That is deliberate (it keeps every page simple and works without special JavaScript routing), but anything that looks different at the browser's first paint than it does a moment later shows up as a "blink" on every click. The layouts are built so the first paint already looks like the finished page:

- **Theme before first paint.** A tiny script at the top of the page's `<head>` (app and guest layouts) reads the saved theme and sets the `dark` class *and* the browser's `color-scheme` before any CSS or JavaScript loads, so a dark-mode user never sees a white page first. The Profile page's theme switch updates both too.
- **Nothing hidden flashes into view.** Parts of the page that start hidden and are shown by Alpine.js (the mobile sidebar and its dimmed backdrop, the dashboard chart tooltips, the password field's "hide" eye icon) carry `x-cloak`, and the stylesheet hides anything with `x-cloak` until Alpine has started. Two places don't use `x-cloak` and instead have their starting state written by the server, so they are still correct even if the page's JavaScript fails to load: the counseling session form's Follow-Up Date field (hidden unless Follow-up required is ticked) and the Classification Thresholds buttons (only Enable Override Mode visible; Save Changes and Cancel start hidden).
- **Fonts requested early.** The app's Figtree font files are self-hosted. Each layout tells the browser up front (a `preload` link) to fetch the weights it paints with — 400, 500 and 600 for the app and guest layouts, all four (400–700) for the login page — instead of the browser discovering them only after reading the stylesheet. The preload uses the exact same file address the stylesheet does, so each font is downloaded once. Text still appears immediately in a fallback font if a font is slow (`font-display: swap`).
- **The page width never jumps.** On a computer whose scrollbars take up space (e.g. Windows), a page that scrolls is narrower than one that doesn't, and a confirmation dialog — which stops the page behind it from scrolling — makes the scrollbar disappear. Every page in the main app always keeps the scrollbar's space reserved, so opening a dialog, or moving between a long page and a short one, never changes the width of the page. Where no scrollbar is showing, that reserved strip is coloured to match the page, and darkened like the rest of the page while a dialog is open. The login, password-reset and printed/PDF report pages don't reserve it. On the Notifications page, the buttons on each notification also move to a new line on a phone instead of squeezing.
- **The student device page is separate.** It has its own minimal layout without the theme script (its security policy blocks inline scripts), so it is always light, and it doesn't reserve the scrollbar space. It preloads the same three font weights.
- **The sidebar keeps its scroll position.** On a short screen where the sidebar scrolls, its position is remembered for the current browser tab and restored before the new page is drawn, instead of jumping back to the top on every click. If the browser blocks this storage, the sidebar simply starts at the top as before.

**Local development note:** the `php artisan serve` development server sends the built CSS, JavaScript and font files with no caching headers at all (no `Cache-Control`, `ETag` or `Last-Modified`), so the browser has no way to know a saved copy is still good and generally fetches them again on every page. That makes page changes slower locally than they need to be. A production web server (Apache/nginx) normally adds these headers; since the files' names change whenever their content changes, they can safely be cached for a long time there.

---

## Closing Summary

NORMI is built around a few consistent principles that show up repeatedly across every module documented above:

1. **The AI assists, it never decides alone.** Every classification is a reviewable draft, cross-checked against a deterministic engine, and only the Psychometrician's final reviewed decision ever triggers a real-world consequence (a flag, a notification).
2. **Almost nothing destructive actually destroys data.** Students, courses, year levels, sections and questionnaires are archived (the button says **Archive**) — archived students can be restored from the Students page's Archived tab — notifications are archived and can be unarchived, and user accounts are deactivated. Counseling sessions and Draft versions say **Delete** and can't be restored in the app, but their rows are kept. The one real deletion is a question in a Draft version, which never has answers. The historical record matters more than a tidy list.
3. **The same patterns are reused everywhere** — the same name-search logic, the same AND-combined filters, the same archive-with-a-guard-clause pattern, the same audit-logging mechanism — rather than each feature reinventing its own approach. This makes the system easier to reason about as a whole, and easier to extend consistently as new features are added.
4. **Every consequential action leaves a record.** From login lockouts to threshold overrides to who reviewed which AI classification, the system is built so that "who did what, and why" is always answerable after the fact.
5. **Staff-only, with one narrow, deliberate exception.** Since the 2026-10-08 scope change, a student can answer the questionnaire, and if the Psychometrician chooses, type their own details, on their own device. That page needs no login but only a one-time code, can reach nothing else, and shows nothing that already exists in the system. Every decision after that — checking the details, the duplicate check, scoring, the AI, the review, saving — stays with the Psychometrician.
