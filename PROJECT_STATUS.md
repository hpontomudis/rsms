# Project Status

**Current Version:** V5.5 — Phase 5 **Steps 0-2e complete**, plus **Phase 5A–5E** (Curriculum foundation, Scopes + CP, TP, ATP, and Prota + Prosem); Teaching Modules and Daily Journals not started
**Current Phase:** Phase 5 architecture approved. **Steps 0 (effective-dated teaching assignments), 1 (academic-period canonicalisation), 2a-i (English programmes & levels), 2a-ii (teaching groups, membership, English placement), 2b (teaching-assignment extension), 2c (unified accessors + assessment integration), 2d (report-card discovery), 2e (teacher workspace), Phase 5A (curriculum registry + learning phases), Phase 5B (curriculum scopes + learning outcomes), Phase 5C (learning objectives), Phase 5D (learning pathways) and Phase 5E (annual + semester programmes) are implemented, tested, and verified.** Teaching Modules and Daily Journals are approved in design but **not started** — awaiting explicit go-ahead.
**Last verified:** 2026-08-12 — by inspecting routes, migrations, models, policies, seeders, and running the full test suite

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
- **Teaching Assignments for groups (V4.8–V5.0-pre / Phase 5 Steps 2b + 2c + 2d):** `class_subject` extended so an assignment is backed by an administrative class **or** a teaching group, with Step 0 close-and-create handover; and unified roster/year/display accessors so the existing assessment engine serves both identically; and report-card discovery across both roster sources, merged by subject.
- **Annual & Semester Programmes / Prota + Prosem (V5.5 / Phase 5E):** the planning contract in full — ATP owns sequence, Prota owns which objectives a roster covers in which academic period and their JP budget, Prosem owns when inside the period. Prota is anchored to the **roster** (class XOR teaching group), not to a teaching assignment, so **a plan survives teacher succession**: the plan stays put and write access follows the current assignment. Prosem items are one-to-many scheduling slots, and activation reconciles their JP against the annual budget. Active plans stay editable; identity does not.
- **Learning Pathways / ATP (V5.4 / Phase 5D):** linear ordered routes through one curriculum scope + subject; several may be active as alternatives; item integrity database-enforced; teachers author drafts scoped by their real active teaching assignments, management activates.
- **Learning Objectives / TP (V5.3 / Phase 5C):** `CP ↔ TP` many-to-many within one scope and subject, enforced by composite foreign keys with no application-level gap. TP carries its own draft/active/archived lifecycle so objectives can be authored while a curriculum is in force; activation requires at least one CP link.
- **Curriculum Scopes & Learning Outcomes (V5.2 / Phase 5B):** `Curriculum → Scope → Learning Outcome`, one engine for national CP (scoped by Learning Phase) and Rahai English outcomes (scoped by English Level). Cross-programme scoping refused by the database; content immutable once a version leaves draft.
- **Curriculum & Learning Phase foundation (V5.1 / Phase 5A):** seven seeded national learning phases mapped to grades (Foundation → Kindergarten 1-2, A → Year 1-2, B → Year 3-4, C → Year 5-6, D → Year 7-9, E → Year 10, F → Year 11-12), and a versioned curriculum registry with archive-and-create version lifecycle. Reference layer only — no CP, TP or scopes.
- **Teacher Workspace (V5.0-pre / Phase 5 Step 2e, extended in 5E):** `/my-teaching` — a teacher's own classes and teaching groups in one list, active and historical. Assessments was the single action at 2e; Phase 5E added the Annual Programme and the current Semester Programme. Closes the Step 2c navigation gap without widening any policy.
- **Teaching Groups & English Placement (V4.6 / Phase 5 Step 2a-ii):** year-scoped groups (English or generic), effective-dated membership with rejoin history, and per-student assessed proficiency kept deliberately independent of which group they attend

## In Progress

- **Phase 5 Steps 0-2e, Phase 5A, 5B, 5C, 5D and 5E are complete.** Nothing else is in progress; Teaching Modules / Daily Journals await approval.

## Next (pending user instruction)

- Teacher scoping through teaching groups (`StudentPolicy`), deferred until a teaching assignment can authorize it.
- Phase 5F onward: Teaching Modules (the "how") → Daily Journals (the "actual"). Both sit below Prosem in the planning contract and add no new allocation facts.
- Kindergarten developmental assessment and reporting — deliberately separate from the numeric assessment model.
- Later cleanup migration: drop the deprecated `assessments.term` column once the new architecture has proven stable.
- ~~The "successor teacher can read predecessor planning" test is deferred until a planning entity exists.~~ **Done in Phase 5E** — `PlanningUiTest::test_a_successor_sees_the_predecessors_plan_on_her_own_card` covers both directions: Eka gains write access, Sarah keeps read access.
- Scope and build Excel import/export (Students first) — waiting on admin staff to confirm what data/columns their existing spreadsheets contain.
- Communication module (now V7, renumbered to make room for Phase 5) — not started, no detailed scope yet.

## Known Issues / Gaps

- **No password-reset flow.** Accounts are admin-provisioned; there's no "forgot password" screen. Not a bug — just not built yet.
- **No admin UI for managing roles/permissions.** They're defined in `RolesAndPermissionsSeeder` only; changing them today means editing code and re-seeding, not clicking through a screen.
- **No parent portal.** The `parent` role and the `student_guardian` relationship it would be scoped through both exist, but no login/UI has been built for it.
- **Not deployed.** Everything so far has been built and verified against a local dev environment (PHP 8.4, PostgreSQL 17, `php artisan serve`) — no staging/production hosting exists yet.

## Development Conventions

- **Verify lifecycle-protected curriculum data in an isolated database, not the dev one.** Manual verification that has to activate or archive a curriculum leaves rows the lifecycle guard correctly refuses to delete. Cleaning those up with direct SQL worked once, but it must not become the routine: bypassing a guard for tidiness is how a guard stops being trusted. Use a throwaway verification database (the `rahai_sms_verify` pattern already used for fresh-install checks) whenever the verification itself needs to leave draft.

## Technical Debt

- **Draft pathway item positions are kept contiguous by the service, not by a constraint.** A partial unique index on `(pathway, position)` would need the parent's status, which SQL cannot read from an index predicate, and mirroring status onto every item purely to enable one index was judged the worse trade. Raw SQL can leave a draft gapped; `LearningPathwayService::normalise()` repairs it and activation re-validates. Documented rather than discovered.

- **`class_student` has no effective dating and no guard against a student holding two `active` rows in one academic year.** Discovered during Step 2a-ii. Nothing was changed in Phase 1; instead `StudentGradeResolver` refuses to resolve a grade when the active classes disagree, and blocks the English operation with a clear message rather than picking one. A future Foundation integrity pass should decide whether `class_student` becomes effective-dated like `class_subject` and `teaching_group_student`, or gains a partial unique index over active rows.
- **`staff.user_id` has no unique index**, so two staff rows can be linked to one login. Found in Step 2e. No migration was added (the step was scoped to require none); instead the teacher workspace refuses to resolve an ambiguous identity rather than guessing. A Foundation integrity pass should add the unique index.
- **`academic_years` permits overlapping date ranges.** Also unaddressed by design in this step; the resolver reports the ambiguity instead of choosing. Worth a constraint when Academic Years are next revisited.

## Important Architectural Decisions

- **`class_subject` is the single Teaching Assignment store.** One row = one subject + one roster + one teacher + a date range, where the roster is an administrative class XOR a teaching group. No parallel `teaching_group_subject` table, and no rename of the physical table — `assessments.class_subject_id` and every other reference keep working, and the domain concept lives in the model's documentation rather than in churn.
- **Teachers author pathways, scoped by what they actually teach.** The `academics.plan` permission is not a blanket grant: a teacher may draft only where they hold an *active* teaching assignment whose subject and resolved scope match the pathway. Year 5 and Year 6 Mathematics teachers both resolve to Phase C and collaborate on one record, which is the point. Activation stays with management — that is the approval step, so no separate approval workflow was built.
- **Alternative pathways coexist; they are not versions.** Several may be active for one scope + subject, and activating one never retires another. This is the one place the curriculum layer deliberately does NOT copy the single-active rule used for objectives.
- **TP is the school's formulation, not the published standard, so it has its own lifecycle.** CP inherits the curriculum's state; TP carries draft/active/archived of its own, because educators write and revise objectives while a curriculum is in force. Requiring every TP before activation would have made activation punitive and pushed curricula to stay in draft forever — which would have made versioning decorative.
- **A learning objective's anchor is immutable from creation.** Not merely after activation: moving a Phase C objective to Phase D would re-point what it serves and unpick the composite keys binding its CP links. An unused draft in the wrong place is deleted and rewritten.
- **Published curriculum content is immutable; a change means a new version.** A shared model guard refuses edits to scopes and outcomes once their curriculum leaves draft — for managers too, because this is versioning rather than a permission question. Drafts stay fully editable.
- **Cross-table integrity is bought with one deliberate discriminator, not with triggers.** `curriculum_scopes.english_programme_id` exists so composite foreign keys can enforce that an English level belongs to the same programme as its curriculum — something no single-column constraint can express. The one direction NULL semantics leave uncoverable is stated as an application-level rule rather than assumed away.
- **A curriculum VERSION is a historical record, never a row that gets overwritten.** Identity is `code` + `version` and becomes immutable the moment a version leaves draft; superseding archives the old version and opens a new one. Work recorded under the 2025 standards must keep pointing at the 2025 standards.
- **Learning outcomes will belong to a Learning Phase, never to a grade.** Phase C covers Year 5 and Year 6 with one set of outcomes; grades resolve through `learning_phase_grade`. This is why phases are a table rather than an enum, and why `grades` carries no phase column.
- **A signed-in user is resolved to staff explicitly, never through the `HasOne`.** `staff.user_id` has no unique index, so `User::staff()` would silently pick one of several. Anything showing "my" work resolves candidates itself and refuses on zero or many, because guessing here means showing one teacher another teacher's students.
- **Report-card discovery is a union of results and participation.** Results answer "what happened" and survive every later change of membership, assignment or group status; participation answers "what should appear even with no marks yet". Deduplicated by assignment id, so no score is counted twice for being found by both paths.
- **Report-card rows are merged by subject, never by roster.** English taken in Green A then Blue A is one row with two period columns — the roster a student sat in is not a fact the report card reports on.
- **A score sheet is the roster on the day, union everyone already scored.** A recorded mark is historical evidence, so a student who leaves a teaching group keeps appearing on assessments they were scored on. The union is computed from `assessment_results` rather than snapshotted, so there is nothing to keep in sync. The same union is the write allowlist.
- **Roster accessors take a date.** `rosterOn($date)` rather than a bare "current roster", because an ambiguous accessor is how historical assessments silently lose students. Group rosters honour the date; class rosters cannot, because `class_student` is not effective-dated, and that limitation is documented rather than faked.
- **A teaching assignment's roster source is immutable.** Changing it would rewrite what every assessment hanging off the row was about, so the model throws; the supported answer is end-and-create, the same shape as never mutating `staff_id`.
- **Modular monolith**, not microservices — one Laravel app, one database, feature-organized folders.
- **Invoice balance/status is always computed**, never stored as a mutable field (`Invoice::balance()`, `Invoice::refreshStatus()`) — chosen specifically to prevent the finance ledger from ever drifting out of sync.
- **Policy-layer scoping, not just UI-hiding**, for teacher access (own classes/subjects only) — verified via direct-URL-access tests, not just "the button isn't shown."
- **Group membership and assessed proficiency are separate facts, never synchronised.** A student assessed Blue may legitimately still attend Green A. Recording a new level closes the old one and opens a new one; it does not touch group membership, because moving a student is a human decision that a re-assessment only informs.
- **Effective-date integrity lives in services, not constraints.** Grade eligibility, no-overlapping-membership (within a group and within a programme), and no-overlapping-proficiency all need either a date-range exclusion constraint (PostgreSQL-only; SQLite runs the test suite) or a join through `english_levels` that an index cannot see without copying `english_programme_id` onto every membership row. All are enforced in `app/Services/` inside a transaction that locks the student row first. Documented as application-level so nobody assumes the database is the backstop — verified directly: PostgreSQL does accept overlapping closed ranges.
- **A student's grade is resolved only through the existing class structure, and the resolver never guesses.** `StudentGradeResolver` is the single place that knows the path. Where Phase 1's flat `class_student` allows a student two active classes in different grades in one year, it reports the ambiguity rather than picking one the way `Student::currentClass()` does. It likewise refuses to substitute the current academic year for a date that matches no configured year, or to pick between two overlapping years.
- **Proficiency levels are scoped per programme, never globally.** Rahai runs more than one English framework at once, so uniqueness on level name and sequence is `(programme, name)` and `(programme, sequence)` — "Level A" can legitimately exist in two frameworks. A grade maps to at most one programme (`UNIQUE(grade_id)`), enforced by the database rather than the UI.
- **Pivot writes go through a real model, never `attach()`/`sync()`.** Those methods operate through the query builder and fire no Eloquent events, so an `Auditable` pivot written that way silently records nothing. Verified empirically before relying on it; `EnglishProgrammeGrade` is a full model and its `belongsToMany` counterpart is marked read-only.
- **Academic periods are data, not constants.** `academic_periods` (Semester 1/2 for Rahai) replaced the hardcoded Term 1/2/3 vocabulary; the report card renders whatever periods a year defines, ordered by `sequence`. `assessments.term` is deprecated and unreferenced, pending a later drop migration.
- **Datalist over a JS combobox library** for the Staff position "type or pick" field — native HTML, zero new dependency.
- **Teaching assignments are effective-dated, never mutated.** Reassigning a subject closes the outgoing `class_subject` row and opens a new one, so anything referencing it (assessments today, Phase 5 planning records later) keeps identifying the teacher actually in force. Enforced by a partial unique index over active rows only, which works identically on PostgreSQL and SQLite.
- **Read/write split on historical assignments:** teachers keep read access to their own past work but cannot write to a closed assignment; admins retain write access for corrections.

## Database Status

- PostgreSQL 17, local dev instance (`rahai_sms` database).
- 50 migrations, all applied cleanly; verified both as an in-place upgrade of the dev database and as a from-scratch migrate + seed on an isolated throwaway database (`rahai_sms_verify`, since dropped).
- Soft-deletes on: `students`, `guardians`, `staff`.
- Audit trail (`audit_logs`) covers: `Student`, `Guardian`, `Staff`, `Attendance`, `Invoice`, `Payment`, `Discount`, `Assessment`, `ClassSubject`, `AcademicPeriod`, `EnglishProgramme`, `EnglishLevel`, `EnglishProgrammeGrade`, `TeachingGroup`, `TeachingGroupStudent`, `StudentEnglishLevelPlacement`, `TeachingGroup`, `TeachingGroupStudent`, `StudentEnglishLevelPlacement`, `Curriculum`, `LearningPhase`, `LearningPhaseGrade`, `CurriculumScope`, `LearningOutcome`, `LearningObjective`, `LearningObjectiveLearningOutcome`, `LearningPathway`, `LearningPathwayItem`.

## Testing Status

- **424/424 automated tests passing** (PHPUnit, run against an in-memory SQLite DB per `phpunit.xml` — isolated from the Postgres dev DB).
- Coverage by area: Foundation relationships (6 tests), Policy scoping (2), Attendance (6), Finance (6), Academics (5), teaching-assignment history (13), academic periods (12), English programmes (25), teaching groups & English placement (61), teaching assignments (28), group assessments (29), report-card discovery (21), teacher workspace (18), curriculum foundation (40), curriculum scopes & outcomes (49), learning objectives (45), learning pathways (56), teaching assignments (28), group assessments (29), report-card discovery (21), teacher workspace (18), curriculum foundation (40), curriculum scopes & outcomes (49), learning objectives (45), learning pathways (56), plus 1 baseline routing test.
- Tests focus on business rules and authorization scoping (e.g. "a teacher cannot record attendance for a class they don't teach," "an invoice's items lock once a payment exists") rather than exhaustive UI coverage.
- Every module has also been manually verified end-to-end in-browser (desktop + mobile viewports, across at least two roles) before being marked complete.

---

## Update Rule

Update this file whenever the project moves significantly forward — a module changes status, a phase completes, a known issue is fixed or discovered, or the current version changes. Do not update it for every commit.
