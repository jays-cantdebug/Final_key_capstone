# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

"NORMI" — a Web-Based Student Depression, Anxiety and Stress Predictor (DASS-21) for Northern Mindanao Colleges, Inc.: a Laravel 11 school guidance/psychometric records portal (student capstone project). Public registration is disabled; only `psychometrician` and `guidance_counselor` roles are provisioned by seeders. Backend is PHP 8.2/Blade with Laravel Breeze for auth scaffolding (registration removed); frontend uses Tailwind + Alpine.js via Vite (no SPA framework).

The codebase is driven from a large user-owned "Master Prompt" spec, revealed and approved module-by-module. Nearly the full module list is implemented end-to-end: auth/roles, Course/YearLevel/Section management, Student Information Management, Questionnaire Management (+ versions + questions), Classification Thresholds, New Assessment (3-step wizard), AI Classification, Differentiated Flagging, Notifications, Feedback Loop, Assessment History/Results, Counseling Sessions, Reports (PDF), User Management, Settings, Audit Logs.

**Known open gaps** (tracked, not yet fixed):
- The login page's visual design doesn't fully match the approved mockup (dark/near-black background, "LOGIN" heading and button copy, exact project title text) — currently a lighter two-column Breeze-style layout.
- No full pixel-level UI audit has been done across every dashboard/report/flagged-cases view against the exact brand palette and differentiated-flag display rules (priority badge collapsing, "+1 Notification" secondary indicator, etc.) — several were fixed opportunistically when their underlying data changed, but a systematic pass hasn't run.

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

Test environment uses the `array` driver for cache/session and `sync` queue (see `phpunit.xml`); the sqlite in-memory `DB_CONNECTION`/`DB_DATABASE` override lines in `phpunit.xml` are commented out, so `RefreshDatabase` tests currently run against whatever `.env` points at (MySQL `student_mental_health_system`, see below) unless you uncomment those lines or override env vars.

Existing tests only cover Breeze auth flows (login, password reset/confirm/update) and profile editing — there is no test coverage yet for the domain modules (Student, Assessment, AI, Flagging, Feedback Loop, etc). Per the Master Prompt, feature/unit tests are intentionally deferred until explicitly requested.

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
- `ClaudeAIProvider` — scaffold only; throws `AIProviderNotImplementedException` until a real integration is configured. Swapping providers must never require touching controllers/services/schema.
- `AIProviderFactory` resolves the active provider; `AIService` is the only class controllers/services call — never instantiate a provider directly.
- `AssessmentPayload` (immutable DTO) carries only `assessmentId` + the three final subscale scores — classification input, not output.

### DASS scoring, differentiated flagging, and the Feedback Loop

- `DassScoringService` is a **pure arithmetic** service: sums raw responses per subscale and doubles them for the final score. It does not classify severity or decide flags — that moved to the AI layer and `FlaggedCaseService` respectively (an earlier draft conflated all three in one service; that's been split apart).
- `AssessmentService::submit()` runs the full pipeline in one DB transaction: save assessment/responses → compute scores → `AIService::classify()` → save `DassResult` (includes `ai_provider`) → `FlaggedCaseService::evaluateAndFlag()`.
- `FlaggedCaseService::evaluateAndFlag()` evaluates Stress, Depression, and Anxiety **independently**: Stress Severe/Extremely Severe → `flagged_cases` row with `flag_type = counseling_endorsement`; Depression and/or Anxiety Severe/Extremely Severe → separate `flag_type = awareness_notification` row(s), one per triggering subscale. A single assessment can produce 0–3 rows. Notifications go out per newly-created flagged case, to Guidance Counselors only — the Psychometrician is never a notification recipient (she sees the result immediately on submission).
- `DassResult::highestSeverityLevel()` computes an on-the-fly "highest severity" across the three subscales for display purposes (Dashboard/Reports/Counseling Sessions) — this is **not** a stored column; there is no `overall_status`/`overall_flag` in the schema.
- `PredictionFeedback` (Feedback Loop): a Psychometrician confirms or corrects the AI's classification per assessment via `PredictionFeedbackController`/`PredictionFeedbackService`, one row per assessment (updated in place on re-submission). Corrections never retroactively alter the original `dass_results` row or any already-issued flags/notifications.

### Student domain model

Core entities: `Student` belongs to `Course`, `YearLevel`, and `Section` (all simple lookup tables). `Student` uses `SoftDeletes` — "delete" is really an archive (see `StudentController::destroy` message: "archived successfully"); there is no separate `status` column — soft-delete (`deleted_at`) is the sole active/archived signal. The only sex/gender field is `gender` (Male/Female/Prefer not to say) — there is no legacy `sex` column.

`student_number` is **always system-generated** by `StudentNumberGeneratorService` (year-prefixed sequential code) — it is never typed by the Psychometrician, on either the Student Information Management "Register Student" form or the New Assessment intake form.

Validation for the Student Information Management create/update lives in `StudentFormRequest` (`app/Http/Requests/StudentFormRequest.php`), which authorizes at the request level (`authorize()` returns `true`) — actual authorization is done separately in the controller via `StudentPolicy`.

### New Assessment workflow (3-step wizard)

`AssessmentWizardController` drives Student → Questionnaire → Result, with in-progress state held in session until final submit. Step 1 (`AssessmentStudentRequest`) **always creates a brand-new `students` row** — there is no search-for-existing-student/reuse step; every assessment is treated as a first encounter. The UI takes a single "Full Name" field, split into `first_name`/`middle_name`/`last_name` in `AssessmentStudentRequest::prepareForValidation()` (2 words → first/last; 3+ words → first/last-with-middle-joined; fewer than 2 words is rejected with a validation error rather than guessed). Searching/reconciling a student who may have multiple historical records is a manual, Psychometrician-driven action through Student Information Management instead — not something the intake flow does automatically.

### Routes

`routes/web.php` is the main app route file; `routes/auth.php` (Breeze-generated, registration routes removed) holds auth routes and is required from it. Both roles now have real resource routes, not just a dashboard: Psychometrician owns `students.*`, `questionnaires.*` (+ versions/questions), `users.*`, Settings, Audit Logs, and the New Assessment wizard + Feedback Loop submission; Guidance Counselor owns `flagged-cases.index`, `notifications.*`, and `counseling-sessions.*`. Assessment History/Show and Reports are shared across both roles (some report routes are role-specific, e.g. Flagged Students/Counseling reports are guidance-counselor-only, Questionnaire Usage is psychometrician-only).

### Login gating

`LoginRequest::authenticate()` (`app/Http/Requests/Auth/LoginRequest.php`) passes `is_active => true` directly into the `Auth::attempt()` credentials array, so deactivating a user (`is_active = false`) silently blocks login as an "invalid credentials" error rather than a distinct "account disabled" message. Login throttling (5 attempts, RateLimiter, audit-logged lockouts) is implemented per spec.

## Conventions in this codebase

- New PHP files in `app/` generally start with `declare(strict_types=1);` (controllers, services, middleware, migrations added after the initial schema) — match this in new files.
- Constructor property promotion + `private readonly` is used for injected dependencies.
- Views are plain Blade (no view models/Livewire) organized by feature under `resources/views/{feature}/`, with shared partials as `_form.blade.php`-style files and Breeze's `layouts/app.blade.php` / `layouts/guest.blade.php` layouts.
- Schema corrections to already-migrated tables are done via **new**, additive migration files (never editing an already-run migration) — see the `2026_07_0*` migrations altering `dass_results`, `flagged_cases`, `system_notifications`, `students`, `users`, and `classification_thresholds` for the pattern, including the MySQL gotcha where a unique index doubling as a FK's supporting index must be dropped/re-added around a column change, not just dropped.
