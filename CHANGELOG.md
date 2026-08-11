# Changelog

All notable changes to RSMS are recorded here, in chronological order. Small/tiny code changes are not recorded — only what's useful for understanding how the application evolved.

---

## 2026-08-12 - Phase 5B: Curriculum Scopes & Learning Outcomes

### Added
- **`curriculum_scopes`** - what a curriculum version says something about. Exactly one basis: a Learning Phase (national) or an English Level (Rahai English), enforced by a CHECK constraint.
- **`learning_outcomes`** - ONE table for both frameworks. A row is a Capaian Pembelajaran on the national curriculum and an English Learning Outcome on a Rahai English one; the wording is derived from the curriculum, the structure is identical. No national_cp / english_outcomes split to keep in sync.
- **`CurriculumScopeService`** - every scope is created through it, so the UI, the tests and any future import share one set of rules.
- Scope management on the curriculum screen, and a scope screen for outcomes: add, edit, reorder within a subject, remove - all while the curriculum is a draft.

### Cross-programme integrity
- `curriculum_scopes` carries an `english_programme_id` discriminator - the one piece of deliberate duplication - so two composite foreign keys can compare across tables a single-column constraint cannot reach:
  `(curriculum_id, english_programme_id) -> curricula` and `(english_level_id, english_programme_id) -> english_levels`.
- The database therefore refuses a Primary English curriculum scoping to Junior High Level B, refuses an English level on a national curriculum, and refuses a falsified discriminator used to sneak either past. All three verified directly against PostgreSQL and covered by SQLite tests.
- **One direction stays application-level**: an English-bound curriculum taking a learning-phase scope. A phase scope legitimately carries a NULL discriminator, and MATCH SIMPLE skips a composite key whenever any column is NULL, so no portable constraint can see it. `CurriculumScopeService` refuses it. Closing it in SQL would need a sentinel "no programme" row - inventing data to satisfy a constraint.

### Design decisions
- **Outcomes are ordered, not one-per-subject.** `UNIQUE(scope, subject, sequence)` rather than `UNIQUE(scope, subject)`: an official CP is often broken into elements, so Phase C -> Mathematics may hold several outcomes. A `code`, where used, is unique within its scope via a partial index.
- **No `grade_id` on an outcome**, permanently. Phase C covers Year 5 and Year 6 with one outcome set; grades are derived through `learning_phase_grade` for display only.
- **No `status` on an outcome.** The curriculum already carries draft/active/archived and outcomes are immutable after activation; a second lifecycle would only create a way to mutate a published standard. The smaller model is the correct one here.
- `outcome_text` is TEXT - official CP narratives are paragraphs, and a long narrative round-trips intact in test.

### Lifecycle
- A shared `BelongsToDraftCurriculum` guard refuses adding, changing or removing a scope or outcome once its curriculum leaves draft - including for principals, because this is versioning rather than permission. Drafts remain fully editable.
- Archiving never deletes content. A new version carries its own scopes and outcomes; the old version's standards stay exactly as they were.

### Not implemented
- TP (Learning Objectives), ATP, Prota, Prosem, Teaching Modules, Daily Journals.
- Curriculum standards are NOT linked to `class_subject`. Standards and teaching execution remain separate layers.
- English learning outcomes describe expected competency and never move a student between levels; proficiency placement stays its own workflow.

### Tests
- New `CurriculumScopeTest` (49 tests): scope basis rules in both directions, database-level integrity including two bypass attempts, scope uniqueness per version, outcomes and ordering, the no-grade-column rule, long-narrative persistence, draft editability, activation immutability across text/subject/reorder/delete/scope-repointing, archived retention, delete safety, vocabulary, selector filtering, authorization and audit. Suite: 274 to 323 passing.

---

## 2026-08-11 - Phase 5A: Curriculum & Learning Phase foundation

### Added
- **`learning_phases`** - the national phase structure as seeded reference DATA, not application constants: FOUNDATION, A, B, C, D, E, F. Unique on both code and sequence.
- **`learning_phase_grade`** - which grades sit in which phase, with `UNIQUE(grade_id)`. Foundation -> Kindergarten 1/2, A -> Year 1-2, B -> Year 3-4, C -> Year 5-6, D -> Year 7-9, E -> Year 10, F -> Year 11-12. Existing grade rows are reused; the seeder throws if an expected grade is missing rather than seeding a half-mapped phase.
- **`curricula`** - a versioned registry. Identity is `code` + `version` (UNIQUE), not `name`. Optional `english_programme_id` binds a curriculum to a Rahai English programme; NULL means the national, phase-based curriculum. `source_reference` is free-text provenance - no URL requirement, no document storage.
- Management UI: `/learning-phases` (reference view; description and status editable) and `/curricula` (list grouped by family, create, show, edit, activate, archive).

### Database
- Partial unique index on `curricula (code) WHERE status = 'active'` - at most one active version per family. NATIONAL and PRI-ENG may both be active; two NATIONAL versions may not. Identical syntax on PostgreSQL and SQLite, so it is a real constraint rather than a service-level hope.
- RESTRICT throughout: a phase with mappings cannot be deleted, a mapped grade cannot be deleted, an English programme bound to a curriculum cannot be deleted.

### Version lifecycle
- Superseding is **archive-and-create**. Activating a draft archives the outgoing active version of the same family and closes it the day before the successor starts; the old row is kept.
- A version's identity (`code`, `version`, `english_programme_id`) is **immutable once it leaves draft** - a model guard throws. A never-used draft stays fully editable, so a typo can still be corrected. Name, description, source reference and effective dates remain editable after activation.
- `effective_to >= effective_from` is enforced on the model, not only in the form. Curriculum dates are the version's own; there is deliberately no `academic_year_id`, since a version may span several years.

### Not seeded, deliberately
- **No curriculum row is seeded.** A version records a real school decision; inventing a version label, an effective date or a regulation reference to make the table non-empty would be fabricating one. Learning phases ARE seeded, because that structure is approved national reference data.

### Kindergarten
- Mapping Kindergarten 1/2 to the Foundation phase is a curriculum reference relationship only. Nothing in `Assessment`, `assessment_results` or `ReportCard` was changed for Kindergarten; developmental assessment and reporting remain separate future work.

### Not implemented yet
- Curriculum Scopes, CP (Learning Outcomes), TP (Learning Objectives), ATP, Prota, Prosem, Teaching Modules, Daily Journals. The contracts for the first three are recorded in MODULES.md - in particular that CP will reference a Learning Phase and never a grade, and that a scope's English level must agree with its curriculum's English programme.

### Tests
- New `CurriculumFoundationTest` (40 tests): phase seeding and per-phase grade mapping, uniqueness, idempotency, delete safety, curriculum creation and version identity, the one-active-version rule, both English bindings and the NULL case, date validation, the draft/post-draft lifecycle split, authorization for admin/principal/teacher, and audit - including a re-verification that `attach()` on the phase-grade mapping records nothing, which is why writes go through the model. Suite: 234 to 274 passing.

---

## 2026-08-11 - Phase 5 Step 2e

### Added
- **Teacher Workspace at `/my-teaching`** - a teacher's own teaching assignments, classes and teaching groups in one list. Active assignments carry roster name, roster type, subject, academic year, start date, student count and an Assessments action; closed ones move to a Previous section with their date range, still readable, with no create action.
- Sidebar entry, shown only to users who actually hold a staff profile.

### Fixed
- **The Step 2c navigation gap.** An assigned group teacher could reach their assessments only by typing the URL, because the only link lived on the teaching-group screen, which teachers cannot see.
- The assessments back-link pointed at the roster page, which 403s for a teacher on a group-backed assignment - exactly the people this step serves. It now falls back to the workspace when the viewer cannot open the roster page.

### Notes
- **Identity is resolved explicitly.** `staff.user_id` has no unique index, so two staff rows can share a login and `User::staff()` (a HasOne) would silently return whichever came first - showing a teacher someone else's work. The workspace resolves candidates itself and reports "no staff profile" or "ambiguous staff mapping" instead of guessing. Recorded as Foundation technical debt; no migration added, since the step was scoped to require none.
- English programme context is derived through group -> level -> programme, never stored on `class_subject`. A class-backed English assignment (Senior High) correctly shows none.
- Cards are shaped to take ATP, Prota, Prosem, Teaching Modules and Daily Journal later. None of those exist, and no placeholder buttons or routes were created.

### Unchanged, deliberately
- `StudentPolicy`. The workspace is assignment-centred and grants no access to student profiles, guardians, finance or attendance. A test proves a group teacher still cannot view their own group member's record.
- Assessment authorization. `AssessmentPolicy` already scoped on `class_subject.staff_id`; nothing needed widening.
- No management controls. Reassigning or ending an assignment stays on the management screens.
- **No migration.**

### Preflight
- Verified before starting: the class-participation path in `ReportCardBuilder` is student-specific (`$student->classes()` is a belongsToMany over `class_student`), not "all classes in the year". No change was needed; a negative regression test now pins it.

### Tests
- New `TeacherWorkspaceTest` (18 tests): identity resolution including the ambiguous and missing cases, both roster sources, isolation from other teachers, programme context presence and absence, active/historical split, academic-year filtering, and security - no StudentPolicy dependency, no management actions, no cross-teacher leakage via the year parameter. Suite: 216 to 234 passing.

---

## 2026-08-10 - Phase 5 Step 2d

### Added
- **`ReportCardBuilder`** - report-card discovery extracted from the Livewire component, because it now has to answer a harder question than "which classes is this student in".
- **Report cards discover teaching-group results.** English taught in Green A now reaches the report card through the same subject row as any other subject.

### Changed
- Discovery is the **union of two paths**, deduplicated by assignment id:
  - *result-driven* - every assignment the student was scored on, unfiltered by membership, assignment state or group state. A mark stays reportable after the student leaves the group, after the assignment closes, after the group is archived, after the teacher changes.
  - *participation-driven* - completeness. Classes stay scoped to the academic year exactly as before; teaching groups use membership whose range OVERLAPS the year, so a Green A membership that closed in December still counts annually.
- Rows are merged by `subject_id`, so a Green A -> Blue A move produces ONE English row with Semester 1 from Green and Semester 2 from Blue. Never grouped by teacher or group.
- Results are now explicitly constrained to the requested year's academic periods, so an assessment from another year cannot leak into an average.
- Empty-state wording corrected: rows can now legitimately exist with no marks, so "No subjects with recorded assessments" became "No subjects for this academic year".

### Unchanged, deliberately
- **The arithmetic.** percentage = score / max_score x 100; period average = round(mean of that period's percentages); subject overall = round(mean of ALL that subject's percentages) - a flat mean over results, not a mean of period averages; card overall = round(mean of non-null subject overalls). Step 2d widens discovery only; it does not redefine grading.
- English remains one ordinary subject. No programme result type, no English score table, no separate aggregation.
- Proficiency is not derived from scores and scores are not derived from proficiency. Changing a placement Blue -> Red moves nothing on the report card - asserted by test.
- Authorization. No new access was granted; this is a read-model change.
- **No migration.** Every fact needed was already recorded.

### Not included, deliberately
- Teacher Workspace / My Teaching Assignments - the navigation gap found in Step 2c remains open, and is recorded as deferred work rather than improvised during reporting.
- English proficiency-progress reporting and printable/Kindergarten report formats belong to the later Reporting & Document Generation work.

### Tests
- New `ReportCardDiscoveryTest` (21 tests): class behaviour unchanged, group discovery, leak prevention, archived/closed history, the Green -> Blue move, teacher succession in a group, no-result completeness, year and period integrity, the deprecated `term` column being ignored, and proficiency-vs-score separation. Suite: 195 to 216 passing.
- The Step 2c test that pinned "ReportCard does not discover group assignments" was converted to assert the opposite - that was the seam Step 2d was approved to move.

---

## 2026-08-10 - Phase 5 Step 2c

### Added
- **Unified teaching-assignment accessors on `ClassSubject`**, so downstream academic code stops branching on `class_id`: `academicYear()`, `displayName()`, `rosterLabel()`, `rosterUrl()`, `rosterOn($date)`, `rosterStudentIdsOn($date)`. `Year 5A + Mathematics` and `Green A + English` now expose one interface.
- **Teaching-group assessments.** A group-backed assignment creates assessments and records scores through the same screens, the same `assessments` table and the same `assessment_results` store as any class. No `english_assessments`, no `teaching_group_results` — asserted by test.
- An Assessments link on each active teaching assignment on the group screen.

### Fixed
- **The Step 2b boundary defect.** Assessment creation resolved its academic year through `classSubject->schoolClass`, so a group-backed assignment errored. It now resolves through `academicYear()`, which reads the class or the group as appropriate and throws on a malformed assignment rather than falling back to the year flagged current.

### Changed
- Assessment score sheets are now the roster as at `assessment_date` **union** everyone who already holds a result. A student who scored 85 in Green A and later moved to Blue A keeps their 85 on the Green A assessment; the union is derived from `assessment_results`, so nothing is snapshotted and nothing can drift.
- Score writes are checked against that same union, so a tampered payload cannot score a student who was never on the roster. Sharing a grade with the group is not sufficient - group membership is authoritative.
- Assessment screens use `displayName()` and show a "Teaching Group" tag rather than labelling Green A a class.
- `assessments.assessment_date` already existed and is reused as the roster date. No new column was introduced.

### Roster date semantics
- Teaching group: date-aware. Membership counts when `started_on <= date` and it had not ended by then.
- Administrative class: NOT date-aware, because `class_student` is not effective-dated. The roster remains the current `active` membership, exactly as before. The date argument is accepted and ignored rather than faked - changing this would silently alter every existing class assessment.

### Not included, deliberately
- ReportCard is untouched. A group-backed result is stored correctly but does not surface on the report card; `ReportCard` still discovers assignments by `class_id` only. A test asserts this is still the case. *(Step 2d)*
- `StudentPolicy` is untouched. No change was needed: assessment access flows through the teaching assignment, and the score screens never consult it.

### Tests
- New `TeachingAssignmentAssessmentTest` (29 tests) covering accessors, creation for both sources, period scoping, roster date semantics, historical-result preservation, write safety, the score engine, teacher scoping for both sources, and an explicit assertion that ReportCard has not changed. Suite: 166 to 195 passing.

---

## 2026-08-10 - Phase 5 Step 2b

### Added
- **`class_subject` now backs a teaching assignment with EITHER an administrative class OR a teaching group.** `class_id` became nullable, `teaching_group_id` was added, and a CHECK constraint enforces exactly one of the two. The table and model keep their names deliberately — renaming them would churn `assessments.class_subject_id` and every other reference for no behavioural gain. Conceptually a row is a Teaching Assignment.
- Teaching Assignments section on the teaching-group screen: assign subject + teacher, hand over to a different teacher, end an assignment, and read previous teachers with their date ranges.
- `TeachingAssignmentService` — the group-side workflow, mirroring Step 0's close-and-create.

### Database
- `class_subject.class_id` nullable; `class_subject.teaching_group_id` nullable, FK RESTRICT.
- `CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL))` — neither and both are rejected by the database.
- New partial unique index on `(teaching_group_id, subject_id) WHERE ended_on IS NULL`, alongside the existing class one. Closed history stays unlimited, so Sarah -> Eka handovers are representable.
- **Written twice on purpose.** Neither half of this migration is portable: PostgreSQL has `ALTER COLUMN ... DROP NOT NULL` and `ADD CONSTRAINT`, SQLite has neither. PostgreSQL alters in place; SQLite rebuilds the table (create, copy, drop, rename) preserving row ids so assessments keep pointing at the right assignment. Both paths verified.

### Changed
- `ClassSubject` gained `teachingGroup()`, `isClassBacked()`, `isTeachingGroupBacked()`, and `classBacked()`/`teachingGroupBacked()` scopes. A model guard makes the roster source immutable: repointing a row from one class to another, class to group, or Green A to Blue A throws, because it would rewrite what every assessment hanging off it was about. End the assignment and create the correct one instead.
- Existing class-backed assignments were not rewritten and behave exactly as before.

### Not included, deliberately
- **Teaching-group assessments are not implemented.** `Assessments\\Create` still resolves its academic year through `classSubject->schoolClass`, so it serves class-backed assignments only, and nothing links to it from a group. A hand-crafted URL pointing it at a group-backed assignment would error; that is the known boundary until Step 2c.
- ReportCard does not discover group-backed assignments; English-group results do not appear. (Step 2d.)
- `StudentPolicy` is untouched. Teaching a group grants a teacher nothing, and teachers cannot see teaching groups at all.

### Tests
- New `TeachingAssignmentTest` (28 tests): structure, both-and-neither rejection, active uniqueness for classes and groups, reassignment and no-op reassignment, archived-group rules, academic-year and overlap validation, roster-source immutability, audit, and authorization including an explicit check that StudentPolicy still refuses. Suite: 138 to 166 passing.

### Fixed
- Documentation correction: the Step 2a-ii report totalled the retained audit rows as 13; the actual count is 15 and matches the per-model breakdown. Counting error in the report only — no audit rows were deleted.

---

## 2026-08-10 - Phase 5 Step 2a-ii (integrity refinements)

### Changed
- **Academic-year resolution for a placement no longer falls back to the current year.** It resolves `started_on` against `academic_years.start_date`/`end_date` and uses the single match; zero matches are rejected ("The date ... does not fall within a configured Academic Year") and more than one match is rejected as ambiguous. The old fallback could validate a 2020 placement against 2026's grade without saying so. Nothing in the schema stops two academic years overlapping, so that case is reported rather than resolved with first().

### Fixed
- **Overlapping proficiency history is now rejected.** The partial unique index stops two OPEN placements but says nothing about closed ones, so Green 1 Jul - 31 Dec alongside Blue 1 Oct - 31 Jan was previously accepted. Placement validity ranges may no longer overlap for one student; a null `ended_on` counts as open-ended, and adjacent ranges (ends 15 Dec / starts 16 Dec) remain valid. A backdated placement landing inside an earlier closed period is refused.
- **Overlapping group membership is now rejected on dates, not just on "is it open".** Two rules: membership periods may not overlap within one group (generic groups included), and may not overlap across different groups of the same English programme. Green -> Blue -> Green stays recordable as long as the ranges do not collide; generic groups remain exempt from the programme rule and may overlap anything.
- The add-student picker now filters against the start date in the form rather than against today, and the date field is live-bound so the list updates as the date changes. The picker and the service rule use the same predicate, so what the list shows and what the server accepts cannot disagree.

### Notes
- Verified directly against PostgreSQL that overlapping CLOSED ranges are accepted by the database in both tables -- which is exactly why these rules are service-level. A portable constraint would need a date-range exclusion constraint (PostgreSQL-only; SQLite runs the test suite), and the programme rule would additionally need `english_programme_id` copied onto every membership row. Neither was done.
- All three checks run inside the existing transaction that locks the student row before reading and writing.
- The Phase 1 `class_student` ambiguity and the possibility of overlapping academic years are now recorded as technical debt rather than silently worked around.

### Tests
- `TeachingGroupTest` 45 -> 61 tests. Suite: 122 to 138 passing.

### Dev data
- The manual Green A / Blue A verification groups, Andi's verification membership history, and his verification placements were removed through the models, so the deletions are audited. Seeded reference data (programmes, levels, grade applicability) untouched; all 15 audit rows from the verification session retained (4 group, 6 membership, 5 placement).

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
