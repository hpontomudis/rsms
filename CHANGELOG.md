# Changelog

All notable changes to RSMS are recorded here, in chronological order. Small/tiny code changes are not recorded — only what's useful for understanding how the application evolved.

---

## 2026-08-10 - Phase 5 Step 2a-ii

### Added
- **Teaching groups** - groups of students taught together within one academic year, at `/teaching-groups`. A group carrying an English level is a proficiency group; one without is a generic group. No `kind` enum: Remedial/OSN/Elective will be designed from real requirements, not guessed at now.
- **Group rosters** - add an eligible student, end a membership, with current and past members shown separately. Groups are never generated from English levels, and no production seed data is shipped: a level is a standard, a group is a room of students the school actually decided to run.
- **English proficiency placement** per student, at `/students/{student}/english-placement` - current assessed level, full history, change-of-level by close-and-open, optional assessment date, reason and notes. The screen also lists the groups the student actually attends, so the two facts sit side by side.
- Three domain services (`app/Services/`) holding the rules, so the UI, the tests, and any future import path all go through the same code.

### Database
- `teaching_groups` - `unique(academic_year_id, name)`; academic year and English level both `RESTRICT`.
- `teaching_group_student` - effective-dated, with partial unique index `UNIQUE(teaching_group_id, student_id) WHERE ended_on IS NULL`. Deliberately not `class_student`'s flat uniqueness, which would make Green -> Blue -> Green impossible to record.
- `student_english_level_placements` - partial unique index `UNIQUE(student_id) WHERE ended_on IS NULL`: one open assessed level, any number of closed ones behind it. No grade or programme column, both being derivable.

### Notes
- **Proficiency and group membership are never synchronised.** A student assessed Blue may still attend Green A. Recording a new level does not move anyone; a test asserts the membership rows are byte-identical before and after a re-assessment.
- **Two rules are application-level, not database-level, and that is deliberate.** Grade eligibility for an English group, and "one open English group per programme", both depend on the join path group -> level -> programme. Neither can be a unique index without copying `english_programme_id` onto the membership row, so both are enforced in a transaction that locks the student row first. The database is not the backstop for these two; everything else here is.
- **Student grade is resolved only through the existing class structure** (`StudentGradeResolver`), with no second student-grade field. Where Phase 1's flat `class_student` permits a student two active classes in different grades in one year, the resolver reports the ambiguity instead of silently picking one the way `Student::currentClass()` does.
- A group's English level locks once the group has ever had a member - changing it would rewrite what the group was and who had been eligible for it.

### Security
- Teaching groups and placements require `academics.manage`, **not** `academics.view`. Teachers hold `academics.view`, and gating rosters on it would hand every teacher every roster in the school. Until Step 2b records which teacher teaches which group there is no basis to scope a teacher's access, so they get none. Asserted over HTTP against the real routes, not just by hiding buttons. `StudentPolicy` was not modified.

### Tests
- New `TeachingGroupTest` (45 tests). Suite: 77 to 122 passing.
- Verified beyond the suite: PostgreSQL partial indexes and RESTRICT exercised directly on a from-scratch database, and manual browser checks covering group creation, the eligibility-filtered picker, a live rejection of a second English group, membership end and rejoin, the locked level field, mobile layout, and a 403 for a signed-in teacher.

### Not included
- Anything that gives a group a teacher, makes an English group assessable, or touches `class_subject`, assessments, report cards, or the planning entities.

---

## 2026-08-10 - Phase 5 Step 2a-i

### Added
- **English Programmes** - reference data for Rahai's proficiency-based English teaching. Two seeded frameworks: Primary (`PRI-ENG`, Purple > Pink > Gold > Green > Blue > Red, Year 1-6) and Junior High (`JHS-ENG`, Level A/B/C, Year 7-9). Kindergarten and Year 10-12 intentionally map to nothing.
- Management UI at `/english-programmes`: programme CRUD, inline level add/reorder/archive, and grade link/unlink. The grade dropdown offers only grades not already claimed by another programme.
- `academics.manage` granted to `principal` (previously admin_staff and super_admin only).

### Database
- `english_programmes` - name unique globally, optional code, description, active/archived status.
- `english_levels` - `UNIQUE(programme, name)` and `UNIQUE(programme, sequence)`. Deliberately **not** globally unique on name: Rahai runs more than one framework, so "Level A" may legitimately exist in two of them. `RESTRICT` on the programme FK - a programme with levels cannot be deleted.
- `english_programme_grade` - `UNIQUE(grade_id)`, not a composite. A grade belongs to at most one programme or to none, enforced by the database rather than the UI. `RESTRICT` on both FKs.
- Levels are archived, never deleted, so a level stays a valid reference for historical data even with no students in it.

### Notes
- The grade pivot is a real Eloquent model (`EnglishProgrammeGrade`), not a `belongsToMany` write path. `attach()`, `detach()` and `sync()` go through the query builder and fire **no** model events, so an `Auditable` pivot written that way records nothing at all. Measured before relying on it: attach -> 0 audit rows, detach -> 0, `create()` -> 1, `delete()` -> 1. All writes go through the model; `grades()` remains for reads and is marked read-only.

### Fixed
- Level reorder could violate `UNIQUE(programme, sequence)`. `update()` re-syncs a model's original attributes, so reading the neighbour's "previous" sequence *after* writing to it returned the new value and the swap collided. Both sequences are now captured before either write, and the swap runs through a sentinel value inside a transaction.

### Tests
- New `EnglishProgrammeTest` (25 tests) covering structure, seeded reality, delete safety, authorization, audit, and the UI paths. Suite: 52 to 77 passing.
- Verified beyond the suite: PostgreSQL constraint and RESTRICT behaviour on a from-scratch database, seeder idempotency, and manual browser checks as both super_admin (full management, reorder, link/unlink, audit rows written with user and IP) and teacher (read-only - no Edit, Add, reorder, archive, or Remove controls).

### Not included
- Teaching groups, group membership, and per-student level placement. Step 2a-i is the reference half only; nothing yet assigns a student to a level.

---

## 2026-08-10 - Phase 5 Step 1

### Added
- `academic_periods` - the canonical reporting period within an academic year, seeded as **Semester 1 / Semester 2** for Rahai. The number and names of periods are data: a year needing three periods requires no code change.
- Academic-period validation on assessment creation: a period belonging to a different academic year is rejected.

### Changed
- Assessment create replaces the hardcoded Term 1/2/3 dropdown with periods loaded from the selected teaching assignment's academic year.
- ReportCard columns are now generated from `academic_periods.sequence` instead of a hardcoded term array.

### Deprecated
- `assessments.term`. `academic_period_id` is the single canonical source. `term` is removed from `$fillable`, read by nothing, and made nullable so new rows leave it empty; it remains physically present for rollback safety and will be dropped in a later cleanup migration.

### Database
- `academic_periods` (unique per year on both `name` and `sequence`; `academic_year_id` RESTRICT).
- `assessments.academic_period_id` - added nullable, backfilled by **exact name match only**, gated by a validation migration that refuses to proceed while any row is unmapped, then set NOT NULL. No automatic Term-to-Semester rule was encoded; the single known Phase 4 demo row was mapped by hand.

### Fixed
- Closed teaching assignments now reject **new** assessments for every role, admins included. Admins may still correct assessments that already exist. (Closed assignment = no new academic activity.)

### Tests
- New `AcademicPeriodTest` (12 tests). Suite: 39 to 52 passing.

---

## 2026-08-10 - Phase 5 Step 0

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
