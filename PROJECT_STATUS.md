# Project Status

**Current Version:** V4.5 — Phase 5 **Steps 0, 1 and 2a-i complete**; Phase 5 planning entities not started
**Current Phase:** Phase 5 architecture approved. **Step 0 (effective-dated teaching assignments), Step 1 (academic-period canonicalisation) and Step 2a-i (English programmes & proficiency levels) are implemented, tested, and verified.** The Phase 5 planning entities (Curriculum, CP, TP, ATP, Prota, Prosem, Teaching Modules, Daily Journals) and the rest of the teaching-group work (Step 2a-ii onward) are approved in design but **not started** — awaiting explicit go-ahead.
**Last verified:** 2026-08-10 — by inspecting routes, migrations, models, policies, seeders, and running the full test suite

---

## Completed

- **Foundation (V1):** Auth (login/logout, admin-provisioned accounts), Roles & Permissions, Students, Guardians, Staff, Academic Years, Grades, Classes — all with full CRUD, soft-delete, audit logging
- **Brand identity (V1.1):** Rahai crest, color palette, typeface applied to the UI
- **Attendance (V2):** mobile-first daily attendance, same-day edit window, reports, dashboard integration
- **Finance (V3):** fee structures, invoices, payments, discounts/scholarships, printable receipts, student ledger
- **Academics (V4):** subjects, class-subject assignment, assessments, score recording, report cards
- **Sidebar navigation redesign (V4.1):** grouped, permission-driven sidebar replacing the old horizontal nav
- **Staff position type-to-add (V4.2):** free-type-or-pick position field; added Support Staff / Building Staff to defaults
- **English Programmes (V4.5 / Phase 5 Step 2a-i):** proficiency frameworks (Primary colour levels, Junior High Level A/B/C) with per-programme level ordering, archive-not-delete levels, and grade applicability

## In Progress

- **Phase 5 Steps 0, 1 and 2a-i are complete.** Nothing else is in progress; Step 2a-ii (teaching groups, membership, student level placement) and the Curriculum entity onward await approval.

## Next (pending user instruction)

- Phase 5 Step 2a-ii: `teaching_groups`, `teaching_group_student`, `student_english_level_placements` — the half of the English design that puts actual students into groups and levels. Step 2a-i deliberately built only the reference data.
- Phase 5 Steps 2b–11: Curriculum → CP → TP → ATP → Prota → Prosem → Teaching Modules → Daily Journals → dashboards.
- Later cleanup migration: drop the deprecated `assessments.term` column once the new architecture has proven stable.
- The "successor teacher can read predecessor planning" test is deliberately deferred to the ATP step, since no Phase 5 planning entity exists yet to test against.
- Scope and build Excel import/export (Students first) — waiting on admin staff to confirm what data/columns their existing spreadsheets contain.
- Communication module (now V7, renumbered to make room for Phase 5) — not started, no detailed scope yet.

## Known Issues / Gaps

- **No password-reset flow.** Accounts are admin-provisioned; there's no "forgot password" screen. Not a bug — just not built yet.
- **No admin UI for managing roles/permissions.** They're defined in `RolesAndPermissionsSeeder` only; changing them today means editing code and re-seeding, not clicking through a screen.
- **No parent portal.** The `parent` role and the `student_guardian` relationship it would be scoped through both exist, but no login/UI has been built for it.
- **Not deployed.** Everything so far has been built and verified against a local dev environment (PHP 8.4, PostgreSQL 17, `php artisan serve`) — no staging/production hosting exists yet.

## Technical Debt

- None flagged as urgent. The codebase has stayed close to Laravel/Livewire conventions throughout; no known shortcuts that need unwinding.

## Important Architectural Decisions

- **Modular monolith**, not microservices — one Laravel app, one database, feature-organized folders.
- **Invoice balance/status is always computed**, never stored as a mutable field (`Invoice::balance()`, `Invoice::refreshStatus()`) — chosen specifically to prevent the finance ledger from ever drifting out of sync.
- **Policy-layer scoping, not just UI-hiding**, for teacher access (own classes/subjects only) — verified via direct-URL-access tests, not just "the button isn't shown."
- **Proficiency levels are scoped per programme, never globally.** Rahai runs more than one English framework at once, so uniqueness on level name and sequence is `(programme, name)` and `(programme, sequence)` — "Level A" can legitimately exist in two frameworks. A grade maps to at most one programme (`UNIQUE(grade_id)`), enforced by the database rather than the UI.
- **Pivot writes go through a real model, never `attach()`/`sync()`.** Those methods operate through the query builder and fire no Eloquent events, so an `Auditable` pivot written that way silently records nothing. Verified empirically before relying on it; `EnglishProgrammeGrade` is a full model and its `belongsToMany` counterpart is marked read-only.
- **Academic periods are data, not constants.** `academic_periods` (Semester 1/2 for Rahai) replaced the hardcoded Term 1/2/3 vocabulary; the report card renders whatever periods a year defines, ordered by `sequence`. `assessments.term` is deprecated and unreferenced, pending a later drop migration.
- **Datalist over a JS combobox library** for the Staff position "type or pick" field — native HTML, zero new dependency.
- **Teaching assignments are effective-dated, never mutated.** Reassigning a subject closes the outgoing `class_subject` row and opens a new one, so anything referencing it (assessments today, Phase 5 planning records later) keeps identifying the teacher actually in force. Enforced by a partial unique index over active rows only, which works identically on PostgreSQL and SQLite.
- **Read/write split on historical assignments:** teachers keep read access to their own past work but cannot write to a closed assignment; admins retain write access for corrections.

## Database Status

- PostgreSQL 17, local dev instance (`rahai_sms` database).
- 36 migrations, all applied cleanly; verified both as an in-place upgrade of the dev database and as a from-scratch migrate + seed on an isolated throwaway database (`rahai_sms_verify`, since dropped).
- Soft-deletes on: `students`, `guardians`, `staff`.
- Audit trail (`audit_logs`) covers: `Student`, `Guardian`, `Staff`, `Attendance`, `Invoice`, `Payment`, `Discount`, `Assessment`, `ClassSubject`, `AcademicPeriod`, `EnglishProgramme`, `EnglishLevel`, `EnglishProgrammeGrade`.

## Testing Status

- **77/77 automated tests passing** (PHPUnit, run against an in-memory SQLite DB per `phpunit.xml` — isolated from the Postgres dev DB).
- Coverage by area: Foundation relationships (6 tests), Policy scoping (2), Attendance (6), Finance (6), Academics (5), teaching-assignment history (13), academic periods (12), English programmes (25), plus 1 baseline routing test.
- Tests focus on business rules and authorization scoping (e.g. "a teacher cannot record attendance for a class they don't teach," "an invoice's items lock once a payment exists") rather than exhaustive UI coverage.
- Every module has also been manually verified end-to-end in-browser (desktop + mobile viewports, across at least two roles) before being marked complete.

---

## Update Rule

Update this file whenever the project moves significantly forward — a module changes status, a phase completes, a known issue is fixed or discovered, or the current version changes. Do not update it for every commit.
