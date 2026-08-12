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

Card layout is deliberately shaped to take more actions as they are built. *Updated in Phase 5F:* cards now carry **Assessments**, the **Annual Programme** (with its status), the **Semester Programme** for the period containing today, **Teaching Modules** and **Daily Journal**. A card with no plan offers to start one. Historical cards expose the same entry points as history: a successor sees the shared Prota and Prosem plus their predecessor's modules and journals, read-only, with no "new" affordance anywhere.

**Not implemented yet, deliberately:**
- Session attendance, and any Kindergarten developmental view.
- Teacher scoping through teaching groups. `StudentPolicy` is unchanged: teaching a group grants a teacher no access to its students' profiles.
- English proficiency-progress reporting (a level history across the year) and printable/Kindergarten report formats — both belong to the later Reporting & Document Generation work.

### Planning Entities *(Phase 5 Step 2b onward)*
Status: **Proposed** — architecture approved, no code

Vision: connect the full teaching cycle — Curriculum → CP → TP → ATP → Prota → Prosem → Teaching Modules → Daily Teacher Journal → (existing) Assessment → (existing) Report Card — as structured data. *Corrected in Phase 5D: the standards and planning layers (Curriculum, CP, TP, ATP) are anchored to a **curriculum scope + subject**, not to `class_subject`. Only the execution layers (Prota onward, plus the existing Assessment) hang off a teaching assignment. That separation is what lets one Phase C pathway serve Year 5A, Year 5B and Year 6A without duplication.*

Proposed features:
- **Curriculum:** name/code/description, `status` (draft/active/archived) — admin-managed, framework-level, not tied to one academic year
- **Capaian Pembelajaran (CP):** linked to curriculum + subject + optional grade; admin-managed
- **Tujuan Pembelajaran (TP):** implemented in Phase 5C — see below. *Corrected: an earlier draft said a TP is "linked to a CP" (one-to-many). It is many-to-many, because a TP often synthesises several CP elements.*
- **Alur Tujuan Pembelajaran (ATP):** an ordered sequence of TP **within one curriculum scope and subject** (a learning phase, or an English level), via header + `atp_items`. *Corrected in Phase 5C: an earlier draft of this document said an ATP belonged directly to a teaching assignment (`class_subject`). It does not. A Phase C ATP spans Year 5 and Year 6, so it cannot be owned by one class's assignment; teaching assignments will SELECT an ATP, and several may use the same one.*
- **Program Tahunan (Prota):** implemented in Phase 5E — see below. *Corrected twice: an earlier draft called Prota "a thin wrapper with no items of its own" (impossible — a Phase C pathway spans Year 5 and Year 6, so something must record which portion each roster covers and when), and a later one said it "belongs to a teaching assignment". It belongs to the **roster** — a class or a teaching group — so it survives a mid-year teacher handover. Grade and academic period enter the architecture here, not in the pathway.*
- **Program Semester (Prosem):** implemented in Phase 5E — see below. Free-text week labels rather than a rigid calendar, and one allocated objective may take several slots.
- **Modul Ajar (Teaching Module):** implemented in Phase 5F — see below. *Corrected: an earlier draft had it linking to CP and to an Assessment at planning time. It links to TP only (traceability is Module → TP → CP), and planned assessment is free text, because at planning time the Assessment row usually does not exist yet. `planned_activity` is required alongside `title`: a module without its activity is not a plan.*
- **Jurnal Harian Guru (Daily Journal):** implemented in Phase 5F — see below. *Corrected: an earlier draft had it referencing an Attendance session. It does not — there is no `attendance_id`, and attendance is shown as read-only context for class-backed sessions only. It also does not "auto-derive" its context: the roster and subject are mirrored from the assignment for integrity, and the period and curriculum scope are chosen explicitly rather than inferred.*
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

#### Learning Pathways / ATP *(Phase 5D)*
Status: Complete

`Curriculum → Scope → TP → Learning Pathway`. On the national curriculum a pathway is an **Alur Tujuan Pembelajaran (ATP)**; on a Rahai English curriculum it is a **Learning Path**. Physically `learning_pathways` — neutral naming, because one engine serves both.

**A linear, ordered route through one curriculum scope and subject.** No `parent_item_id`, no prerequisite graph, no branches: a different valid ordering is a different pathway. Anchored to scope + subject and nothing else — no grade, class, teaching group, teaching assignment or academic year — so a Phase C pathway covers Year 5 and Year 6 as one progression. The anchor is immutable from creation.

**`position` is the authoritative teaching sequence**, entirely independent of `learning_objectives.reference_order`. The same objectives may run in a different order in a different pathway; the UI shows both numbers side by side so the distinction is visible rather than assumed.

**Several pathways may be ACTIVE at once** for one scope + subject. They are alternative approved routes, not competing versions, so the one-active rule that governs TP deliberately does not apply. Activating an alternative never retires another. Only `code` is unique among active pathways, so a draft replacement may carry its predecessor's code while it is prepared.

**Item integrity is database-enforced**, the same mirrored-anchor pattern used for CP↔TP: two composite foreign keys force the item, its pathway and its objective to share a scope and subject. Cross-phase, cross-subject, national↔English and falsified-anchor items are all refused, along with the same objective appearing twice.

**TP eligibility:** a draft pathway may sequence draft or active objectives; an archived objective may never be newly added. Activation requires every item to reference an **active** objective. If an objective is archived *afterwards*, the pathway stays valid and its items are never rewritten — activation-time eligibility and historical validity are different questions.

**Lifecycle** mirrors TP: draft fully editable, active frozen (metadata, membership and order), archived read-only. Revision is prepare-draft → archive predecessor → activate replacement; alternatives simply coexist.

**Teachers may author drafts** — the first curriculum artefact they can, because a pathway is planning rather than a published standard. The new `academics.plan` permission is scoped by real teaching: a teacher may draft only where they hold an **active** teaching assignment whose subject and resolved scope match. Year 5 and Year 6 Mathematics teachers both resolve to Phase C and collaborate on the *same* pathway; a Green A English teacher reaches only the Green path. A closed assignment authorises nothing. Activation and archiving stay with `academics.manage` — that is the approval step. No creator ownership: pathways are collaborative.

**One application-level constraint, stated plainly:** draft item positions are kept contiguous (1..n) by the service after every add, remove and move, and re-validated at activation — but not by a database constraint. A partial unique index would have to know the parent's status, which SQL cannot see from an index predicate, and mirroring status onto every item purely to enable one index was judged worse than the rule living in the service. Raw SQL can still leave a draft gapped; `normalise()` repairs it.

**Not implemented yet, deliberately:**
- Any link from `class_subject` to a pathway. A teaching assignment SELECTS a pathway through Prota; it never owns one.
- Teaching Modules, Daily Journals.
- Kindergarten developmental assessment and reporting.

Curriculum standards are **not linked to teaching assignments**: `class_subject` knows nothing about scopes, outcomes, objectives or pathways. Standards and teaching execution stay separate layers — the annual programme is where they meet a real roster. English learning outcomes describe expected competency and never move a student between levels — proficiency placement remains its own workflow.

#### Annual & Semester Programmes / Prota + Prosem *(Phase 5E)*
Status: Complete

`Curriculum → Scope → TP → Pathway → **Annual Programme → Semester Programme**`. On the national curriculum these are **Program Tahunan (Prota)** and **Program Semester (Prosem)**; on a Rahai English curriculum, **Annual Programme** and **Semester Programme**. Physically `annual_programmes` and `semester_programmes` — neutral naming, one engine.

**The planning contract, stated once:**

| Layer | Owns |
|---|---|
| ATP / Learning Pathway | the logical **sequence** of objectives within a scope + subject |
| Prota | which objectives a **roster** covers, in **which academic period**, and the **JP budget** for that period |
| Prosem | **when inside the period** — one or more scheduling slots per allocated objective |
| Teaching Module *(future)* | **how** it will be taught |
| Daily Journal *(future)* | **what actually happened** |

No fact is stated twice. The pathway does not know about periods; Prota does not schedule weeks; Prosem does not re-order the pathway.

**Prota is anchored to the ROSTER, never to a teaching assignment** — a class XOR a teaching group, plus subject, academic year and pathway. There is no `staff_id`. **A plan therefore survives teacher succession:** when Sarah hands Year 5A Mathematics to Eka mid-year, the plan does not move, get copied, or need recreating — same row, same allocations. Write access follows the *current* active assignment, so Eka can carry on editing the day her assignment opens while Sarah keeps read access to what she wrote. Authorship lives in the audit trail, not in an owner column.

**JP (`planned_lesson_periods`) is a total for the period, not a weekly rate**, and there is no `planned_weeks` field — weeks are Prosem's business.

**Prosem items are scheduling slots, deliberately one-to-many.** One allocated objective may occupy weeks 3, 4 and 6, so there is **no** unique index on `(semester_programme_id, annual_programme_item_id)`. `week_label` is a free string (`"Minggu Efektif 7"`), not a week number, because effective weeks are not calendar weeks. Slot `position` is kept contiguous by the service, the same application-level rule as pathway items.

**JP reconciliation at activation:** if an allocation carries a JP budget, every one of its slots must carry its own JP and the slots must sum to exactly that budget. If the allocation has no budget, slots may be scheduled freely. Activation also requires every objective allocated to the period to have at least one slot. Both rules are surfaced continuously on the Prosem screen (`3 slots · 12/12 JP`), not only at the moment of refusal.

**Integrity is database-enforced** by the same mirrored-discriminator + composite-FK pattern used since Phase 5B, here applied five times over: the roster must belong to the programme's academic year, the period must belong to that year, the pathway must match the mirrored curriculum scope and subject, an allocated item must belong to the programme's pathway, and a slot must belong to both its semester programme *and* the annual item's period. A CHECK enforces class XOR teaching group. Falsified discriminators are refused too. Partial unique indexes (`WHERE status = 'active'`) allow one active programme per roster + subject while draft and archived variants coexist.

**Application-level eligibility** does what no foreign key can: resolving *class → grade → learning phase* or *teaching group → English level* and requiring it to equal the pathway's scope. Year 5A cannot follow a Phase D pathway; Green A cannot follow Blue's; a class cannot follow an English path at all.

**Moving an allocation to another period is refused while it is scheduled** — the composite key would reject it anyway, so the service turns a constraint violation into a sentence.

**An active plan stays editable** — a deliberate inversion of the standards layer's immutability. A school year genuinely shifts, and rebuilding the year for a lost week would be worse than allowing audited edits. What is frozen is *identity* (roster, subject, year, scope, pathway), never allocation. Archived is read-only; only an unused draft may be deleted.

**Because both layers stay editable, neither may falsify the other.** The activation gate (`assertPlanIsComplete()`) is also a continuous invariant: every mutation of an active semester plan re-asserts completeness, JP reconciliation, dates and contiguous positions against the *resulting* database state, inside the same transaction, so a violation rolls the edit back. The state is re-read rather than simulated, so there is no second model of the rules to drift out of step with the first. On the annual side, a period whose plan is in force will not accept a new objective, an incoming move, or a budget change its slots contradict — each refused with a sentence naming the workflow, rather than inventing a slot or quietly demoting the semester plan to draft. A **draft** plan is deliberately exempt: it may be incomplete while it is prepared, though structural integrity still applies.

**One atomic operation makes replanning possible.** Sequential editing cannot reach 3+1+4 from an active 2+2+4 — the intermediate does not reconcile — and cannot raise a budget from 8 to 10 from either side alone, since changing the slots contradicts the old budget and changing the budget contradicts the slots. `SemesterProgrammeService::rebalance()` restates one objective's annual budget and every one of its slots in a single transaction; the screen offers it as **Edit allocation**. A partial map is rejected, so the total is always stated deliberately. No revision or version subsystem was built.

**Archiving is bottom-up**: an annual programme refuses to archive while a child semester programme is still active, naming the period. Nothing cascades, and archived schedules are never rewritten by anything that happens above them — an archived plan is history, not an operational record.

**Authorisation:** teachers with `academics.plan` may create and edit plans for rosters they *currently* teach; activation and archiving stay with `academics.manage`. Anyone with `academics.view` may read a plan — a plan is a public statement of what a class will be taught, not private teacher work.

**Teacher Workspace integration:** `/my-teaching` cards now expose Assessments, the Annual Programme (with status), and, for the period containing today, the Semester Programme. A card with no plan offers to start one, prefilled from the assignment.

**Not implemented yet, deliberately:**
- Document generation for Prota/Prosem (V6).

#### Teaching Modules & Daily Journals *(Phase 5F)*
Status: Complete

The last two layers of the planning contract. On the national curriculum these are **Modul Ajar** and **Jurnal Harian Guru**; on a Rahai English curriculum, **Teaching Module** and **Daily Teaching Journal**. Physically `teaching_modules` and `daily_journals` — one engine, two vocabularies.

**Module = HOW. Journal = WHAT ACTUALLY HAPPENED.** Neither restates anything upstream: no CP or TP text, no ATP order, no Prota period, no Prosem dates, no scores.

**Both are anchored to `class_subject`, deliberately unlike Prota and Prosem.** A plan for the year belongs to the class and survives a handover; instructional design and a teaching record belong to the person who wrote them. **Sarah's modules and journals stay Sarah's when Eka takes over** — Eka reads them, cannot edit them, and writes her own. There is no ownership transfer, no automatic copy and no `copied_from_id`. The roster columns and subject are mirrored from the assignment so composite keys can police them; teacher identity is never duplicated.

**A module's curriculum scope is chosen, never guessed.** Candidates resolve through *class → grade → learning phase* or *teaching group → English level*, filtered to active curricula. Zero candidates is a stated error, one may be preselected, and **several must be chosen from** — silently taking the first would bind a module to the wrong curriculum version. Re-checked at draft→ready, because a class can be re-graded while a draft sits.

**Module ↔ TP is many-to-many**, through a real auditable model with mirrored scope and subject. Two composite keys make every link database-enforced: a Phase C Mathematics module cannot link a Phase D objective, a Phase C English one, or a Green English one — including with a falsified anchor.

**Module ↔ Prosem slot is an explicit optional many-to-many.** It answers which design serves which meeting — a fact neither table holds alone. A module may cover weeks 3 and 4 but **not** week 6 even where all three teach the same objective, and one shared slot may be served by different teacher-specific modules across a handover. Links are editable only while the module is a draft, and never against an archived semester programme.

**Module lifecycle: draft → ready → archived.** Ready freezes the plan and its links; `teacher_notes` stays editable because a margin note is not the plan. **Ready may return to draft only while no journal refers to it** — once something has been taught against it, what was planned is history. That rule is why there is no version, supersedes or copied-from field: revision is archive-and-write-new.

**No new module may be written against a closed assignment by anyone**, managers included. A plan written after the teaching would be a fiction.

**A journal stores its period rather than deriving it.** Academic periods carry no guarantee of being non-overlapping or complete, so `periodsFor()` returns every candidate: one may be preselected, zero and several are both reported. A mirrored `academic_year_id` lets a composite key prove the period belongs to the assignment's year, and the service checks the date against both the period and the assignment's effective range.

**Two staff facts, and they differ.** `class_subject.staff_id` is who was responsible; `conducted_by_staff_id` is who actually taught. A substitute lesson names the substitute and changes nothing about the assignment. **The conductor need not be currently active staff** — a 2025 session taught by someone who left in 2026 is still a fact about 2025, and current status cannot prove historical status. There is no Position-based restriction, because `positions.title` is free text with no teaching flag.

**Journal ↔ TP is its own many-to-many, never inferred from the module.** A module planned TP1 and TP2; the lesson reached TP1. That difference is the entire point of the layer, so the journal's objectives need not be a subset of the module's. **Journal ↔ Assessment** records only that an activity happened — every mark stays in `assessment_results` — and a composite key forces the assessment to belong to the same assignment.

**Journal lifecycle: draft → finalized.** A finalized journal is frozen to its teacher and correctable only by `academics.manage`, audited; there is no separate "corrected" state because the audit log is one. **Manager backfill onto a closed assignment is allowed** but only for a date that assignment actually covered — an explicit correction privilege, and the one place journals are looser than modules.

**Daily Journal ≠ Attendance.** There is no `attendance_id` and no session-attendance engine. A class-backed journal may display that date's administrative attendance as read-only context; a teaching-group journal says plainly that no attendance exists for groups rather than rendering an empty register.

**`meeting_number` is advisory** — teacher-entered, not unique, not identity, and never an ordering key; journals order by date. **`actual_lesson_periods`** is the single duration field, teacher-recorded because there is still no timetable engine.

**Not implemented yet, deliberately:**
- Session or subject attendance for classes and teaching groups.
- Kindergarten developmental assessment and reporting.
- Plan-versus-actual analytics. The architecture permits it — journal → module → planned TP, journal → actual TP, journal → slot, journal → assessments — but nothing computes it yet.
- Document generation (V6).

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
