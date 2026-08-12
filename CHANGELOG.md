# Changelog

All notable changes to RSMS are recorded here, in chronological order. Small/tiny code changes are not recorded — only what's useful for understanding how the application evolved.

---

## 2026-08-12 - Phase 5F: Teaching Modules + Daily Journals

The last two layers of the V5 teaching cycle. Module answers HOW; Journal answers WHAT ACTUALLY HAPPENED. Neither restates anything upstream.

### Phase 5E lifecycle gap, closed first
The Phase 5F architecture review proved that archiving an annual programme leaves a DRAFT semester programme behind as a draft, and that `SemesterProgrammeService::assertEditable()` inspected only the child's own status. Policies already refused it and activation was already blocked, but a direct service call could still add, edit or remove slots - and Phase 5F calls into that service without a policy in the path.
- `assertEditable()` now also refuses when the parent annual programme is archived.
- `SemesterProgrammePolicy::transition()` now consults the parent too, so the screen no longer offers Activate on a draft child of an archived programme - a button that could only ever fail.
- Nine regression tests: add, update, remove, reorder, rebalance, activate, and create-new all refused; both policies false; historical read still works.

### Added
- **`teaching_modules`** - Modul Ajar nationally, Teaching Module on a Rahai English curriculum. Anchored to `class_subject`, with roster and subject mirrored from the assignment and an explicit `curriculum_scope_id`.
- **`teaching_module_learning_objective`** and **`teaching_module_semester_programme_item`** - real auditable link models.
- **`daily_journals`** - Jurnal Harian Guru / Daily Teaching Journal, plus **`daily_journal_learning_objective`** and **`daily_journal_assessment`**.
- **`TeachingModuleService`**, **`DailyJournalService`**, **`TeachingModulePolicy`**, **`DailyJournalPolicy`**.
- Screens for both, reached from the Teacher Workspace; `module`/`journal` added to the curriculum vocabulary. No new permission: `academics.plan` authors modules, `academics.record` writes journals, `academics.manage` corrects.

### Anchored to the assignment, deliberately unlike Prota/Prosem
A plan for the year belongs to the class and survives a handover. Instructional design and a teaching record belong to whoever made them. **Sarah's modules and journals stay Sarah's when Eka takes over** - Eka reads them, cannot edit them, and writes her own. No ownership transfer, no automatic copy, no `copied_from_id`. Teacher identity is never duplicated: it stays `class_subject.staff_id`.

### The scope is chosen, never guessed
Candidate scopes resolve through class -> grade -> learning phase, or teaching group -> English level, filtered to active curricula. **Zero is a stated error, one may be preselected, several must be chosen from** - silently taking the first would bind a module to the wrong curriculum version. Re-checked at draft->ready, since a class can be re-graded while a draft sits.

### Integrity
- Split composite foreign keys on `class_subject(id, class_id, subject_id)` and `(id, teaching_group_id, subject_id)` - two keys rather than one, because with a class XOR teaching group one column is always NULL and a single three-column key would be skipped entirely under MATCH SIMPLE.
- Objective links use the mirrored-anchor pattern on both sides, so cross-phase, cross-subject and national-to-English links are refused by the database including with a falsified anchor. Verified in raw SQL.
- A journal's module link is enforced by three composite keys (scope, class, group), so a Green English journal physically cannot cite a Year 5 Mathematics module.
- A new `assessments(id, class_subject_id)` anchor forces every journal assessment link to the same teaching assignment.

### Module <-> Prosem: an explicit optional many-to-many
Chosen over the architecture review's "derive it from the shared objective". A module may cover weeks 3 and 4 but **not** week 6 even where all three slots teach the same objective, and one shared slot may be served by different teacher-specific modules across a handover - neither is derivable. Nothing about the slot is copied.

### Lifecycles
- **Module: draft -> ready -> archived.** Ready freezes the plan and both link sets; `teacher_notes` stays editable, because a margin note is not the plan. **Ready returns to draft only while no journal refers to it** - after that, what was planned is history. That single rule is why there is no version, supersedes or copied-from field.
- **No new module against a closed assignment, by anyone including managers.** A plan written after the teaching would be a fiction.
- **Journal: draft -> finalized.** Finalized is frozen to its teacher and correctable only by `academics.manage`, audited. No separate 'corrected' state - the audit log is one. **Manager backfill onto a closed assignment is allowed**, but only for a date that assignment actually covered.

### Dates and periods
`academic_period_id` is stored, not derived: academic periods carry no guarantee of being non-overlapping or complete, and `periodsFor()` returns every candidate so zero and several are reported rather than resolved. A mirrored `academic_year_id` lets a composite key prove the period belongs to the assignment's year, and the service checks the date against the period AND the assignment's effective range. A journal may legitimately fall outside its planned slot's dates - teaching slips - but never outside the period.

### Two staff facts
`class_subject.staff_id` is who was responsible; `conducted_by_staff_id` is who actually taught. A substitute lesson names the substitute and changes nothing about the assignment. **The conductor need not be currently active staff**: a 2025 session taught by someone who left in 2026 is still a fact about 2025, and current status cannot prove historical status. No Position-title restriction, because `positions.title` is free text with no teaching flag - inspected rather than assumed.

### Plan versus actual
A journal's objectives are its own many-to-many, never inferred from the module. A module planned TP1 and TP2; the lesson reached TP1. That difference is the layer's entire purpose, so the journal's set need not be a subset of the plan's.

### Attendance boundary
**No `attendance_id`, and no session-attendance engine.** A class-backed journal shows that date's administrative attendance as read-only context; a teaching-group journal says plainly that groups have no attendance yet rather than rendering an empty register. Tests assert the absent columns and tables so a later phase cannot quietly introduce them.

### Fixed
- Two obsolete guard tests replaced rather than deleted: `LearningPathwayTest` and `SemesterProgrammeTest` each asserted that module and journal tables do not exist. Both now assert the boundary that still holds - that a pathway item and a schedule slot carry no instructional or actual columns.
- `DailyJournalPolicy` originally gated correction and backfill behind `academics.record`, which a principal does not hold. Correcting history is a management act; both now check `academics.manage` first.

### Tests
`TeachingModuleTest` (41), `DailyJournalTest` (43), `TeachingRecordUiTest` (13), plus nine Phase 5E gap tests in `PlanningInvariantTest` (now 49). Suite: 555 to 662 passing.

### Verification
Fresh PostgreSQL install (62 migrations) in an isolated `rahai_sms_verify` database: scope resolution, a module with two objectives linked to two of three slots, raw-SQL attacks on the objective link and the roster mirror both refused, freeze-at-ready with notes still editable, a substitute-conducted journal with the assignment untouched, actual TP as a subset of planned, a cross-assignment assessment link refused, a date outside its period refused, finalization, a finalized journal refusing deletion, a journalled module refusing to return to draft, succession in both directions including a successor citing a predecessor's ready module, an English group module refusing a National objective, and the absent attendance columns and tables. Repeated through the browser at 375px with no horizontal overflow. Database dropped, environment restored, development database left with zero modules and zero journals.

---

## 2026-08-12 - Phase 5E refinement: the active-plan invariant

Phase 5E allows an active Prota and an active Prosem to stay editable. That is the right call operationally, but it left one gap: neither layer was stopping the other from being edited into a state that contradicted it. An active semester plan could be left incomplete or unreconciled by a later annual edit, and vice versa. This closes that.

### The invariant
An ACTIVE semester programme must satisfy all of the following, continuously and not only at activation:
- every annual item allocated to its period has at least one slot
- every slot belongs to an annual item allocated to that same period
- where an annual item carries a JP budget, every one of its slots states its own JP and they sum to exactly that budget
- slot dates fall inside the period
- slot positions are contiguous 1..n

### How it is enforced
- The activation gate was extracted into `SemesterProgrammeService::assertPlanIsComplete()` and is now **re-run after every mutation** -- add slot, edit slot, remove slot, reorder, rebalance -- inside the same transaction, so a violation rolls the edit back. The state is **re-read from the database rather than simulated**, so there is no second model of the rules to drift out of step with the first.
- A DRAFT plan is deliberately exempt: it may be incomplete while it is prepared, and activation remains the gate. Structural integrity (period, dates, parent item, deterministic positions) still applies to a draft.
- An ARCHIVED plan is untouched by any of this. Nothing cascades onto historical rows.

### Annual-side protections
- **Adding an objective to a period whose plan is in force is refused**, rather than silently making that plan incomplete: *"Semester 1 already has an active Semester Programme. Add this objective through a planning revision that also schedules it."* No slot is invented and the semester plan is never pushed back to draft on the teacher's behalf.
- **Moving an unscheduled objective into such a period is refused** for the same reason. Moving a scheduled one out was already refused.
- **Removing a scheduled objective stays blocked**, with the FK RESTRICT as the backstop behind the readable error. An unscheduled one still removes normally, from a draft or an active parent.
- **Changing an annual JP budget** where the period's plan is in force requires the existing slots to already state their JP and sum to the new figure: *"This item is scheduled for 8 JP in the active Semester Programme. Update the semester allocation before changing the annual budget to 10 JP."* Clearing the budget to NULL is always allowed -- it removes the reconciliation requirement without falsifying any slot.

### The rebalance workflow
Enforcing the invariant makes sequential editing impossible for two legitimate operations, so one atomic operation covers both: `SemesterProgrammeService::rebalance()` restates an objective's whole allocation -- its annual budget and every one of its slots -- in a single transaction.
- 2+2+4 to 3+1+4 cannot be done slot by slot, because 3+2+4 does not reconcile.
- **Raising a budget from 8 to 10 is impossible from either side alone** -- change the slots and they disagree with 8, change the budget and it disagrees with the slots. This was found by a test written to prove the documented two-step worked; it did not. Both facts now move together or neither does.
- A partial map is rejected: the caller states every slot, so the total is deliberate. No revision or version subsystem was built.
- The Prosem screen exposes this as one **"Edit allocation"** action per objective, with the annual budget and all its slots in a single form.

### Archive lifecycle
- Archiving is now **bottom-up**: an annual programme refuses to archive while a child semester programme is still active, naming the period. An archived annual programme is read-only, so a live schedule beneath one would be a plan nobody could correct.
- Nothing cascades. Archived semester programmes keep every row exactly as it was, verified by comparing slot state before and after the parent was archived.

### Also
- `AnnualProgrammeShow` gained an **Edit** action for an allocation's period, budget and note. The `updateItem` service method existed since Phase 5E but had no UI, so the JP rule it enforces was unreachable from the screen.
- The Prota screen names periods whose semester plan is in force, so the restriction is visible before a write is refused rather than only after.

### Fixed
- **The Phase 5E completion report said "50 migrations"; the real count is 55.** Phase 5D ended at 50 and Phase 5E added five. Verified against both `database/migrations` and `migrate:status`. Documentation was corrected; no migration history was touched.
- `PROJECT_STATUS.md` listed the audit-trail models with three duplicated entries and none of the four planning models, and its per-area test list repeated seven areas twice while stating a stale total.

### Tests
- New `PlanningInvariantTest` (39 tests) covering every case in the refinement brief plus the audit consequences: a refused edit writes no audit row, and a successful rebalance writes exactly one per *changed* slot. `PlanningUiTest` grew to 25 with the same rules exercised through the screens. Suite: 510 to 555 passing.

### Verification
- Isolated `rahai_sms_verify` database on PostgreSQL, from the specification's fixture (8 JP as 2+2+4): parent 8 to 10, slot 4 to 3, removing a required slot, adding a new objective to the live period, and archiving the annual programme were **all refused with the plan intact**; then 2+2+4 to 3+1+4, a combined move to 4+2+4 at 10 JP, and a week-label edit all succeeded; then archive-Prosem-then-Prota succeeded and the archived plan refused further writes. Repeated through the browser at 375px, including the rejected and accepted allocation submissions. Audit counts matched exactly what was actually committed. Database dropped and the development environment restored.

---

## 2026-08-12 - Phase 5E: Annual + Semester Programmes (Prota + Prosem)

### The planning contract
Each layer owns exactly one kind of fact, and no fact is stated twice:

| Layer | Owns |
|---|---|
| ATP / Learning Pathway | the logical **sequence** of objectives within a scope + subject |
| Prota | which objectives a **roster** covers, in **which academic period**, and the **JP budget** for that period |
| Prosem | **when inside the period** - one or more scheduling slots per allocated objective |
| Teaching Module *(not built)* | **how** it will be taught |
| Daily Journal *(not built)* | **what actually happened** |

### Added
- **`annual_programmes`** - Program Tahunan nationally, Annual Programme on a Rahai English curriculum. Anchored to a class XOR a teaching group, plus subject, academic year, curriculum scope and pathway.
- **`annual_programme_items`** - one pathway objective allocated to one academic period, with an optional JP budget and notes.
- **`semester_programmes`** - one per annual programme and period.
- **`semester_programme_items`** - the scheduling slots, with `position`, free-text `week_label`, optional dates and JP.
- **`AnnualProgrammeService`** and **`SemesterProgrammeService`**; **`AnnualProgrammePolicy`** and **`SemesterProgrammePolicy`**.
- Planning screens at `/planning`, plus Prota and Prosem screens; sidebar entry; `annual`/`semester` added to the curriculum vocabulary so National reads *Program Tahunan (Prota)* / *Program Semester (Prosem)* and English reads *Annual Programme* / *Semester Programme*.
- Teacher Workspace cards now carry the Annual Programme, the Semester Programme for the period containing today, and an offer to start a plan where none exists.

### Teacher succession
- **A Prota is anchored to the roster, never to a teaching assignment, and carries no `staff_id`.** When Sarah hands Year 5A Mathematics to Eka mid-year the plan does not move, get copied, or need recreating - same row, same allocations. Write access follows the *current* active assignment, so Eka continues editing the day her assignment opens while Sarah keeps read access to what she wrote. Authorship lives in the audit trail. Verified in the browser from both sides.

### Integrity
- The mirrored-discriminator + composite-FK pattern applied five more times: the roster must belong to the programme's year, the period must belong to that year, the pathway must match the mirrored scope and subject, an allocated item must belong to the programme's pathway, and a slot must belong to both its semester programme and the annual item's period. A CHECK enforces class XOR teaching group. Verified directly in SQL, falsified discriminators included: both-and-neither roster, wrong year, wrong scope, foreign period, duplicate pathway item and a Semester-2 item pushed into a Semester-1 plan were all refused.
- **Deliberately no `UNIQUE(semester_programme_id, annual_programme_item_id)`** - one objective may legitimately occupy weeks 3, 4 and 6. Three slots for one allocation were confirmed to insert cleanly.
- Partial unique indexes (`WHERE status = 'active'`) allow one active programme per roster + subject while drafts and archives coexist.
- What no foreign key can see lives in the service: resolving *class → grade → learning phase* or *teaching group → English level* and requiring it to equal the pathway's scope. Year 5A cannot follow a Phase D pathway, Green A cannot follow Blue's, and a class cannot follow an English path at all. The creation screen only offers eligible pathways, and activation re-checks in case a class was re-graded since the draft.

### Allocation and scheduling rules
- JP is a **total for the period, not a weekly rate**; there is no `planned_weeks` field, because weeks are Prosem's business. `week_label` is a free string ("Minggu Efektif 7"), since effective weeks are not calendar weeks.
- **JP reconciliation at activation:** if an allocation carries a budget, every one of its slots must carry its own JP and they must sum to exactly that budget; if it carries none, slots schedule freely. Activation also requires every objective allocated to the period to have at least one slot. Both are shown continuously on the screen (`3 slots · 12/12 JP`), not only at the moment of refusal.
- Moving an allocation to another period is refused while it is scheduled - the composite key would reject it anyway, so the service turns a constraint violation into a sentence.
- Slot positions are normalised to a contiguous 1..n, the same application-level rule as pathway items, and only changed rows are written.

### Lifecycle
- **An active plan stays editable** - a deliberate inversion of the standards layer. A school year genuinely shifts, and rebuilding the year for a lost week would be worse than audited edits. Identity (roster, subject, year, scope, pathway) is frozen at creation; allocation never is. Archived is read-only, and only an unused draft may be deleted.
- Teachers with `academics.plan` create and edit plans for rosters they currently teach; activation and archiving stay with `academics.manage`. Anyone with `academics.view` may read a plan.

### Fixed
- A Blade `@else` glued to a word character (`JP@else`) rendered as literal text rather than a directive - found in the browser, not by the tests, which had asserted only on the substring that was present.
- `$slots` passed as view data is silently shadowed by Blade's own named-slot bag in a component view; renamed to `$scheduleSlots`. The JP summary said "2 slots" while the list below it said "Nothing scheduled yet".
- The "still editable" banner no longer shows to a reader who cannot edit.
- `LearningPathwayTest::test_no_prota_or_prosem_tables_exist_yet` was a Phase 5D guard that this phase legitimately invalidates. Replaced with the boundary that still holds - a pathway item carries no allocation columns - plus a new guard that no Teaching Module or Daily Journal table exists.

### Not implemented
- Teaching Modules and Daily Journals. The Prota screen states this on the page rather than implying completeness.

### Tests
- `AnnualProgrammeTest` (36), `SemesterProgrammeTest` (30) and `PlanningUiTest` (19), with the shared fixture graph extracted into a `BuildsPlanningFixtures` trait so neither suite re-runs the other's tests. Suite: 424 to 510 passing.

### Verification practice
- Fresh PostgreSQL install and full browser walkthrough in an isolated `rahai_sms_verify` database - multi-grade Phase C across Year 5A and Year 6A, the Sarah-to-Eka handover from both sides, an English teaching group, three slots for one objective, a refused JP shortfall then its correction, and 375px with no horizontal overflow. Every planning write appeared in `audit_logs`. The database was dropped and the development environment restored; the development database was not touched.

---

## 2026-08-12 - Phase 5D: Learning Pathways (ATP)

### Added
- **`learning_pathways`** - a linear ordered route through one curriculum scope and subject. Alur Tujuan Pembelajaran nationally, Learning Path on a Rahai English curriculum; one engine, wording derived from the curriculum. Physically neutral naming for the same reason the CP table is not called `capaian_pembelajaran`.
- **`learning_pathway_items`** - the ordered sequence, with a mirrored anchor and `notes` for sequencing rationale.
- **`LearningPathwayService`** - authoring, ordering, normalisation and the activation gate.
- **`academics.plan` permission**, granted to teacher plus the three management roles.
- Pathway list on the curriculum scope screen and a pathway screen for the sequence.

### Integrity
- Two composite foreign keys through a mirrored anchor force the item, its pathway and its objective to share a curriculum scope and subject. Verified directly in SQL: cross-phase, cross-subject, national-to-English-programme, falsified anchor and duplicate objective - all five refused.
- `UNIQUE(pathway, objective)`: an objective appears at most once. A pathway is an ordered set of goals, not a schedule of every occasion one is revisited - that belongs to the semester plan.

### Ordering
- `position` is the authoritative teaching sequence and is independent of `learning_objectives.reference_order`. The UI shows both numbers so the distinction is visible rather than assumed.
- Draft positions are normalised to a contiguous 1..n after every add, remove and move, and re-validated at activation. **This is an application-level constraint**, not a database one: a partial unique index would need the parent's status, which SQL cannot read from an index predicate, and mirroring status onto every item purely to enable one index was the worse trade. Raw SQL can still gap a draft; `normalise()` repairs it.

### Variants
- Several pathways may be ACTIVE at once for one scope + subject. They are alternative approved routes, so the single-active rule used for objectives deliberately does not apply, and activating one never retires another. Only `code` is unique among active pathways.

### Lifecycle
- Draft editable, active frozen (metadata, membership and order), archived read-only. Revision is prepare-draft, archive predecessor, activate replacement; alternatives simply coexist.
- Draft pathways may sequence draft or active objectives; an archived objective may never be newly added. Activation requires every item to reference an active objective - but an objective archived AFTERWARDS leaves the pathway valid and its items untouched.
- Curriculum boundary mirrors TP: draft ATP under a draft or active curriculum, activation only under an active one, nothing created or changed under an archived one, and archiving a curriculum leaves pathway status factual.

### Governance
- Teachers may author drafts - the first curriculum artefact they can, because a pathway is planning rather than a published standard. `academics.plan` is scoped by real teaching: a teacher may draft only where they hold an ACTIVE assignment whose subject and resolved scope match. Year 5 and Year 6 Mathematics teachers both resolve to Phase C and collaborate on the same record; a Green A English teacher reaches only the Green path; a closed assignment authorises nothing. Activation and archiving stay with `academics.manage` - that is the approval, so no separate approval workflow was built. No creator ownership.

### Fixed
- **Two more stale planning commitments corrected.** Prota was described as "a thin wrapper around an ATP with no items of its own"; that cannot work, since a Phase C pathway spans Year 5 and Year 6 and something must record which portion each assignment covers and when. And the V5 vision line still claimed the whole cycle is anchored to `class_subject`; only the execution layers are. A duplicated ATP entry left by the Phase 5C edit was also removed.

### Not implemented
- Prota, Prosem, Teaching Modules, Daily Journals. Grade and academic period enter the architecture at Prota.
- No `class_subject.learning_pathway_id`. A teaching assignment will SELECT a pathway through Prota; a test pins that the column does not exist, and another pins that no Prota/Prosem table exists.

### Tests
- New `LearningPathwayTest` (56 tests): anchor and absent-column checks, ordering independence, duplicate rejection at both layers, four database-level integrity attacks plus a falsified anchor, position normalisation after add/remove/move and repair of a raw-SQL gap, TP status eligibility including archive-after-activation, all activation gates, coexisting active variants, active-code conflict at service and database, lifecycle freezes, curriculum interaction, the full teacher-scoping matrix, audit, and delete safety. Suite: 368 to 424 passing.

### Verification practice
- Verified in an isolated `rahai_sms_verify` database. The development database was not touched and no lifecycle guard was bypassed.

---

## 2026-08-12 - Phase 5C: Learning Objectives (TP)

### Added
- **`learning_objectives`** - Tujuan Pembelajaran on the national curriculum, Learning Objective on a Rahai English one. One table, wording derived from the curriculum. Anchored to a curriculum scope and subject; no grade_id, class_subject_id, teaching_group_id or academic_year_id.
- **`learning_objective_learning_outcome`** - a real Eloquent model, not a Laravel pivot, so link changes are audited. CP traceability is many-to-many: a TP may synthesise several CP elements and a CP may inform several TP.
- **`LearningObjectiveService`** - authoring workflow and the activation gate.
- TP management on the curriculum scope screen: create, edit, link/unlink CP, reorder, activate, archive, delete drafts.

### Integrity
- Two composite foreign keys through a mirrored anchor on the link table force both sides to share a curriculum scope and subject. All three columns are NOT NULL, so unlike the Phase 5B discriminator there is **no residual application-level gap**.
- Verified against PostgreSQL by attempting each path directly in SQL: Phase C TP -> Phase D CP, Phase C Maths TP -> Phase C English CP, national TP -> Primary English Green outcome, a falsified mirrored anchor, and a duplicate link. All five refused.

### Lifecycle
- TP carries its own draft/active/archived, unlike CP which inherits the curriculum's. Educators formulate and revise objectives while a curriculum is in force; requiring every TP before activation would have made activation punitive and pushed curricula to stay in draft forever.
- Draft TP may be created under a draft OR active curriculum; activation requires an ACTIVE curriculum; nothing may be created or changed under an archived one. Archiving a curriculum does not rewrite TP status - historical status stays factual.
- **The anchor is immutable from creation**, not merely after activation. An objective in the wrong scope or subject is deleted and rewritten.
- Activation is transactional and gated on six checks: active curriculum, statement present, at least one CP link, every link still matching the anchor, no active reference-order conflict, no active code conflict.

### Reference order is not teaching order
- The column is called `reference_order`, not `sequence`, and orders the library for reading only. ATP will own instructional sequence.
- Uniqueness on reference order and code applies to the ACTIVE library only, so a draft replacement may deliberately carry its predecessor's number and code while being prepared. The revision workflow is: prepare draft -> archive the old -> activate the replacement. Nothing that already referenced the old TP is rewritten.

### Fixed
- **Stale ATP documentation corrected.** An earlier draft said an ATP is "an ordered selection of TPs for a specific teaching assignment (class_subject)" and that a TP is "linked to a CP". Neither holds: a Phase C ATP spans Year 5 and Year 6 so it cannot be owned by one class's assignment, and CP-TP is many-to-many. Both entries now say so explicitly.
- A model guard bug caught by its own tests: `isDirty([])` means "is anything dirty" and returns true, so a status-only change looked like a content edit and archiving was impossible.

### Not implemented
- ATP, Prota, Prosem, Teaching Modules, Daily Journals.
- Teacher authorship of the canonical TP library. Teachers remain read-only; their collaborative work belongs in ATP, which is per-phase and cross-grade. Revisit when ATP collaboration is designed.

### Tests
- New `LearningObjectiveTest` (45 tests): anchor immutability, the absence of teaching columns, many-to-many in both directions, four database-level rejection paths plus a falsified anchor, draft editability and zero-link drafts, all six activation gates, active/archived freezes, revision coexistence and conflict rejection, curriculum-lifecycle interaction, vocabulary, authorization, audit including proof that `attach()` records nothing, and delete safety. Suite: 323 to 368 passing.

### Verification practice
- Phase 5C was verified in an isolated `rahai_sms_verify` database per the convention recorded after Phase 5B. The development database was not touched at all, and no lifecycle guard was bypassed for cleanup.

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
