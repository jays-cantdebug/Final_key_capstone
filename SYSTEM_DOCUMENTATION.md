# NORMI — System Documentation

**NORMI** (Web-Based Student Depression, Anxiety and Stress Assessment) is a school guidance and psychometric records portal built for Northern Mindanao Colleges, Inc. It lets school staff run the DASS-21 mental health screening on students, get an AI-assisted severity classification, route serious cases to the guidance office, and keep proper records for reporting and audits.

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
9. [The AI Classification System (Full Detail)](#the-ai-classification-system-full-detail)
10. [DASS-21 Scoring — How Raw Answers Become Final Scores](#dass-21-scoring--how-raw-answers-become-final-scores)
11. [Differentiated Flagging](#differentiated-flagging)
12. [The "Take Again" Retake Feature](#the-take-again-retake-feature)
13. [Assessment History](#assessment-history)
14. [Flagged Cases (Guidance Counselor)](#flagged-cases-guidance-counselor)
15. [Notifications](#notifications)
16. [Counseling Sessions](#counseling-sessions)
17. [Reports](#reports)
18. [Classification Thresholds & Settings](#classification-thresholds--settings)
19. [User Management](#user-management)
20. [Audit Logs](#audit-logs)
21. [Search & Filter System — How It Works Everywhere](#search--filter-system--how-it-works-everywhere)
22. [Closing Summary](#closing-summary)

---

## Who Uses This System

There are exactly two staff roles. There is no public registration — accounts are created by an administrator, not signed up for.

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
| `routes/web.php` | The master map of every URL in the system and which Controller handles it. If a page exists, its URL is registered here. |
| `tests/` | The automated test suite — code that exercises the real system (creates a student, submits an assessment, checks the right things happened) and fails loudly if a change breaks existing behavior. |
| `config/` | Small settings files for the whole application — e.g., `config/ai.php` decides which AI Classification strategy is active. |

**Why this structure?** It's Laravel's standard convention, and following convention matters more than it sounds: any developer who already knows Laravel can find their way around this codebase immediately, without needing a special onboarding explanation. The Controller → Service → Model split specifically exists so that business logic (Services) never gets tangled up with web-request handling (Controllers) — which makes it possible to write automated tests against the *logic* directly, without needing to fake an entire web request every time.

---

## Login & Account Security

There is **no public sign-up**. Every account is created by a Psychometrician through User Management — this is a closed staff system, not a public website.

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
- **Guidance Counselor Dashboard:** flagged case counts, a "Flagged Cases Volume" chart, a breakdown by flag type (Counseling Endorsement vs. Awareness Notification), and the most recent assessments needing attention.

**Why separate charts per role?** A Psychometrician cares about overall testing volume and severity trends across the whole student population; a Guidance Counselor specifically cares about *flagged* cases — the subset that actually needs a human follow-up. Showing the Guidance Counselor a chart of *all* assessments (most of which are Normal/Mild and require no action) would bury the signal they actually need.

Both volume charts use **relative bar scaling** — each bar's height is calculated as a percentage of whichever month currently has the highest count in the visible 6-month window, not a fixed scale. This means early on, with low data volume, even a small number can look like a "full" bar — that's expected and self-corrects as more months of real data accumulate. Hovering over a bar shows a small styled tooltip with the exact count for that month, so the real number is never ambiguous even while the chart is still visually adjusting to low volume.

---

## Student Information Management

A record-keeping module for browsing, editing, and archiving student records.

**Key design decisions:**
- **`student_number` is always generated by the system** (year-prefixed, sequential) — it is never typed in by staff, on any form, anywhere. This guarantees no duplicate or malformed student numbers can ever be entered by mistake.
- **"Delete" is really "archive."** Deleting a student doesn't erase their row from the database — it soft-deletes it (marks it archived, keeps every historical assessment attached to it intact, and can be reviewed later). This matters because a student's mental health assessment history has to be preserved for continuity of care, even if they've left the school.
- There is **no manual "Add Student" form** here — new students only ever get registered through the New Assessment wizard (see below), because every real-world encounter with a student starts with running an assessment on them, not with data entry for its own sake.

---

## Questionnaire Management

Manages the DASS-21 questionnaire itself: its versions, and the individual questions within each version.

**Why versions exist at all:** A questionnaire's wording or question set might need to change over time (e.g., translated, or a question reworded for clarity), but every *already-submitted* assessment must keep showing exactly what it was scored against — you can't silently rewrite history. So a `Questionnaire` is a container, and each `QuestionnaireVersion` inside it is a frozen snapshot. Only one version can be `Active` system-wide at any time (that's the one the New Assessment wizard actually uses); older versions become `Archived` but stay attached to their historical assessments forever.

**Editing rules:** questions can only be added, edited, or removed while their parent version is still in `Draft` status. Once a version goes `Active` (meaning real students may have already been assessed against it), it's locked — this prevents someone from quietly changing a question's wording or scoring weight *after* real assessments have already been scored against the old wording.

**Deleting a questionnaire** is blocked if any of its versions has ever been used by a real assessment — the system checks this automatically and shows a friendly error instead of silently corrupting historical data.

---

## The New Assessment Wizard

This is the core workflow: a 3-step guided process — **Student Information → Questionnaire → Review & Save**.

### Why nothing is saved until the very end

Nothing is written to the database until the Psychometrician reaches the final step and clicks **Confirm & Save** or **Correct & Save**. Everything in between — the student's name/course/section, their answers to all 21 questions, even the AI's computed classification — lives only in the browser session (server-side temporary storage tied to that login session), not the database.

**Why build it this way?** An earlier version of this system *did* save the student record as soon as Step 1 was submitted — but that meant every time a Psychometrician started a wizard and then closed the tab, got interrupted, or made a mistake and restarted, a half-finished "student" record was permanently left behind in the database with no actual assessment attached to it. Dozens of these orphan records accumulated. Deferring all saving to the final step means an abandoned wizard — at any point, for any reason — leaves **zero trace** in the database.

### Step 1: Student Information

Captures First Name, Middle Name, Last Name (as three separate fields, not one "Full Name" field, to avoid ambiguous name-splitting), Gender, Course, Year Level, Section, and a privacy consent checkbox.

**Middle Name format rule:** the Middle Name field only accepts a single letter followed by a period (e.g., `P.`) — a middle *initial*, not a full middle name. Lowercase is accepted but automatically converted to uppercase before it's saved, so `p.` becomes `P.` without the user needing to retype it. If the format doesn't match, a floating tooltip explains the expected format with an example.

### Step 2: Questionnaire

Shows the 21 DASS-21 questions from the currently Active questionnaire version. Every question must be answered with a value from 0–3, matching the official DASS-21 response scale:

| Value | Meaning |
|---|---|
| 0 | Did not apply to me at all |
| 1 | Applied to me to some degree, or some of the time |
| 2 | Applied to me to a considerable degree, or a good part of the time |
| 3 | Applied to me very much, or most of the time |

### Step 3: Review AI Classification (mandatory)

This is the step that makes the AI safe to use in a clinical context. Before anything is saved, the system:
1. Scores the 21 answers into three subscale scores (see [DASS-21 Scoring](#the-ai-classification-system-full-detail) below).
2. Sends those scores to the active AI Classification provider.
3. Shows the Psychometrician exactly what the AI concluded for Depression, Anxiety, and Stress.

The Psychometrician must then either:
- **Confirm** — accept the AI's classification as correct, or
- **Correct** — override one or more subscales with a different severity level, optionally with notes explaining why.

**Nothing is saved until one of those two choices is made.** This decision is recorded permanently (see the Feedback Loop, below) — every single assessment has exactly one review decision, there is no way to skip this step and save without a decision on record.

**Why this exists:** DASS-21 classification is legally and clinically consequential — a wrong severity tier could mean a student who needs help doesn't get flagged, or a false alarm needlessly escalates a normal result. The system treats the AI as a *draft suggestion*, never a final verdict. A trained Psychometrician always has the final say, and the system is built so that saying so is not optional — it's baked into the save action itself.

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

This is the literal text sent as the `system` parameter of the API call — it's what tells Claude what its job is and, critically, what it is *not* allowed to do:

```
You are a strict classification lookup engine for a DASS-21 (Depression, Anxiety, Stress Scale) mental health assessment system.

Your ONLY task is to classify three subscale scores (depression, anxiety, stress) into their official severity tier by looking up which range in the "official_thresholds" object of the user's message contains each score. A score belongs to a tier when it falls within that tier's inclusive [min, max] range; a null max means the range is unbounded upward.

Rules you must follow exactly:
- Use ONLY the threshold ranges provided in the user message. Do not use any outside knowledge of DASS-21 cutoffs, even if it seems to conflict with the provided ranges.
- Do not guess, estimate, round, or reason clinically about the scores. This is a literal lookup, not a clinical judgment.
- The keys in "official_thresholds" use snake_case tier names (e.g. "extremely_severe"). Report your classification using the Title Case form of that same tier name (e.g. "Extremely Severe").
- You must report your classification by calling the classify_dass_subscales tool. Do not respond with any other text.
```

**Why word it this strictly?** Large language models are trained on huge amounts of general text, which can include generic (and possibly outdated, or differently-sourced) DASS-21 cutoff numbers "baked in" from training. The instruction to use *only* the ranges provided — even if they "seem to conflict" with what it might otherwise assume — exists specifically to force it to defer to *this school's actual configured thresholds* (which an administrator can adjust in Settings) rather than some generic memorized version. The "this is a literal lookup, not a clinical judgment" line exists to stop the model from trying to be clever — e.g., second-guessing a boundary score — when the whole point is a mechanical, reproducible lookup.

### The forced tool-use JSON Schema (guaranteeing structured output)

Rather than just *asking* Claude to reply in JSON (which can still occasionally produce malformed or explanatory text around the JSON), the request uses Claude's **tool use** feature: it defines a "tool" (essentially a function signature) and forces Claude to call it, with `tool_choice` explicitly set to require exactly that tool. Claude's API then guarantees the reply arrives as structured data matching this schema, not as prose:

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
- **Guaranteed valid values.** The `enum` list in the schema means Claude is structurally prevented from returning anything other than one of the five real severity tier names — no typos, no synonyms, no made-up tier.
- **Industry best practice for AI-to-system integration.** Whenever an AI's output needs to be consumed by another program (rather than read by a human), forcing structured output is the standard, recommended approach specifically because it removes the need for fragile text-parsing logic, which is one of the most common sources of bugs in AI-integrated systems.
- **Machine-checkable.** Because the shape is guaranteed, the response can be validated with simple code (checking three fields exist and are one of five valid strings) rather than complex text-pattern matching that could silently misinterpret a reply.

### The accuracy safeguard: cross-checking against the rule-based engine

Because the rule-based lookup is deterministic and always correct by definition (it's a direct database lookup against the exact same numbers Claude was given), **every single Claude response is automatically compared against what the rule-based engine computed for the exact same input**, before it is trusted:

- **If they agree** on all three subscales → the Claude result is used, and `dass_results.ai_provider` is recorded as `"claude"`.
- **If they disagree on even one subscale** → the discrepancy (including both results and the input scores) is written to the application log for review, and the system silently falls back to the rule-based result instead. `ai_provider` is recorded as `"rule_based"`.
- **If the Claude API call fails outright** (network error, timeout, malformed/missing tool call, API error) → the same fallback happens, also logged.

**Why this matters:** it means an incorrect AI classification can *never* actually reach the Psychometrician's review screen or be saved to the database — the worst thing a Claude malfunction can do is silently fall back to the (always-correct) rule-based answer. The `ai_provider` column on every saved result is a truthful record of which engine's answer was *actually used*, which matters both for transparency and because a capstone/thesis needs to be able to demonstrate this safeguard is real, not just claimed.

### The mandatory pre-save review — why the AI's raw output never triggers anything by itself

This is worth restating clearly because it's a core safety property of the whole system: **whichever classification comes out of the AI Classification step above — Claude or rule-based — is never what decides whether a case gets flagged or a Guidance Counselor gets notified.**

The actual sequence is:
1. AI Classification produces a raw severity per subscale (Depression/Anxiety/Stress).
2. The Psychometrician reviews it at Step 3 and either **Confirms** it as-is, or **Corrects** one or more subscales.
3. The system computes an **effective result**: for any subscale that was confirmed, the effective level is the AI's raw level; for any subscale that was corrected, the effective level is the Psychometrician's corrected level instead.
4. **Differentiated Flagging (below) is evaluated only against this effective result — never against the AI's raw output directly.**

So if the AI raw-classifies a subscale as *Extremely Severe*, but the Psychometrician corrects it down to *Normal* based on their own clinical judgment, **no flag is created and no Guidance Counselor is notified** — because the thing that actually happened, as far as the system is concerned, is what the Psychometrician decided, not what the AI initially suggested. The AI's original raw output is still permanently saved (for the audit trail — it's never overwritten or hidden), but it is not what drives any downstream action.

**Why build it this way?** If the AI's raw output directly triggered flags/notifications, then a Psychometrician correcting an AI mistake would be pointless — the (possibly wrong) alarm would already be out the door. Routing every downstream consequence through the human-reviewed, *effective* result is what makes the mandatory review step meaningful rather than a rubber stamp.

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

**How it works:** clicking "Take Again" on an existing student's row stages that student's existing information into the wizard session and skips straight to Step 2 (Questionnaire) — Step 1 is bypassed entirely since the student's info is already known. Because Step 1 is skipped, the student's privacy consent (normally captured on Step 1) is instead captured on Step 2 for a retake — the retake flow adds a required consent checkbox there specifically to cover this gap.

**Why this exists:** without it, every retake would either (a) require manually re-typing a returning student's full information every time, inviting typos and duplicate near-identical student records, or (b) require a "search for existing student" step baked into every single New Assessment run, slowing down the much more common case of a brand-new student encounter. Keeping the regular wizard's "always register a fresh student" behavior untouched, and adding retake as a separate, explicit entry point, keeps both paths simple.

---

## Assessment History

A shared, searchable, read-only listing of every completed assessment — available to both roles, since both legitimately need to look up past results (the Psychometrician for record-keeping, the Guidance Counselor for case history).

---

## Flagged Cases (Guidance Counselor)

The Guidance Counselor's main working list — every assessment that has at least one flagged case attached to it, organized into tabs:

- **All** — every flagged assessment.
- **Endorsement** — assessments with a `counseling_endorsement` flag (Severe/Extremely Severe Stress).
- **Notification** — assessments with an `awareness_notification` flag but *no* endorsement flag.
- **Normal** — assessments with *no* flags at all (useful as a "confirmed clear" reference view).

Supports filtering by course, year level, section, and a date range, on top of name search — all combinable at once (see the [Search & Filter](#search--filter-system--how-it-works-everywhere) section for exactly how that combination works).

---

## Notifications

The Guidance Counselor's inbox for flagged-case alerts. Each notification links directly to the assessment that triggered it.

**Archive / Unarchive:** a notification can be archived to hide it from the default inbox view without deleting it — the row stays in the database permanently (for accountability — there's no way to make a real notification simply vanish), and a separate "View Archived" toggle shows them again on demand. Archiving an already-archived notification is a safe no-op (it doesn't reset the archive timestamp). Viewing a notification automatically marks it as read and redirects straight to the underlying assessment.

**Why archive instead of delete?** A Guidance Counselor needs to be able to clean up their working inbox without losing the historical record of "a notification about this case existed and was seen" — which matters for accountability if a case is ever reviewed later.

---

## Counseling Sessions

Where a Guidance Counselor records an actual counseling session held with a student — session date/time, notes, follow-up requirements, and confidentiality level — optionally linked to the specific assessment that prompted it.

Creating a session starts with searching for the student by name (the same shared search pattern used everywhere — see below); if the search matches exactly one student, that student is pre-selected automatically to save a click.

---

## Reports

A set of printable/downloadable (PDF) reports, all built from the same underlying filter system used throughout the app:

| Report | Who can see it | What it shows |
|---|---|---|
| **Assessment Summary Report** | Both roles | Institution-wide totals and per-condition breakdowns, filterable by course/year level/gender/date range. |
| **Flagged Students Report** | Guidance Counselor only | A consolidated view of flagged cases, filterable by flag type plus the usual course/section/date filters. |
| **Assessment Report** | Both roles | A single assessment's full detail, reached from Assessment History. |
| **Student Assessment History Report** | Both roles | One specific student's full history, reached from their profile page. |
| **Counseling Report** | Guidance Counselor only | A record of counseling sessions held, reached from the Counseling Sessions module. |

**Why PDF, and why reuse the same Blade views?** Every report has both a "Print" and a "PDF" version that render from the *exact same* Blade template — the PDF version just runs that same HTML through the `laravel-dompdf` library instead of sending it straight to the browser. This guarantees the on-screen preview and the downloaded PDF can never drift out of sync with each other, since they're not two separately-maintained versions of the same report.

---

## Classification Thresholds & Settings

Lets the Psychometrician view and, if necessary, override the official DASS-21 severity cutoff numbers that both AI Classification engines read from — and restore them back to the official published values at any time.

**Why allow overriding official, published clinical cutoffs at all?** It doesn't happen casually — every change is captured as a single, consolidated Audit Log entry showing exactly which rows changed and their old vs. new values, and the system displays a persistent warning banner anywhere thresholds are in effect that don't match the official values, so it's never silently forgotten that non-standard cutoffs are active. The override capability exists for edge cases (e.g., a future, revised, officially-published DASS-21 cutoff table) without needing a code deployment to update it — but the constant visible warning and full audit trail mean it can never be changed by accident or without a permanent record of who changed what.

The Settings area also manages the lookup tables everything else depends on: Courses, Year Levels, and Sections (each independently archivable, blocked from archiving if a student record still references it — the same "in use, can't delete" guard pattern used for Questionnaires).

---

## User Management

Psychometrician-only. Creates, edits, deactivates/reactivates, and resets passwords for staff accounts.

**Why deactivate instead of delete?** User accounts have no delete function at all — only `is_active` toggling. Every assessment, counseling session, and audit log entry permanently references *who* performed it; deleting a user account would either break those historical references or require silently reassigning history to someone else, both bad options. A user who leaves the school gets deactivated (immediately blocked from logging in) while every record they ever touched stays intact and correctly attributed.

There's also a built-in safety net preventing a user from deactivating their own account — closing off a way to accidentally lock yourself out with no other admin available to reverse it.

---

## Audit Logs

A permanent, read-only (Psychometrician-only) trail of significant actions across the system — records created, updated, deleted, restored, and login lockouts — each entry capturing who did it, what module/action it was, and (where relevant) the actual before/after values that changed.

**How it writes itself automatically:** rather than every single Controller action having to remember to manually write a log entry (an easy thing to forget, and inconsistent if some developers remember and others don't), a single shared "Observer" is attached to every model that needs auditing. It listens for the model's own create/update/delete database events and writes the log entry itself, automatically, every time — this guarantees consistent coverage across the whole system rather than depending on every feature remembering to log itself individually.

Filterable by module, action, and a date range (see below for exactly how that filtering works).

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

## Closing Summary

NORMI is built around a few consistent principles that show up repeatedly across every module documented above:

1. **The AI assists, it never decides alone.** Every classification is a reviewable draft, cross-checked against a deterministic engine, and only the Psychometrician's final reviewed decision ever triggers a real-world consequence (a flag, a notification).
2. **Nothing destructive actually destroys data.** Students, courses, sections, questionnaires, notifications — "deleting" almost always means archiving, because the historical record matters more than a tidy list.
3. **The same patterns are reused everywhere** — the same name-search logic, the same AND-combined filters, the same archive-with-a-guard-clause pattern, the same audit-logging mechanism — rather than each feature reinventing its own approach. This makes the system easier to reason about as a whole, and easier to extend consistently as new features are added.
4. **Every consequential action leaves a record.** From login lockouts to threshold overrides to who reviewed which AI classification, the system is built so that "who did what, and why" is always answerable after the fact.
