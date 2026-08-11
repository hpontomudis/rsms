# RSMS Modules

This file tracks each module and its current, actually-implemented functionality. It is the first thing to check before starting new work, and the first thing to update after finishing it.

Status values: `Complete`, `In Progress`, `Proposed` (architecture written, awaiting approval, no code), `Planned`, `Not Started`.

---

## V1 — Foundation

### Authentication
Status: Complete

Features:
- Session-based login / logout
- Accounts are admin-provisioned — **no self-registration screen**
- Account `status` (active/disabled) checked at login
- Login rate-limiting (5 attempts) per email+IP

Known gap: no "forgot password" / password-reset flow yet.

### Users, Roles & Permissions
Status: Complete

Features:
- 7 seeded roles: `super_admin`, `principal`, `admin_staff`, `teacher`, `finance_staff`, `management`, `parent`
- Permission-per-action model via `spatie/laravel-permission` (e.g. `students.view`, `finance.manage`)
- One Eloquent Policy per core model, enforcing both permission checks and object-level scoping (e.g. a teacher can only act on their own classes)
- `super_admin` bypasses all checks via a `Gate::before` hook
- No admin UI to manage roles/permissions yet — they're seeder-defined only (`RolesAndPermissionsSeeder`)

### Students
Status: Complete

Features:
- Profile CRUD (student number, name, DOB, gender, enrollment date, status)
- Search by name/student number, filter by status
- Soft-delete ("Archive") — preserves attendance/finance/academic history
- Guardian linking (attach/detach existing guardians with relationship type, primary-contact flag, pickup permission)
- Class enrollment (attach/detach, with enrollment date)
- Attendance History section (recent records)
- Finance section (invoices + running outstanding balance)
- Audit-logged (create/update/delete)

### Parents / Guardians
Status: Complete

Features:
- Profile CRUD (name, phone, email, address, occupation)
- One guardian can be linked to multiple students, and one student to multiple guardians — no duplicate guardian records
- Student linking from the guardian side (mirrors the student-side form)
- Soft-delete, audit-logged

### Staff
Status: Complete

Features:
- Profile CRUD (staff number, name, position, phone, email, hire date, status)
- Position field: pick from existing positions **or type a new one** (native `<datalist>`, created on the fly via `firstOrCreate` — no duplicate rows on re-use) — *added V4.2*
- Default seeded positions: Principal, Vice Principal, Homeroom Teacher, Subject Teacher, Finance Officer, Administration Staff, Librarian, Support Staff, Building Staff
- Optional link to a `users` login account
- "Classes Taught" list on the staff profile
- Soft-delete, audit-logged

### Classes
Status: Complete

Features:
- Academic Years (start/end date, one `is_current` at a time)
- Grades (Kindergarten 1–2, Year 1–12, fixed seeded list)
- Classes (name + grade + academic year + optional capacity)
- Teacher assignment (homeroom / assistant / subject_teacher roles per class)
- Student roster (enroll/unenroll with date)
- Subject assignment (see Academics module — `class_subject`)
- **Effective-dated teaching assignments** *(Phase 5 Step 0)*: reassigning a subject to a different teacher closes the outgoing assignment (`ended_on`) and opens a new one rather than overwriting it, so past assessments keep identifying the teacher who actually recorded them. A partial unique index permits exactly one active assignment per class+subject alongside any number of closed ones. The class screen lists active assignments only.
- **`class_subject` is now the Teaching Assignment store** *(Phase 5 Step 2b)*: a row is one subject, taught to one roster, by one staff member, over a date range — and the roster is **either** an administrative class **or** a teaching group, never both and never neither. The table and model keep their old names deliberately; renaming them would churn every reference (`assessments.class_subject_id` above all) for no behavioural gain. Class-backed assignments are unchanged in every respect.

---

## V2 — Attendance

Status: Complete

Features:
- One attendance session per class per day (not per subject-period)
- Mobile-first entry: roster with large Present/Late/Excused/Absent buttons, defaults to Present, "mark all present" shortcut
- Same-day edit window for teachers; `admin_staff`/`super_admin` can correct historical records
- A teacher can only take/view attendance for classes they are assigned to teach (object-level policy check, not just hidden UI)
- Class + school-wide attendance report (date range, per-student present/late/excused/absent counts, attendance rate %)
- Attendance History section on the student profile
- Dashboard integration: teacher sees today's classes with taken/not-taken status; admin/principal see a school-wide attendance-today rate

Not built: per-subject-period attendance, offline queuing (both explicitly deferred per the original PRD's MoSCoW).

---

## V3 — Finance

Status: Complete

Features:
- Fee Structures (per grade + academic year, composed of named fee items)
- Invoices: created from a fee structure (items copied onto the invoice), auto-numbered `INV-{year}-####`
- Invoice balance/status **always computed** from items − discounts − payments, never stored — cannot drift
- Status auto-transitions `unpaid → partially_paid → paid`; `void` is a manual, terminal state
- Line items lock once any payment exists on the invoice (edits after that go through a new discount/payment entry, not history edits)
- Discounts & Scholarships: fixed-amount or percentage, with a required reason and an approver
  - `finance_staff` can approve discounts as part of general finance management
  - `principal` can **also** approve discounts without holding general finance-management access (a specific role carve-out)
- Payments: cash/bank_transfer/other, auto-numbered receipts `RCT-{year}-####`
- Printable receipt (crest + brand styling, browser print-to-PDF — no PDF library dependency)
- Student ledger section (all invoices + running outstanding balance) on the student profile
- Dashboard: outstanding balance, payments received today, overdue invoice count

---

## V4 — Academics

Status: Complete

Features:
- Subjects (name, optional grade restriction)
- Class-Subject assignment: a subject taught in a specific class by a specific staff member (`class_subject`, unique per class+subject)
- Assessments: name, **academic period** (FK to `academic_periods`), max score, date, scoped to a class-subject
- Score recording: roster with numeric score inputs per student, validated against the assessment's max score
- A teacher can only create assessments / record scores for class-subjects they are assigned to (object-level policy check), and only while that assignment is **active** — after a handover they keep read access to their past work but can no longer edit it; admins retain write access for corrections *(Phase 5 Step 0)*
- Report card groups by subject and merges results across a subject's successive teacher assignments, so a mid-year handover doesn't split it into two rows *(Phase 5 Step 0)*
- A class-subject with existing assessments cannot be deleted (`restrictOnDelete` at the DB level, not just app-level)
- Report Card: per student, per academic year, grouped by subject, averaged per **academic period** (columns generated from `academic_periods.sequence`) with an overall average
- Assessments list/create/show screens; Subjects list/create/edit screens

**Academic Periods** *(Phase 5 Step 1)*: `academic_periods` is the canonical reporting period within an academic year — seeded as Semester 1 / Semester 2 for Rahai, unique per year on both name and sequence, RESTRICT-protected against deletion while assessments reference it. Neither the count nor the names appear in application logic, so a year that later needs three periods is a data change only. The old free-text `assessments.term` column is deprecated: unreferenced by any code path, excluded from `$fillable`, and scheduled for removal in a later cleanup migration.

---

## V5 — Academic & Teaching Administration

Status: **Steps 0, 1, 2a-i and 2a-ii complete; planning entities not started.** The architecture is approved. Step 0 (effective-dated teaching assignments) and Step 1 (academic-period canonicalisation) modified existing modules and are documented under V1 Classes / V4 Academics above. Steps 2a-i and 2a-ii are new modules, documented immediately below. **None of the planning entities listed further down exist yet.**

### English Programmes & Proficiency Levels *(Phase 5 Step 2a-i)*
Status: Complete

Rahai teaches English in proficiency groups that cut across classes, and runs more than one framework: Primary uses colour levels (Purple → Pink → Gold → Green → Blue → Red), Junior High uses Level A/B/C, and Senior High has no programme at all. This module holds the *reference data* for that — which programmes and levels exist, and which grades each programme applies to.

Features:
- Programme CRUD (name, optional code, description, active/archived status)
- Inline level management on the programme screen: add, reorder (up/down), archive. Levels are **archived, never deleted** — a level stays a valid reference for historical data even when no student currently occupies it
- Grade applicability: link/unlink grades to a programme. The "add grade" dropdown offers only grades not already claimed by another programme
- Read access for anyone with `academics.view` (teachers included, read-only); write requires `academics.manage` (super_admin, principal, admin_staff)
- Audit-logged (create/update/delete on programmes, levels, and grade links)

Seeded reality: Primary English Programme (`PRI-ENG`, 6 levels, Year 1–6) and Junior High English Programme (`JHS-ENG`, 3 levels, Year 7–9). Kindergarten 1–2 and Year 10–12 intentionally map to no programme.

Rules enforced at the **database** level, not just in the UI:
- Programme names are globally unique; level names and sequences are unique **per programme**, so "Level A" may exist in more than one framework
- `UNIQUE(grade_id)` on the pivot — a grade belongs to at most one programme, or to none
- `RESTRICT` on both foreign keys: a programme with levels cannot be deleted, and a grade referenced by a programme cannot be deleted

Implementation note worth keeping: the grade pivot is a real Eloquent model (`EnglishProgrammeGrade`), not a `belongsToMany` write path. `attach()`/`detach()`/`sync()` go through the query builder and fire no model events, so an `Auditable` pivot written that way records nothing — verified empirically, not assumed. All writes go through `create()`/`delete()`; `grades()` exists but is marked read-only.

### Teaching Groups & English Placement *(Phase 5 Step 2a-ii)*
Status: Complete

Where Step 2a-i recorded the standards, this records the operational reality: which students are actually grouped together, and what level each has been assessed at. **These are two separate facts and the system never syncs one to the other** — a student assessed Blue may legitimately still attend Green A, and a re-assessment is information for a human deciding whether to move them, not an instruction to move them.

Features:
- **Teaching groups**, scoped to one academic year: list (filtered by year), create, edit, archive. A group carrying an English level is an English proficiency group; one without is a generic group (Choir, Chess Club). There is no `kind` enum — Remedial/OSN/Elective categories will be designed when there are real requirements for them.
- **Groups are never generated from English levels.** A level is a standard; a group is a room of students in one year. "Green" may run as one group, as Green A + Green B, or not at all. No production seed data.
- **Roster management**: add an eligible student, end a membership, with active and historical members shown separately. Membership is effective-dated (`started_on`/`ended_on`) following the Step 0 pattern — a student may leave a group and return later, and both stints survive.
- **English placement** per student: current assessed level, full history, and change-of-level by close-and-open. Optional `assessed_on`, reason, and notes. The screen also shows which groups the student actually attends, so the two facts sit side by side.
- Read and write both require `academics.manage` — see Authorization below.
- Audit-logged: group create/update/archive, membership add/end, placement create/close/change.

Rules enforced at the **database** level:
- `unique(academic_year_id, name)` on groups — the same group name recurs each year
- Partial unique index `UNIQUE(teaching_group_id, student_id) WHERE ended_on IS NULL` — one open membership per group+student, any number of closed ones behind it. Deliberately not `class_student`'s flat uniqueness, which would forbid returning to a group.
- Partial unique index `UNIQUE(student_id) WHERE ended_on IS NULL` on placements — exactly one open assessed level per student
- `RESTRICT` on every foreign key: academic year, English level, group, student

Rules enforced in the **application** (`app/Services/`), because they cannot be expressed as constraints without denormalising:
- **Grade eligibility.** For an English group, the student's grade must be covered by that group's programme. Resolved through the existing class structure only — student → active `class_student` → class in the group's academic year → grade → programme. No second student-grade field was introduced.
- **No overlapping membership within one group.** A student's membership periods in a single group may not overlap, whether the rows are open or closed. Applies to generic groups too.
- **No overlapping membership within one English programme.** Across different groups of the same programme, membership periods may not overlap either — so Green A and Blue A cannot both cover October. Adjacent ranges (one ends 15 Dec, the next starts 16 Dec) are fine, and Green → Blue → Green remains recordable as long as the dates do not collide. Generic groups are exempt and may overlap anything.
- **No overlapping proficiency periods.** A student has one assessed level in force at a time, so placement validity ranges may not overlap. The partial unique index only stops two *open* rows; overlapping closed history slips past it, which is why the rule is checked in the service. A backdated placement landing inside an earlier closed period is rejected.
- **Academic-year resolution never falls back.** A placement carries no academic year, so it is resolved from `started_on` against `academic_years.start_date`/`end_date` (both NOT NULL). Exactly one match is used; zero matches and more than one match are both rejected with a clear message. There is deliberately no substitution of the year flagged current — that would validate a historical placement against today's grade and say nothing.
- **Membership dates fall inside the group's academic year**, and `ended_on >= started_on`.
- **A group's English level is locked once it has ever had a member** — changing it would rewrite what the group was and who had been eligible for it.

All of the above run inside a transaction that locks the student row (`SELECT … FOR UPDATE`) before checking and writing, so two administrators acting on the same student at the same moment serialise rather than both passing the check.

Authorization is deliberately stricter than Step 2a-i: rosters and placements require `academics.manage`, not `academics.view`. Teachers hold `academics.view`, and until Step 2b records which teacher teaches which group there is no basis on which to scope a teacher's access — so they get none rather than all. Programme/level reference data stays readable to teachers; it names no students. `StudentPolicy` was not touched.

Known ambiguity carried over from Phase 1 (documented, not redesigned here): `class_student` is flat, with a `status` enum and no effective dating, and nothing stops a student holding two `active` rows for different classes in the same year. `Student::currentClass()` resolves that with `first()`. Eligibility checks must not be silent, so `StudentGradeResolver` refuses to guess: if the active classes for a year point at more than one distinct grade it reports the data problem instead of picking one.

### Teaching Assignments for Teaching Groups *(Phase 5 Steps 2b + 2c + 2d)*
Status: Complete — **assessable and reportable**

A teaching group can now be given a subject and a teacher, stored as an ordinary `class_subject` row with `class_id` NULL and `teaching_group_id` set. There is deliberately no separate `teaching_group_subject` table.

Features:
- Teaching Assignments section on the group screen: assign a subject + teacher, hand a subject over to a different teacher, end an assignment, and read the history of previous teachers with their date ranges
- Handover uses the Step 0 close-and-create pattern — the outgoing row keeps its original `staff_id`, so past work keeps naming the teacher who did it. Re-selecting the teacher already in place is a no-op, not a fake succession
- Assignments cannot be created against an archived group; archiving a group later does **not** touch its existing assignments
- Audited through the existing `ClassSubject` audit trail: a handover records the close of the old row and the creation of the new one

Enforced by the **database**:
- `CHECK ((class_id IS NULL) <> (teaching_group_id IS NULL))` — exactly one roster source
- one active assignment per `class_id + subject_id` (Step 0) and one per `teaching_group_id + subject_id` (new), both partial unique indexes over `ended_on IS NULL`
- `RESTRICT` on `teaching_group_id`, so a group with assignment history cannot be deleted

Enforced in the **service** (`TeachingAssignmentService`): assignment dates must fall inside the group's own academic year (never the year flagged current), `ended_on >= started_on`, and assignment periods for one group+subject may not overlap — the partial index only stops two *open* rows.

Enforced on the **model**: a row's roster source is immutable. Repointing an assignment from one class to another, from a class to a group, or from Green A to Blue A throws — the supported answer is to end the assignment and open the correct one.

#### Assessments for teaching groups *(Step 2c)*

**One assessment engine, one score store.** A group-backed assignment creates rows in `assessments` and `assessment_results` through exactly the same screens and the same code as a class-backed one. There is no `english_assessments`, no `teaching_group_results`, and no parallel workflow — verified by a test that asserts those tables do not exist.

Downstream code no longer branches on `class_id`. `ClassSubject` exposes:
- `academicYear()` — from the class or the group, whichever backs it. Throws rather than falling back to the year flagged current, so a malformed assignment fails loudly instead of attaching assessments to the wrong reporting periods
- `displayName()` — "Year 5A" or "Green A"
- `rosterLabel()` — "Class" or "Teaching Group", so the UI never calls Green A a class
- `rosterUrl()` — back to the class or group page
- `rosterOn($date)` / `rosterStudentIdsOn($date)` — deliberately date-taking, because an ambiguous "current roster" accessor is exactly how a historical assessment loses students

**Roster date semantics**, which differ by source because the underlying data does:
- *Teaching group* — genuinely date-aware. A student counts when `started_on <= date` and the membership had not ended by then. A student who left on 15 December is on the November score sheet and off the January one.
- *Administrative class* — not date-aware, because `class_student` is not effective-dated (recorded under Technical Debt). The roster stays the current `active` membership, exactly as before Step 2c. The date argument is accepted and ignored rather than faked.

**Historical results never disappear.** An assessment's score sheet is the roster as at `assessment_date` **union** everyone who already holds a result on it. A student who scored 85 in Green A and later moved to Blue A still appears on the Green A assessment with their 85 — a mark does not stop being true because a student moved. Nothing is snapshotted: `assessment_results` already records who was scored.

**Write safety.** Scores are checked against that same union on save, so a tampered form payload cannot invent a student who was never on the roster. Sharing a grade with the group is explicitly not sufficient — group membership is authoritative.

Assessment dates and periods come from the assignment's own academic year, so a Green A assessment offers only that year's Semester 1 / Semester 2. `assessments.assessment_date` already existed and is reused as the roster date; no new column was added.

#### Report cards across both roster sources *(Step 2d)*

`ReportCardBuilder` discovers a student's subjects from **two complementary paths**, unioned and deduplicated by assignment id:

1. **Result-driven** — every assignment this student was actually scored on, found through `assessment_results` → `assessments` → `class_subject`. Deliberately unfiltered by membership, assignment state or group state. A recorded mark stays reportable after the student leaves the group, after the assignment closes, after the group is archived and after the teacher changes. *Archived means no new activity, not that the past stops having happened.*
2. **Participation-driven** — completeness, so a subject appears even before any mark exists. Classes are scoped to the requested academic year exactly as before (no chronology is invented from `class_student`, which has no effective dating). Teaching groups use membership whose validity range **overlaps** the academic year, not merely membership still open today — a Green A membership that closed in December still counts towards the annual report.

Discovery never goes via grade or programme, so a Green A student cannot pick up Blue A's subjects just because both teach English to Year 5.

**Merged by subject, not by assignment.** A student who took English in Green A for Semester 1 and Blue A for Semester 2 gets **one** English row — Semester 1 from Green A, Semester 2 from Blue A. The same holds for a teacher handover inside one group. Rows are never grouped by teacher or by group.

**English stays one ordinary subject.** No programme-specific result type, no English score table, no separate aggregation. Programme context lives in the underlying assignments; Senior High class-based English uses the same subject row.

**Proficiency and score are different facts.** A student's assessed English level is never derived from assessment averages, and no score threshold promotes anyone Blue → Red. Changing a placement does not move a single number on the report card.

**Arithmetic unchanged from Phase 4** — Step 2d widened discovery only:
- percentage = `score / assessment.max_score × 100`
- period average = `round(mean of percentages whose assessment sits in that period)`
- subject overall = `round(mean of percentages of all that subject's results)` — a flat mean over results, **not** a mean of the period averages
- card overall = `round(mean of the non-null subject overalls)`

Only assessments whose `academic_period_id` belongs to the requested year are consumed. The deprecated `assessments.term` is never read, and columns still come from `academic_periods.sequence`, so a year with three periods renders three columns without a code change.

#### Teacher Workspace — My Teaching Assignments *(Phase 5 Step 2e)*
Status: Complete

`/my-teaching` is a teacher's own list of teaching assignments — administrative classes and teaching groups together, because they are the same thing wearing different rosters. It closes the navigation gap found in Step 2c, where an assigned group teacher could reach their assessments only by typing the URL.

Features:
- **Active** section: roster name, roster type badge (Class / Teaching Group), subject, academic year, start date, current student count, and an Assessments action
- **Previous** section: closed assignments with their date range. Still readable, still linked to their assessments, but no create action — Step 0's read/write split is what enforces that, not the page
- English programme context (`Primary English Programme · Green`) derived through group → level → programme. Nothing is stored on `class_subject`, and a class-backed English assignment — Senior High, say — simply has none
- Academic-year selector, so historical years remain reachable. The year always comes from the assignment's own roster source, never from `is_current`

**Identity is resolved explicitly, not through `User::staff()`.** `staff.user_id` carries no unique index, so two staff rows can share one login; the `HasOne` would silently return whichever came first and show a teacher someone else's work. The page resolves candidates itself and reports *no staff profile* or *ambiguous staff mapping* rather than guessing.

**It grants nothing.** No `StudentPolicy` change, no guardian, finance or attendance access, no student-profile links, and no management controls — reassigning a teacher stays on the management screens. Assessment access remains governed entirely by `AssessmentPolicy`, which scopes on `class_subject.staff_id`.

Mobile-first: cards rather than a wide table, so at 375px a teacher sees roster, subject, status and the Assessments action without scrolling sideways.

Card layout is deliberately shaped to take more actions later — ATP, Prota, Prosem, Teaching Modules, Daily Journal — but **only Assessments exists today**. No placeholder buttons or dead routes were created.

**Not implemented yet, deliberately:**
- ATP, Prota, Prosem, Teaching Modules, Daily Journal — the future actions this workspace is shaped for. None exist.
- Teacher scoping through teaching groups. `StudentPolicy` is unchanged: teaching a group grants a teacher no access to its students' profiles.
- English proficiency-progress reporting (a level history across the year) and printable/Kindergarten report formats — both belong to the later Reporting & Document Generation work.

### Planning Entities *(Phase 5 Step 2b onward)*
Status: **Proposed** — architecture approved, no code

Vision: connect the full teaching cycle — Curriculum → CP → TP → ATP → Prota → Prosem → Teaching Modules → Daily Teacher Journal → (existing) Assessment → (existing) Report Card — as structured data, anchored to the existing `class_subject` "teaching assignment" record so teacher/subject/class/grade/academic-year never need re-entering.

Proposed features:
- **Curriculum:** name/code/description, `status` (draft/active/archived) — admin-managed, framework-level, not tied to one academic year
- **Capaian Pembelajaran (CP):** linked to curriculum + subject + optional grade; admin-managed
- **Tujuan Pembelajaran (TP):** implemented in Phase 5C — see below. *Corrected: an earlier draft said a TP is "linked to a CP" (one-to-many). It is many-to-many, because a TP often synthesises several CP elements.*
- **Alur Tujuan Pembelajaran (ATP):** an ordered sequence of TP **within one curriculum scope and subject** (a learning phase, or an English level), via header + `atp_items`. *Corrected in Phase 5C: an earlier draft of this document said an ATP belonged directly to a teaching assignment (`class_subject`). It does not. A Phase C ATP spans Year 5 and Year 6, so it cannot be owned by one class's assignment; teaching assignments will SELECT an ATP, and several may use the same one.*
- **Program Tahunan (Prota):** a thin publish/finalize wrapper around an ATP for a teaching assignment — deliberately has no items of its own, reuses the ATP's — teacher-managed
- **Program Semester (Prosem):** semester-level plan (week-by-week items, free-text week labels rather than a rigid calendar) — teacher-managed
- **Modul Ajar (Teaching Module):** the richest record — links to CP/TP/ATP/an actual Assessment (optional), planning narrative fields (materials, methods, activities, assessment strategy, reflection, differentiation) — only `title` and the teaching assignment are required, everything else optional
- **Jurnal Harian Guru (Daily Journal):** what was actually taught, distinct from the Module's plan; auto-derives teacher/subject/class/grade/year from the teaching assignment; optionally references a Teaching Module, an Attendance session (counts computed live, never copied), and an Assessment (scores stay in the existing assessment tables)
- **Journal Dashboard:** "my teaching journal" calendar view, filterable by class/subject/date
- **Teaching Administration Dashboard:** per-teacher completion view; admin/principal school-wide view — completion metrics compare journal entries against actual attendance sessions taken (no fabricated "expected sessions" number, since no timetable/schedule module exists yet)

Explicitly out of scope for V5 (per the spec that produced this proposal):
- Document export/generation (DOCX/PDF) — deferred to V6
- A formal academic-calendar/timetable/scheduling system — deferred to a future phase
- An approval/review workflow beyond a simple draft/completed/reviewed status on journals

Reuses without modification: `academic_years`, `grades`, `subjects`, `classes`, `class_subject`, `staff`, `assessments`, `assessment_results`, `attendance`, `attendance_records`, `audit_logs`.

One existing file flagged for a small additive change (pending approval): `ClassSubject` doesn't currently use the `Auditable` trait; the spec asks for "teaching assignment changes" to be logged.

Full table list, relationship rationale, and delete-safety rules: see the Phase 5 architecture proposal (repository conversation / commit history — not duplicated here to avoid this file drifting out of sync with the authoritative version).

---

### Curriculum & Learning Phase foundation *(Phase 5A)*
Status: Complete — **reference layer only**

The versioned curriculum registry and the national Learning Phase structure that curriculum work will hang off. Nothing that defines actual learning content exists yet.

**Learning phases** (`/learning-phases`) — seeded reference data, not application constants, so a ministry adjustment is a data correction rather than a redeploy:

| Phase | Code | Grades |
|---|---|---|
| Foundation | `FOUNDATION` | Kindergarten 1, Kindergarten 2 |
| Phase A | `A` | Year 1, Year 2 |
| Phase B | `B` | Year 3, Year 4 |
| Phase C | `C` | Year 5, Year 6 |
| Phase D | `D` | Year 7, Year 8, Year 9 |
| Phase E | `E` | Year 10 |
| Phase F | `F` | Year 11, Year 12 |

Grades are mapped through `learning_phase_grade`, never a `learning_phase_id` column on `grades` — the grade table describes what a grade *is*, not which curricular frameworks classify it, the same separation used for English programme applicability. `UNIQUE(grade_id)` enforces one phase per grade at the database level. The reference page allows editing a phase's description and status; codes, sequences and grade mappings are national structure and are not editable.

**Curricula** (`/curricula`) — a versioned registry where a *version* is a first-class historical record:
- identity is `code` + `version` (`UNIQUE`), never `name`. Names are presentation and may be corrected
- `english_programme_id` binds a curriculum to a Rahai English programme, or is NULL for the national phase-based curriculum. No curriculum-type enum: the only distinction the school actually has today is "bound to an English programme or not"
- effective dates belong to the version, not to an academic year — a version may span several. `effective_to >= effective_from` is enforced on the model, not just the form
- **at most one active version per family**, enforced by a partial unique index on `code WHERE status = 'active'` (identical syntax on PostgreSQL and SQLite). `NATIONAL` and `PRI-ENG` may both be active; two `NATIONAL` versions may not
- superseding is archive-and-create: activating a draft archives the outgoing version and closes it the day before the successor starts. The old row is kept
- a version's identity (`code`, `version`, `english_programme_id`) is **immutable once it leaves draft** — records point at it, and rewriting those fields would retroactively change what they were taught against. A never-used draft stays fully editable
- nothing is seeded. A curriculum version records a real school decision; inventing a version label and an effective date to make the table non-empty would be fabricating one

**Kindergarten note:** mapping Kindergarten 1/2 to the Foundation phase is a *curriculum reference* relationship only. It does **not** mean Kindergarten uses the numeric assessment/report-card model. Nothing in `Assessment`, `assessment_results` or `ReportCard` was changed for Kindergarten; developmental assessment and reporting remain separate future work.

#### Curriculum Scopes & Learning Outcomes *(Phase 5B)*
Status: Complete

`Curriculum → Curriculum Scope → Learning Outcome`. One engine serves both frameworks:

| | National curriculum | Rahai English curriculum |
|---|---|---|
| Scope basis | Learning Phase | English Level |
| UI wording | *Fase*, *Capaian Pembelajaran (CP)* | *Level*, *Learning Outcome* |

There is no `national_cp` / `english_outcomes` split — same tables, wording derived from the curriculum's `english_programme_id`.

**A scope has exactly one basis**, enforced by a CHECK constraint: a learning phase XOR an English level, never both and never neither.

**Cross-programme integrity is enforced by the database.** `curriculum_scopes` carries an `english_programme_id` discriminator — the one piece of deliberate duplication here — so two composite foreign keys can compare across tables that SQL otherwise cannot join in a constraint:
- `(curriculum_id, english_programme_id) → curricula (id, english_programme_id)`
- `(english_level_id, english_programme_id) → english_levels (id, english_programme_id)`

Together these make the database refuse a Primary English curriculum scoping to Junior High Level B, refuse an English level on a national curriculum, and refuse a falsified discriminator used to sneak either past. Verified on PostgreSQL and SQLite. **One direction remains application-level**: an English-bound curriculum taking a *learning phase* scope. A phase scope legitimately carries a NULL discriminator, and SQL's MATCH SIMPLE semantics skip a composite key whenever any column is NULL, so no constraint can see it — `CurriculumScopeService` refuses it instead.

**Outcomes are ordered, not one-per-subject.** `UNIQUE(scope, subject, sequence)` rather than `UNIQUE(scope, subject)`, because an official CP is often broken into elements: Phase C → Mathematics → outcome 1, outcome 2. A `code`, where used, is unique within its scope (partial index).

**No `grade_id` on an outcome, permanently.** Phase C covers Year 5 and Year 6 with one outcome set; the grades are derived through `learning_phase_grade` for display only.

**No `status` on an outcome either.** The curriculum already carries draft/active/archived, and a second lifecycle would only create a way to mutate a published standard. `outcome_text` is TEXT — official CP narratives are paragraphs.

**Content is immutable once the curriculum leaves draft.** A shared model guard refuses adding, changing or removing a scope or outcome whose curriculum is active or archived — for managers and principals too, since this is versioning rather than permission. A draft is fully editable. Superseding means a new curriculum version, whose scopes and outcomes are its own; the old version's standards stay exactly as they were. Archiving never deletes content.

#### Learning Objectives / TP *(Phase 5C)*
Status: Complete

`Curriculum → Scope → Learning Outcome ↔ Learning Objective`. On the national curriculum a row is a **Tujuan Pembelajaran (TP)**; on a Rahai English curriculum it is a **Learning Objective**. One table, wording derived from the curriculum — there is no `english_learning_objectives`.

**Anchored to a curriculum scope and subject**, never to a grade, class, teaching group or academic year. A Phase C objective serves Year 5 and Year 6 alike; which grade works on which part is a teaching decision ATP will make later. The anchor is **immutable from creation**, even on a draft: an objective in the wrong place is deleted and rewritten, so the composite keys that bind its CP links stay simple and historical meaning never migrates between standards.

**CP links are many-to-many.** A TP may synthesise several CP elements, and one CP may inform several TP — traceability reads correctly in both directions. Enforced by two composite foreign keys through a mirrored anchor on the link table:
- `(learning_objective_id, curriculum_scope_id, subject_id) → learning_objectives`
- `(learning_outcome_id, curriculum_scope_id, subject_id) → learning_outcomes`

All three columns are NOT NULL, so unlike the Phase 5B discriminator there is **no residual application-level gap**: the database itself refuses a Phase C Mathematics TP linked to a Phase D outcome, to a Phase C English outcome, or to a Primary English Green outcome — including when the mirrored anchor is falsified to try to force it through.

**TP has its own lifecycle**, unlike CP:

| | Draft | Active | Archived |
|---|---|---|---|
| Content, code, title, reference order | editable | frozen | frozen |
| CP links | editable | frozen | frozen |
| Anchor | immutable | immutable | immutable |
| Deletion | allowed if unused | archive instead | never |

CP had no authoring lifecycle of its own — it inherited the curriculum's. TP genuinely does, because the school formulates and revises objectives *while a curriculum is in force*.

**Curriculum lifecycle interaction:** a draft TP may be created under a **draft or active** curriculum, but may only be **activated** under an active one, and nothing may be created or changed under an **archived** curriculum. Archiving a curriculum does not rewrite TP status — historical status stays factual.

**Activation is gated** and runs in a transaction: the curriculum must be active, the statement must be present, **at least one CP link must exist**, every link must still match the anchor, and no other *active* objective may hold the same reference order or code.

**Reference order is not teaching order.** `reference_order` orders the library for reading; ATP will own instructional sequence and may select a subset in a different order. Uniqueness on reference order and code applies to the **active** library only, so a draft replacement may deliberately carry its predecessor's number and code while it is prepared — that is the revision workflow: prepare draft → archive the old → activate the replacement. Anything that already referenced the old TP keeps referencing it.

**Not implemented yet, deliberately:**
- ATP. Contract above.
- **ATP.** An *ordered sequence of TP within a Learning Phase*, not a list of daily lesson objectives. It must support one phase spanning several grades, so teaching can later allocate parts of an ATP across Year 5 and Year 6 and across academic years without changing the CP's phase identity.
- Prota, Prosem, Teaching Modules, Daily Journals.
- Kindergarten developmental assessment and reporting.

Curriculum standards are **not linked to teaching assignments**: `class_subject` knows nothing about scopes, outcomes or objectives. Standards and teaching execution stay separate layers until TP/ATP connect them. English learning outcomes describe expected competency and never move a student between levels — proficiency placement remains its own workflow.

---

## V6 — Document Generation

Status: Not started (not scoped — deliberately deferred until V5 exists and is stable)

Long-term goal: structured V5 data → template → generated DOCX/PDF for Prota, Prosem, ATP, Modul Ajar, Jurnal Harian, Rapor, semester reports.

---

## V7 — Communication

Status: Planned (not started; renumbered from V5 to make room for Academic & Teaching Administration above)

Anticipated features (from the original PRD, not yet scoped in detail):
- Announcements (school-wide / grade / class scoped)
- Parent follow-up logs
- Notifications (in-app / email)

---

## V8 — AI-Assisted Management

Status: Planned (not started; renumbered from V6)

---

## Cross-cutting (not a module, tracked here so it isn't lost)

### Bulk Data Import/Export (Excel)
Status: Requested, not yet scoped

The user wants to bulk-upload existing spreadsheet data (starting with Students) instead of one-by-one entry, plus export for backup/reporting. Scoping is pending admin staff confirming exactly what data/columns exist in their current spreadsheets. See `PRD.md` §12 for the guardian-relationship design note.

### App Shell / Navigation
Status: Complete

- Grouped sidebar layout (People / Academics / Finance sections + icons), permission-driven group visibility — Academics now includes English Programmes
- Mobile slide-in drawer
- User menu with logout
