# NORMI — Executive Summary

*A 1–2 page overview. For full technical detail on every module, see `SYSTEM_DOCUMENTATION.md`.*

## What the System Does

**NORMI** is a web-based portal that lets school staff administer the **DASS-21** (Depression, Anxiety, Stress Scale) mental health screening to students, get an AI-assisted severity classification for the results, automatically route serious cases to the guidance office, and keep proper records for reporting and accountability.

It's a closed staff system — no public sign-up. Two roles use it:

- **Psychometrician** — administers assessments, manages student and questionnaire records, configures official scoring thresholds, manages accounts.
- **Guidance Counselor** — receives cases the system flags as needing attention, manages counseling sessions, reviews notifications.

## The Core Workflow

A New Assessment is a 3-step guided process: **Student Information → Questionnaire (21 questions) → Review AI Classification**. Nothing is saved to the database until the final step, when the Psychometrician confirms or corrects the AI's suggested classification — an earlier design that saved partial data at Step 1 left orphaned incomplete records behind every time a session was abandoned; deferring all saving to the final confirmed step eliminates that entirely.

## The AI Classification Approach

Two interchangeable classification engines sit behind one shared interface, swappable via a single config setting:

1. **A deterministic rule-based engine** — looks up each subscale score against the official DASS-21 cutoff table stored in the database (never hardcoded).
2. **A Claude API-backed engine** — sends the three scores plus the same official thresholds to Anthropic's Claude API as structured JSON, forcing the reply through Claude's "tool use" feature so the response is guaranteed to come back as valid structured data (one of exactly five severity names), not free-form text that would need fragile parsing.

**The safeguard that makes this trustworthy:** every Claude response is automatically cross-checked against what the deterministic engine computes for the same input. If they agree, Claude's answer is used. If they disagree — or the API call fails for any reason — the system silently falls back to the deterministic answer instead, and the discrepancy is logged. An incorrect AI classification can never actually reach a saved record.

**The safeguard that makes this clinically responsible:** even a fully agreed-upon AI classification is never final by itself. It's shown to the Psychometrician as a draft to **Confirm or Correct**. Whichever one she chooses — not the AI's raw output — is the *only* thing that determines whether a case gets flagged and a Guidance Counselor gets notified. The AI's original suggestion is still permanently saved for the audit trail, but it never has power to act on its own.

## Key Design Decisions

- **Human review is mandatory, not optional.** The save action itself is structured so that a classification cannot be persisted without an explicit Confirm/Correct decision on record.
- **Nothing destructive actually destroys data.** Students, courses, questionnaires, notifications — "deleting" almost always means archiving. Historical assessment records must survive even if the student, course, or questionnaire version behind them is later retired.
- **Differentiated flagging, not one generic alert.** Stress, Depression, and Anxiety are evaluated independently; severe Stress routes to a Counseling Endorsement, severe Depression/Anxiety route to an Awareness Notification — reflecting that these call for genuinely different follow-up in practice.
- **Every consequential action is logged automatically**, via a single shared observer attached to every auditable record, rather than depending on each feature remembering to log itself — guaranteeing consistent coverage.
- **The same search and filter logic is reused everywhere** (name search, combinable AND-logic filters) instead of each page inventing its own approach, keeping the system predictable to use and to extend.

## Technology Stack (at a glance)

Laravel 11 (PHP 8.2) + MySQL for the backend; Blade + Tailwind CSS + Alpine.js for a server-rendered interface with lightweight interactivity (no heavy frontend framework needed); the Claude API for AI-assisted classification; `laravel-dompdf` so every report's PDF is generated from the exact same template as its on-screen version, guaranteeing they can never drift out of sync.
