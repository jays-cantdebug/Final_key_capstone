# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

"NORMI" — a Student DASS (Depression Anxiety Stress Scale) Assessment System: a Laravel 11 school guidance/psychometric records portal (student capstone project). Public registration is disabled; only `psychometrician` and `guidance_counselor` roles are provisioned by seeders. Backend is PHP 8.2/Blade with Laravel Breeze for auth scaffolding; frontend uses Tailwind + Alpine.js via Vite (no SPA framework).

Only the Student CRUD module (`students.*`) is actually implemented end-to-end. The psychometrician dashboard shows hardcoded placeholder stat cards (Students/Assessments/Flagged Cases/Counseling Sessions all "0") — the DASS assessment features implied by the product name aren't built yet.

**Local DB is MySQL, not SQLite**, despite `database/database.sqlite` existing and `.env.example` defaulting to sqlite: the working `.env` has `DB_CONNECTION=mysql`, `DB_DATABASE=student_mental_health_system`. Check `.env` before assuming which connection is live.

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
- Fresh DB + seed: `php artisan migrate:fresh --seed`
- Tinker (REPL): `php artisan tinker`

Test environment uses the `array` driver for cache/session and `sync` queue (see `phpunit.xml`); the sqlite in-memory `DB_CONNECTION`/`DB_DATABASE` override lines in `phpunit.xml` are commented out, so `RefreshDatabase` tests currently run against whatever `.env` points at (MySQL `student_mental_health_system`, see below) unless you uncomment those lines or override env vars.

Existing tests only cover Breeze auth flows (login, password reset/confirm/update) and profile editing — there is no test coverage yet for `StudentController`, `StudentService`, or `StudentPolicy`.

## Architecture

### Role-based access model

Authorization is centered on a `role_id` on `User`, not a package like Spatie permissions:

- `Role` (`app/Models/Role.php`) is a simple lookup table (`name`, `display_name`) seeded by `RoleSeeder` with two roles: `psychometrician` and `guidance_counselor`.
- `User::hasRole(string|array $roles)` (`app/Models/User.php`) checks the related role's `name`.
- Route-level protection uses the `role` middleware alias → `EnsureUserHasRole` (`app/Http/Middleware/EnsureUserHasRole.php`), registered in `bootstrap/app.php`. Usage: `->middleware('role:psychometrician')`.
- Model-level authorization uses Laravel Policies (e.g. `app/Policies/StudentPolicy.php`), invoked in controllers via `Gate::authorize(...)`, gated on `$user->hasRole('psychometrician')`.
- `DashboardRouteService` (`app/Services/Auth/DashboardRouteService.php`) maps a user's role to a named dashboard route (`psychometrician.dashboard`, `guidance-counselor.dashboard`, or the generic `dashboard`); `DashboardController::index` redirects there after login.

When adding a new role-gated feature, follow this same three-layer pattern: route middleware for page access, a Policy + `Gate::authorize` for the underlying model actions, and (if it needs its own landing page) an entry in `DashboardRouteService`.

### Service layer

Controllers delegate persistence/query logic to `App\Services\*` classes rather than querying Eloquent directly (see `StudentService`). Services are constructor-injected (e.g. `DatabaseManager` for wrapping writes in `->transaction()`). Follow this pattern for new resourceful features: thin controller (auth check + call service + return view/redirect), service holds the actual query/mutation logic.

### Student domain model

Core entities: `Student` belongs to `Course`, `YearLevel`, and `Section` (all simple lookup tables). `Student` uses `SoftDeletes` — "delete" is really an archive (see `StudentController::destroy` message: "archived successfully"). Status is a string enum-like field with class constants `Student::STATUS_ACTIVE` / `STATUS_INACTIVE`.

Validation for create/update lives in `StudentFormRequest` (`app/Http/Requests/StudentFormRequest.php`), which authorizes at the request level (`authorize()` returns `true`) — actual authorization is done separately in the controller via the Policy, not the FormRequest.

### Routes

`routes/web.php` is the main app route file; `routes/auth.php` (Breeze-generated) holds auth routes and is required from it. Only the `students.*` resource is currently gated behind `role:psychometrician`; guidance-counselor currently only has a dashboard route, no resource routes yet.

**Known bug:** `DashboardController::guidanceCounselor()` renders `view('guidance-counselor.dashboard')`, but `resources/views/guidance-counselor/` doesn't exist — any `guidance_counselor` user hitting `/guidance-counselor/dashboard` gets a view-not-found error. Fix by adding that view (mirror `resources/views/psychometrician/dashboard.blade.php`) before relying on that route.

### Login gating

`LoginRequest::authenticate()` (`app/Http/Requests/Auth/LoginRequest.php`) passes `is_active => true` directly into the `Auth::attempt()` credentials array, so deactivating a user (`is_active = false`) silently blocks login as an "invalid credentials" error rather than a distinct "account disabled" message.

## Conventions in this codebase

- New PHP files in `app/` generally start with `declare(strict_types=1);` (controllers, services, middleware) — match this in new files under `app/Http` and `app/Services`.
- Constructor property promotion + `private readonly` is used for injected dependencies (e.g. `StudentController`, `StudentService`).
- Views are plain Blade (no view models/Livewire) organized by feature under `resources/views/{feature}/`, with shared partials as `_form.blade.php`-style files and Breeze's `layouts/app.blade.php` / `layouts/guest.blade.php` layouts.
