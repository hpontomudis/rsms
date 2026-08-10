# Project Status

**Current Version:** V4.3 — Phase 5 **Step 0 complete**; Phase 5 entities not started
**Current Phase:** Phase 5 architecture approved. **Step 0 (effective-dated teaching assignments) is implemented, tested, and verified.** Step 1 (academic-period migration) and all Phase 5 entities are approved in design but **not started** — awaiting explicit go-ahead.
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

## In Progress

- **Phase 5 Step 0 is complete** (effective-dated teaching assignments — see CHANGELOG 2026-08-10). Nothing else is in progress; Step 1 onward awaits approval.

## Next (pending user instruction)

- Phase 5 Step 1: academic-period migration (`academic_periods` table; `assessments.term` → `academic_period_id`, old column deprecated then dropped in a later cleanup).
- Phase 5 Steps 2–11: Curriculum → CP → TP → ATP → Prota → Prosem → Teaching Modules → Daily Journals → dashboards.
- The "successor teacher can read predecessor planning" test is deliberately deferred to the ATP step, since no Phase 5 planning entity exists yet to test against.
- Scope and build Excel import/export (Students first) — waiting on admin staff to confirm what data/columns their existing spreadsheets contain.
- Communication module (now V7, renumbered to make room for Phase 5) — not started, no detailed scope yet.

## Known Issues / Gaps

- **No password-reset flow.** Accounts are admin-provisioned; there's no "forgot password" screen. Not a bug — just not built yet.
- **No admin UI for managing roles/permissions.** They're defined in `RolesAndPermissionsSeeder` only; changing them today means editing code and re-seeding, not clicking through a screen.
- **No parent portal.** The `parent` role and the `student_guardian` relationship it would be scoped through both exist, but no login/UI has been built for it.
- **Report card terms are hardcoded** (`Term 1`/`Term 2`/`Term 3` in `Assessment::$term`, a free-text field) rather than driven by a database table. Fine for Rahai's current term structure; would need a code change (not just data entry) to support a different term naming scheme.
- **Not deployed.** Everything so far has been built and verified against a local dev environment (PHP 8.4, PostgreSQL 17, `php artisan serve`) — no staging/production hosting exists yet.

## Technical Debt

- None flagged as urgent. The codebase has stayed close to Laravel/Livewire conventions throughout; no known shortcuts that need unwinding.

## Important Architectural Decisions

- **Modular monolith**, not microservices — one Laravel app, one database, feature-organized folders.
- **Invoice balance/status is always computed**, never stored as a mutable field (`Invoice::balance()`, `Invoice::refreshStatus()`) — chosen specifically to prevent the finance ledger from ever drifting out of sync.
- **Policy-layer scoping, not just UI-hiding**, for teacher access (own classes/subjects only) — verified via direct-URL-access tests, not just "the button isn't shown."
- **`term` on `assessments` is a free-text string**, not a foreign key to a Terms table — a deliberate "don't build a table before it's needed" call per the PRD's working principles. Documented above as a known simplification.
- **Datalist over a JS combobox library** for the Staff position "type or pick" field — native HTML, zero new dependency.
- **Teaching assignments are effective-dated, never mutated.** Reassigning a subject closes the outgoing `class_subject` row and opens a new one, so anything referencing it (assessments today, Phase 5 planning records later) keeps identifying the teacher actually in force. Enforced by a partial unique index over active rows only, which works identically on PostgreSQL and SQLite.
- **Read/write split on historical assignments:** teachers keep read access to their own past work but cannot write to a closed assignment; admins retain write access for corrections.

## Database Status

- PostgreSQL 17, local dev instance (`rahai_sms` database).
- 28 migrations, all applied cleanly; run in phase order (Foundation → Attendance → Finance → Academics → spatie permission tables → Phase 5 Step 0 effective dating).
- Soft-deletes on: `students`, `guardians`, `staff`.
- Audit trail (`audit_logs`) covers: `Student`, `Guardian`, `Staff`, `Attendance`, `Invoice`, `Payment`, `Discount`, `Assessment`, `ClassSubject`.

## Testing Status

- **39/39 automated tests passing** (PHPUnit, run against an in-memory SQLite DB per `phpunit.xml` — isolated from the Postgres dev DB).
- Coverage by area: Foundation relationships (6 tests), Policy scoping (2), Attendance (6), Finance (6), Academics (5), teaching-assignment history (12), plus 1 baseline routing test.
- Tests focus on business rules and authorization scoping (e.g. "a teacher cannot record attendance for a class they don't teach," "an invoice's items lock once a payment exists") rather than exhaustive UI coverage.
- Every module has also been manually verified end-to-end in-browser (desktop + mobile viewports, across at least two roles) before being marked complete.

---

## Update Rule

Update this file whenever the project moves significantly forward — a module changes status, a phase completes, a known issue is fixed or discovered, or the current version changes. Do not update it for every commit.
