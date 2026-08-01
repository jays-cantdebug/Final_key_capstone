# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

"NORMI" — a Web-Based Student Depression, Anxiety and Stress Assessment (DASS-21) for Northern Mindanao Colleges, Inc.: a Laravel 11 school guidance/psychometric records portal (student capstone project). Public registration is disabled; only `psychometrician` and `guidance_counselor` roles are provisioned by seeders. Backend is PHP 8.2/Blade with Laravel Breeze for auth scaffolding (registration removed); frontend uses Tailwind + Alpine.js via Vite (no SPA framework).

The codebase is driven from a large user-owned "Master Prompt" spec, revealed and approved module-by-module. Nearly the full module list is implemented end-to-end: auth/roles, Course/YearLevel/Section management, Student Information Management, Questionnaire Management (+ versions + questions), Classification Thresholds, New Assessment (3-step wizard), AI Classification, Differentiated Flagging, Notifications, Feedback Loop, Assessment History/Results, Counseling Sessions, Reports (PDF), User Management, Settings, Audit Logs.

**Known open gaps**: none tracked as of 2026-07-08. A full design pass (component library, brand palette, differentiated-flag display rules), login page mockup fidelity, a mobile/tablet responsive pass (code-audited and user-visually-confirmed), and an automated test suite covering every domain module are all complete.

**Local DB is MySQL, not SQLite**, despite `database/database.sqlite` existing and `.env.example` defaulting to sqlite: the working `.env` has `DB_CONNECTION=mysql`, `DB_DATABASE=student_mental_health_system`. Check `.env` before assuming which connection is live.

**Seeding requires `ADMIN_DEFAULT_PASSWORD`** to be set in `.env` — `UserSeeder` throws if it's unset; there is no hardcoded fallback password. Change the seeded Psychometrician account's password immediately after first login in any real deployment.

## Commands

Run PHP commands via `php artisan`, `vendor/bin/...`. Bring up the frontend build with `npm run dev` (Vite) alongside `php artisan serve` for local development.

- Install deps: `composer install` and `npm install`
- Serve app: `php artisan serve`
- Frontend dev server (HMR): `npm run dev`
- Frontend production build: `npm run build`
- Run all tests: `php artisan test` (or `vendor/bin/phpunit`)
- Run a single test file: `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- Run a single test method: `php artisan test --filter=test_method_name`
- Format code (Pint): `vendor/bin/pint`
- Run migrations: `php artisan migrate`
- Fresh DB + seed: `php artisan migrate:fresh --seed` (requires `ADMIN_DEFAULT_PASSWORD` in `.env` first)
- Tinker (REPL): `php artisan tinker`

Test environment uses the `array` driver for cache/session, `sync` queue, and sqlite in-memory `DB_CONNECTION`/`DB_DATABASE` (see `phpunit.xml`) — `RefreshDatabase` tests run fully isolated from the real dev MySQL database (`student_mental_health_system`), never touching or wiping it.

114 tests (`php artisan test`), covering Breeze auth flows plus every domain module: DASS scoring (pure arithmetic), AI classification (`RuleBasedDASSProvider` severity boundaries, provider factory resolution), differentiated flagging (all 0-3 flag-row combinations, notifications going to Guidance Counselors only — never the Psychometrician, tested as an explicit regression), the Feedback Loop (including the Psychometrician-only 403 regression), the New Assessment wizard end-to-end (name-splitting edge cases, full submit pipeline), Student/Questionnaire/Classification Threshold/Counseling Session/Notification/Report/Audit Log CRUD and role gating, and cross-cutting authorization (inactive-user login block, rate limiting, the User deactivation safety net). Shared fixtures live in `tests/Concerns/InteractsWithDomainData.php` (acting-as helpers, official threshold seeding, a configurable DASS-21 questionnaire version whose per-subscale raw score can be dialed to hit an exact severity band).

## Architecture

### Role-based access model

Authorization is centered on a `role_id` on `User`, not a package like Spatie permissions:

- `Role` (`app/Models/Role.php`) is a simple lookup table (`name`, `display_name`) seeded by `RoleSeeder` with two roles: `psychometrician` and `guidance_counselor`.
- `User::hasRole(string|array $roles)` (`app/Models/User.php`) checks the related role's `name`. `User` has no soft deletes — accounts are deactivated via `is_active`, never deleted/restored.
- Route-level protection uses the `role` middleware alias → `EnsureUserHasRole` (`app/Http/Middleware/EnsureUserHasRole.php`), registered in `bootstrap/app.php`. Usage: `->middleware('role:psychometrician')`.
- Model-level authorization uses Laravel Policies (e.g. `app/Policies/StudentPolicy.php`, `UserPolicy.php`), invoked in controllers via `Gate::authorize(...)`. Simple admin-only pages (Course/YearLevel/Section/Questionnaire/Settings) rely on route middleware alone with no dedicated Policy class.
- `DashboardRouteService` (`app/Services/Auth/DashboardRouteService.php`) maps a user's role to a named dashboard route (`psychometrician.dashboard`, `guidance-counselor.dashboard`, or the generic `dashboard`); `DashboardController::index` redirects there after login.
- Root route `/` (see `routes/web.php`) redirects guests to `login` and authenticated users to `dashboard` — it is not a static view.

When adding a new role-gated feature, follow this same three-layer pattern: route middleware for page access, a Policy + `Gate::authorize` for the underlying model actions (only where per-record nuance exists beyond role), and (if it needs its own landing page) an entry in `DashboardRouteService`.

### Service layer

Controllers delegate persistence/query logic to `App\Services\*` classes rather than querying Eloquent directly. Services are constructor-injected (e.g. `DatabaseManager` for wrapping writes in `->transaction()`). Follow this pattern for new resourceful features: thin controller (auth check + call service + return view/redirect), service holds the actual query/mutation logic.

### AI Prediction Module (`app/AI/`)

Follows the Strategy Pattern, resolved via `config('ai.provider')` (env `AI_PROVIDER`, default `rule_based`):

- `AIProviderInterface::classify(AssessmentPayload): AIClassificationResult` — the only contract callers depend on.
- `RuleBasedDASSProvider` — the default, **functional** provider. Classifies each DASS-21 subscale by querying `classification_thresholds` (no hardcoded cutoffs).
- `ClaudeAIProvider` — also functional. Sends the three subscale scores plus the live `classification_thresholds` (grouped into a snake_case `official_thresholds` JSON object, top tier's max reported as `null` to match DASS-21's open-ended top tier) to the Claude Messages API, forcing a tool-use call (`classify_dass_subscales`, JSON-Schema-enum-constrained) so the reply is structurally guaranteed JSON rather than merely prompted for. Because a threshold lookup is deterministic, the response is always cross-checked against `RuleBasedDASSProvider`'s own result for the same input before being trusted: agreement → the Claude result is used (`ai_provider` recorded as `claude`); any disagreement, malformed response, or request failure → the discrepancy is logged via `Log::warning()` and the rule-based result is returned instead (`ai_provider` recorded as `rule_based`), so an incorrect severity tier is never persisted and `dass_results.ai_provider` always reflects what was actually saved. `classify()` never throws. Configured via `AI_PROVIDER=claude`, `CLAUDE_API_KEY`, `CLAUDE_MODEL` in `.env` — the test suite pins `AI_PROVIDER=rule_based` in `phpunit.xml` regardless of `.env`, so tests never call the live API. Swapping providers must never require touching controllers/services/schema.
- `AIProviderFactory` resolves the active provider; `AIService` is the only class controllers/services call — never instantiate a provider directly.
- `AssessmentPayload` (immutable DTO) carries only `assessmentId` + the three final subscale scores — classification input, not output.

### DASS scoring, differentiated flagging, and the Feedback Loop

- `DassScoringService` is a **pure arithmetic** service: sums raw responses per subscale and doubles them for the final score. It does not classify severity or decide flags — that moved to the AI layer and `FlaggedCaseService` respectively (an earlier draft conflated all three in one service; that's been split apart).
- `AssessmentService::submit()` runs the full pipeline in one DB transaction: register the student → save assessment/responses → compute scores → `AIService::classify()` → save `DassResult` (includes `ai_provider`) → `FlaggedCaseService::evaluateAndFlag()`. See "New Assessment workflow" below for why student registration happens here rather than at Step 1 of the wizard.
- `FlaggedCaseService::evaluateAndFlag()` evaluates Stress, Depression, and Anxiety **independently**: Stress Severe/Extremely Severe → `flagged_cases` row with `flag_type = counseling_endorsement`; Depression and/or Anxiety Severe/Extremely Severe → separate `flag_type = awareness_notification` row(s), one per triggering subscale. A single assessment can produce 0–3 rows. Notifications go out per newly-created flagged case, to Guidance Counselors only — the Psychometrician is never a notification recipient (she sees the result immediately on submission).
- `DassResult::highestSeverityLevel()` computes an on-the-fly "highest severity" across the three subscales for display purposes (Dashboard/Reports/Counseling Sessions) — this is **not** a stored column; there is no `overall_status`/`overall_flag` in the schema.
- `PredictionFeedback` (Feedback Loop): a Psychometrician confirms or corrects the AI's classification per assessment via `PredictionFeedbackController`/`PredictionFeedbackService`, one row per assessment (updated in place on re-submission). Corrections never retroactively alter the original `dass_results` row or any already-issued flags/notifications.

### Student domain model

Core entities: `Student` belongs to `Course`, `YearLevel`, and `Section` (all simple lookup tables). `Student` uses `SoftDeletes` — "delete" is really an archive (see `StudentController::destroy` message: "archived successfully"); there is no separate `status` column — soft-delete (`deleted_at`) is the sole active/archived signal. The only sex/gender field is `gender` (Male/Female/Prefer not to say) — there is no legacy `sex` column.

`student_number` is **always system-generated** by `StudentNumberGeneratorService` (year-prefixed sequential code) — it is never typed by the Psychometrician, on either the Student Information Management "Register Student" form or the New Assessment intake form.

Validation for the Student Information Management create/update lives in `StudentFormRequest` (`app/Http/Requests/StudentFormRequest.php`), which authorizes at the request level (`authorize()` returns `true`) — actual authorization is done separately in the controller via `StudentPolicy`.

### New Assessment workflow (3-step wizard)

`AssessmentWizardController` drives Student → Questionnaire → Result, with in-progress state held entirely in session (`assessment_wizard.student_data`, `assessment_wizard.responses`) until final submit — **nothing is written to the database until Step 3's Submit & Calculate Score action**. Step 1 (`AssessmentStudentRequest`) validates the intake form and stages it in session only; `AssessmentService::submit()` creates the `students` row and the `assessments` row together, in the same transaction, only when the wizard is completed. This means an abandoned wizard (closed after Step 1 or Step 2, a validation error, restarting the flow) leaves no orphan `students` row — earlier drafts created the student row immediately on Step 1, which left dozens of assessment-less student records behind from incomplete runs; that's been fixed by deferring creation to final submit. There is still no search-for-existing-student/reuse step — every *completed* assessment is treated as a first encounter, and always registers a brand-new student. The UI takes First Name/Middle Name/Last Name as three separate inputs directly, all three required at the form-validation level in `AssessmentStudentRequest` (`students.middle_name` itself stays nullable at the schema level — this is a stricter data-entry rule, not a DB constraint) — there is no name-splitting heuristic. Because the student isn't persisted until Step 3, `student_number` (system-generated at creation) can't be previewed on the Step 2/3 review screens — those pages only display `full_name`, built from an unsaved `Student` model over the session data (reusing `Student::fullName()`) rather than a real DB fetch. Searching/reconciling a student who may have multiple historical records is a manual, Psychometrician-driven action through Student Information Management instead — not something the intake flow does automatically.

### Routes

`routes/web.php` is the main app route file; `routes/auth.php` (Breeze-generated, registration routes removed) holds auth routes and is required from it. Both roles now have real resource routes, not just a dashboard: Psychometrician owns `students.*`, `questionnaires.*` (+ versions/questions), `users.*`, Settings, Audit Logs, and the New Assessment wizard + Feedback Loop submission; Guidance Counselor owns `flagged-cases.index`, `notifications.*`, and `counseling-sessions.*`. Assessment History/Show and Reports are shared across both roles (some report routes are role-specific, e.g. Flagged Students/Counseling reports are guidance-counselor-only). The Reports hub (`reports.index`) itself only surfaces two cards — Assessment Summary Report (both roles) and a consolidated Flagged Students Report with a `flag_type` filter (guidance-counselor-only); Assessment Report, Student Assessment History, and Counseling Report remain fully functional routes reached from Assessment History, a student's profile, and the Counseling Sessions module respectively, just not duplicated as their own Reports-hub cards. Daily/Monthly Assessment Report and Questionnaire Usage Report were removed entirely (routes, controllers, views).

### Login gating

`LoginRequest::authenticate()` (`app/Http/Requests/Auth/LoginRequest.php`) passes `is_active => true` directly into the `Auth::attempt()` credentials array, so deactivating a user (`is_active = false`) silently blocks login as an "invalid credentials" error rather than a distinct "account disabled" message. Login throttling (5 attempts, RateLimiter, audit-logged lockouts) is implemented per spec.

## Conventions in this codebase

- New PHP files in `app/` generally start with `declare(strict_types=1);` (controllers, services, middleware, migrations added after the initial schema) — match this in new files.
- Constructor property promotion + `private readonly` is used for injected dependencies.
- Views are plain Blade (no view models/Livewire) organized by feature under `resources/views/{feature}/`, with shared partials as `_form.blade.php`-style files and Breeze's `layouts/app.blade.php` / `layouts/guest.blade.php` layouts.
- Schema corrections to already-migrated tables are done via **new**, additive migration files (never editing an already-run migration) — see the `2026_07_0*` migrations altering `dass_results`, `flagged_cases`, `system_notifications`, `students`, `users`, and `classification_thresholds` for the pattern, including the MySQL gotcha where a unique index doubling as a FK's supporting index must be dropped/re-added around a column change, not just dropped.
