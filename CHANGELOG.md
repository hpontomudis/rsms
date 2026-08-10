# Changelog

All notable changes to RSMS are recorded here, in chronological order. Small/tiny code changes are not recorded — only what's useful for understanding how the application evolved.

---

## 2026-08-10 — Phase 5 Step 0

### Fixed
- **Teaching assignments were losing their history.** Reassigning a subject to a new teacher mutated `class_subject.staff_id` in place, which retroactively re-attributed every past assessment to the incoming teacher *and* transferred edit rights to them. Assignments are now effective-dated: a reassignment closes the outgoing row and opens a new one, so historical records keep identifying the teacher who was actually in force.
- Report card listed a subject once per teacher after a reassignment; results are now grouped by subject and merged across assignments.
- `removeSubject` silently did nothing when a subject had assessments attached; it now closes the assignment instead.

### Changed
- Teachers keep **read** access to work recorded under an assignment they have since handed over, but can no longer **write** to it. Admins retain write access for corrections.
- The class screen lists active assignments only; superseded ones remain in the database for attribution.

### Database
- `class_subject` + `started_on`, `ended_on`; replaced `unique(class_id, subject_id)` with a partial unique index over active rows only, so one assignment can be current while any number are closed.

### Tests
- New `TeachingAssignmentHistoryTest` (12 tests) covering the Budi → Maria reassignment scenario end to end, including the DB-level active-assignment constraint and report-card merging. Suite: 27 → 39 passing.

---

## 2026-08-10

### Added
- Staff position field now supports typing a new position (native `<datalist>`) in addition to picking an existing one; new positions are created automatically on save
- Two new default positions: **Support Staff**, **Building Staff**

### Changed
- Replaced the horizontal top nav with a grouped left sidebar (People / Academics / Finance sections + icons), matching Rahai brand colors
- Promoted "Subjects" and "Fee Structures" from buttons tucked inside other pages to first-class sidebar links
- Dashboard stat tiles now show color-coded circular icon badges per entity
- Top bar simplified to a real user menu (avatar, name, email, logout) — removed decorative icons that had no backing feature

### Tests
- No new tests (presentation-layer changes only); full suite re-verified at 27/27 passing after both changes

---

## 2026-08-09

### Added
- **Foundation module (V1):** Students, Guardians, Staff, Academic Years, Grades, Classes — full CRUD with soft-delete and audit logging
- Authentication: session-based login/logout, admin-provisioned accounts (no self-registration), login rate-limiting
- Roles & Permissions: 7 seeded roles (`super_admin`, `principal`, `admin_staff`, `teacher`, `finance_staff`, `management`, `parent`) via `spatie/laravel-permission`, with an Eloquent Policy per core model enforcing both permission checks and object-level scoping
- Rahai School brand identity applied across the UI: official crest, color palette, Libre Baskerville typeface, printable-receipt styling
- **Attendance module (V2):** mobile-first daily attendance (one session per class per day), same-day teacher edit window, class/school-wide attendance reports, dashboard integration
- **Finance module (V3):** fee structures, invoices (auto-numbered, generated from a fee structure), payments, discounts/scholarships (with a principal-specific approval carve-out), printable receipts, student ledger view
- **Academics module (V4):** subjects, class-subject teacher assignment, assessments with score recording, per-student report cards (grouped by subject, averaged by term)

### Changed
- N/A (initial build)

### Fixed
- Dashboard tile counts weren't scoped to a teacher's own classes (showed school-wide numbers) — caught live during manual testing, fixed before release
- A user with a finance/attendance permission but no linked staff profile hit a raw 403 crash when trying to record attendance, discounts, or payments — replaced with a friendly in-app notice in both places

### Database
- Full Foundation schema: `users` (extended), `positions`, `academic_years`, `grades`, `classes`, `students`, `guardians`, `student_guardian`, `staff`, `class_student`, `class_teacher`, `audit_logs`
- Attendance schema: `attendance`, `attendance_records`
- Finance schema: `fee_structures`, `fee_items`, `invoices`, `invoice_items`, `discounts`, `payments`
- Academics schema: `subjects`, `class_subject`, `assessments`, `assessment_results`
- `spatie/laravel-permission` tables (roles, permissions, model pivots)
- Local dev environment switched from SQLite to a real PostgreSQL 17 instance; all migrations re-verified against it

### Tests
- 27 feature tests added across Foundation, policy scoping, Attendance, Finance, and Academics — covering relationship integrity, RBAC/object-level scoping, invoice balance computation, and report-card averaging
