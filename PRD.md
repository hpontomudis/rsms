# RAHAI SCHOOL MANAGEMENT SYSTEM (RSMS) — Master PRD

| | |
|---|---|
| School | Rahai School |
| Organization | Yayasan Pendidikan Halmahera Membangun Bangsa |
| Location | North Halmahera, Indonesia |
| Document type | Living master blueprint — update only for material changes (see rule at bottom) |
| Last updated | 2026-08-16 (V9.0 Phase V9A built — AI infrastructure, Communication Draft Assistant, Daily Journal Reflection Assistant, Teaching Module AI Planning Assistant, Deterministic Management Insights + AI Narrative; AI is optional assistance, never the source of truth) |

---

## 1. Product Vision

A single, secure, always-current system where every person connected to Rahai School — student, parent, teacher, or administrator — has one record, and every school process (attendance, fees, academics, performance, communication) operates on top of that one record.

RSMS is a purpose-built **School Management System**, not a generic CRM. It is scoped tightly to how Rahai School actually operates, and grows only as real operational need is proven.

## 2. Goals

1. One canonical, de-duplicated record for every student, guardian, and staff member.
2. Mobile-first daily attendance fast enough for teachers to use reliably from a phone.
3. A reliable, auditable system for fees, invoices, payments, discounts, and scholarships.
4. A consistent academic record (assessments, scores, report cards) per student, per academic year.
5. Role-appropriate access so each user type sees only what matters to their job.
6. An architecture where each new phase connects to existing data rather than re-modeling it.
7. A system a small IT/admin team can operate and maintain long-term.

## 3. Non-Goals

- Not a Learning Management System (no course content delivery).
- Not a general-purpose CRM (marketing/sales pipelines, donor management).
- No parent-facing native mobile app (a responsive web view is the future path, not yet built).
- No payroll, general ledger, or tax filing — Finance tracks **student fees/payments only**.
- No Ministry of Education (Dapodik) integration yet.
- No multi-school/multi-tenant support yet (architecture doesn't block it, but it isn't built).

## 4. Target Users / User Roles

| Role | Who | Portal access today |
|---|---|---|
| `super_admin` | IT/system owner | Full access to everything (bypasses all permission checks) |
| `principal` | School head | Read access across modules; can approve discounts/scholarships |
| `admin_staff` | School administration | Manages people, classes, academics; records attendance |
| `teacher` | Teaching staff | Records attendance and scores **only for their own classes/subjects**; read-only elsewhere |
| `finance_staff` | Finance office | Manages fee structures, invoices, payments, discounts |
| `management` | Yayasan leadership | Read-only cross-module reporting |
| `parent` | Guardian | Role exists, seeded, but **no portal UI is built yet** — access will be scoped via the `student_guardian` relationship when it is |

Roles and permissions are implemented with `spatie/laravel-permission`. The authoritative, current permission-to-role mapping lives in code at `database/seeders/RolesAndPermissionsSeeder.php` — treat that file as the source of truth over any prose description, including this one.

## 5. System Architecture

**Pattern:** modular monolith — one Laravel application, feature modules as folders, one database, one auth system. Not microservices; matches the team size and the "avoid unnecessary complexity" principle.

- **Backend:** Laravel 13, PHP 8.3+
- **Database:** PostgreSQL (local dev via a real Postgres instance, not SQLite)
- **Frontend:** Blade + Livewire 4 (full-page components, no separate SPA/API layer), Tailwind CSS v4
- **Auth:** Laravel session auth. Accounts are **admin-provisioned** — there is no self-registration screen.
- **Authorization:** one Eloquent Policy per core model (`app/Policies/`), backed by spatie permissions. A `Gate::before` hook in `AppServiceProvider` grants `super_admin` everything.
- **Audit logging:** an `Auditable` trait (`app/Models/Concerns/Auditable.php`) writes to `audit_logs` on create/update/delete. Currently applied to: `Student`, `Guardian`, `Staff`, `Attendance`, `Invoice`, `Payment`, `Discount`, `Assessment`, `ClassSubject`, `AcademicPeriod`, `EnglishProgramme`, `EnglishLevel`, `EnglishProgrammeGrade`, `TeachingGroup`, `TeachingGroupStudent`, `StudentEnglishLevelPlacement`, and the curriculum layer (`Curriculum`, `CurriculumScope`, `LearningOutcome`, `LearningObjective`, `LearningPathway` and their link/item models). Note that `attach()`/`detach()`/`sync()` fire no Eloquent events, so link tables that must be audited are written as full models, never through a `belongsToMany` write.
- **Soft deletes:** `Student`, `Guardian`, `Staff` — archived, never hard-deleted, so historical records (attendance, invoices, assessments) stay intact.
- **Brand identity:** Rahai's official crest, color palette (`#171F46` navy / `#575D79` slate / `#F3C445` gold / `#AAADBB` grey), and primary typeface (Libre Baskerville) are applied to the UI chrome (sidebar, buttons, headings); data tables stay neutral for legibility.
- **App shell:** grouped left sidebar navigation (People / Academics / Finance sections), permission-driven — a section header only renders if the current role can see at least one item in it.

## 6. Core Modules

| Module | Phase | Status |
|---|---|---|
| Foundation (Users, Roles, Students, Guardians, Staff, Academic Years, Grades, Classes) | V1 | Complete |
| Attendance | V2 | Complete |
| Finance (Fee Structures, Invoices, Payments, Discounts, Receipts) | V3 | Complete |
| Academics (Subjects, Assessments, Report Cards) | V4 | Complete |
| Academic & Teaching Administration (Curriculum → CP → TP → ATP → Prota → Prosem → Teaching Modules → Daily Journals) | V5 | **Complete** — the full chain is built (Steps 0-2e, Phases 5A-5F). Session attendance, teacher student-scoping and Kindergarten developmental reporting remain out of scope |
| Reporting & Document Generation (printable Rapor + Prota/Prosem/ATP/Modul Ajar/Jurnal, and published Academic Records) | V6 | **Phase 6A complete** — browser-native print for all seven document types; issued report cards are immutable period-scoped snapshots. Attendance on the rapor, KG developmental reporting and a server-side PDF renderer remain out of scope |
| Staff Performance Evaluation (Categories, Frameworks, evidence-informed evaluations) | V7 | **Phase V7A complete** — system evidence is context only and never sets a rating; finalization snapshots the record permanently with no correction path. Printing an evaluation is out of scope |
| Communication (Announcements, targeted notices, in-app inbox) | V8 | **Phase V8A complete** — school-authored Communications with explicit Audience Rules, recipients materialized and frozen at Publish, in-app delivery only. External delivery (email, WhatsApp), scheduled publishing, attachments and two-way conversation are all out of scope for this phase |
| AI-Assisted Management (provider-neutral infrastructure, Communication Draft Assistant, Daily Journal Reflection Assistant, Teaching Module AI Planning Assistant, Deterministic Management Insights + AI Narrative) | V9 | **Phase V9A complete** (V9A-1, V9A-2, V9A-3, V9A-4, V9A-5) — governing rule: AI MAY ASSIST THE USER; AI MUST NOT BECOME THE SOURCE OF TRUTH. AI never writes to a canonical model; suggested text is applied only by copying into unsaved form state, then saved through the same unmodified save path a human edit uses. Management Insights are deterministic facts computed live from real records; AI only narrates the already-computed insights and has no query authority of any kind. Performance Evaluation AI content summarization, Report Card AI, Finance AI, RAG/vector search and autonomous agents are all out of scope for this phase |

See `MODULES.md` for the authoritative, per-module feature breakdown and status.

## 7. Functional Requirements Summary

Full per-module functional detail lives in `MODULES.md`. At the product level:

- The student/person database is the foundation; every other module references it, none duplicates it.
- One guardian can have multiple children; one student can have multiple guardians (one marked primary contact) — modeled as a pivot with relationship metadata, not duplicated guardian rows.
- Attendance is one session per class per day (not per subject-period) — a deliberate MVP scope decision.
- An invoice's balance and status are **always computed** from its items/discounts/payments, never stored as a mutable field, so they cannot drift out of sync.
- A teacher only sees/acts on the classes, students, and subjects they are actually assigned to teach — enforced in the Policy layer, not just hidden in the UI (verified: direct URL access to another class's data returns 403).
- Line items on an invoice lock once a payment exists against it; corrections happen via a new discount/payment entry, not by editing history.

## 8. Non-Functional Requirements

| Category | Requirement |
|---|---|
| Performance | Fast on 3G-equivalent mobile connections for attendance entry |
| Scalability | Comfortable at ~250 students / ~47 staff today; 10x headroom without redesign |
| Mobile | Mobile-first for attendance (large tap targets, default-to-present, no page reloads) |
| Auditability | Sensitive-table changes logged with actor, old/new values, timestamp |
| Localization | UI strings run through Laravel localization files (English populated today; Bahasa Indonesia not yet translated) |
| Data retention | Soft-delete by default; nothing that other tables reference is ever hard-deleted |

## 9. Database Principles

1. **One canonical record per real-world entity.** A student exists once; relationships to guardians, classes, invoices are link tables, never copies of person data.
2. **Login identity is separate from person identity.** `users` holds only auth data. `students` / `guardians` / `staff` optionally link to a `users` row via nullable `user_id`.
3. **History is additive, not overwritten.** Class enrollment, attendance sessions, and financial records are new rows per period; nothing gets silently rewritten.
4. **Computed, not cached.** Invoice balance/status are derived from related rows at read time.
5. **No table added before its phase needs it.** Don't build for a hypothetical future module.

## 10. Security Principles

- Deny-by-default authorization: every sensitive action has an explicit Policy check.
- Object-level scoping, not just menu-hiding: a teacher hitting another class's URL directly gets a real 403.
- CSRF protection (Laravel default, never disabled), mass-assignment protection via `#[Fillable]` on every model.
- Sessions: HTTP-only, same-site cookies; account status (`active`/`disabled`) checked at login.
- HTTPS required in production (not yet deployed to a production host).
- Passwords hashed via Laravel's default hasher; no password-reset flow exists yet (see Known Gaps in `PROJECT_STATUS.md`).

## 11. Development Phases (Versioning)

| Version | Phase | Status |
|---|---|---|
| V1.0 | Foundation | Complete |
| V1.1 | Brand identity applied to UI | Complete |
| V2.0 | Attendance | Complete |
| V3.0 | Finance | Complete |
| V4.0 | Academics | Complete |
| V4.1 | Sidebar navigation redesign | Complete |
| V4.2 | Staff position type-to-add + new default positions | Complete (current build) |
| V4.3 | Phase 5 Step 0 — effective-dated teaching assignments | Complete |
| V4.4 | Phase 5 Step 1 — academic-period canonicalisation | Complete |
| V4.5 | Phase 5 Step 2a-i — English programmes & proficiency levels | Complete |
| V4.6 | Phase 5 Step 2a-ii — teaching groups, membership, English placement | Complete |
| V4.8 | Phase 5 Step 2b — `class_subject` extended into the Teaching Assignment store (class OR teaching group) | Complete |
| V4.9 | Phase 5 Step 2c — unified roster accessors; teaching groups assessable through the existing assessment engine | Complete |
| V5.0-pre | Phase 5 Step 2d — report-card discovery across classes and teaching groups, merged by subject | Complete |
| V5.0-pre | Phase 5 Step 2e — Teacher Workspace (My Teaching Assignments) | Complete |
| V5.1 | Phase 5A — Curriculum registry + Learning Phase reference layer | Complete |
| V5.2 | Phase 5B — Curriculum Scopes + Learning Outcomes (CP) | Complete |
| V5.3 | Phase 5C — Learning Objectives (TP), many-to-many with CP | Complete |
| V5.4 | Phase 5D — Learning Pathways (ATP), ordered TP sequences | Complete (current build) |
| V5.0 | Academic & Teaching Administration (planning entities) | **Approved, not started** — the prerequisite steps above are built; Curriculum onward awaits explicit go-ahead |
| V6.0 | Reporting & Document Generation | **Phase 6A complete.** A LIVE report card is a view of current data; a PUBLISHED Academic Record (student + academic period) is a frozen snapshot taken at publish. Planning documents render canonically and store nothing. Print is browser-native — no PDF dependency, no stored files |
| V7.0 | Staff Performance Evaluation | **Phase V7A complete.** System evidence (teaching-activity facts) and human ratings are written by entirely different services and never read from one another. Finalization snapshots the record permanently; there is no correction path. Self-view of one's own finalized record is a policy carve-out, not a permission |
| V8.0 | Communication | **Phase V8A complete.** COMMUNICATION CONTENT, AUDIENCE, RECIPIENT, NOTIFICATION and EXTERNAL DELIVERY are five deliberately separate concerns, never collapsed into one generic messages table. PUBLISHING != EXTERNAL DELIVERY: V8A is honestly in-app only. Recipients are resolved from explicit Audience Rules and materialized once, atomically, at Publish; later membership/relationship/role/category changes never rewrite that history |
| V9.0 | AI-Assisted Management | **Phase V9A complete (V9A-1, V9A-2, V9A-3, V9A-4, V9A-5).** AI is optional assistance, never the source of truth. A provider-neutral abstraction (`AiProvider` interface, fake-first testing, zero new Composer dependency — the real adapter calls Anthropic over Laravel's `Http` facade) backs four user-facing features, all rate-limited (5/minute, 50/day, shared) and logged to one metadata-only `ai_generations` table (no prompt, response or cost persisted). Three draft-authoring assistants — Communication (title/body/mode/language only), Daily Journal reflection (two structured fields, never `actual_activity`), and Teaching Module planning (five structured fields, designed around an already-linked canonical TP the AI can never select/replace/remove/invent) — plus a fundamentally different fourth: **Management Insights**, in which AI has *no data-discovery role at all* — seven deterministic providers (`ManagementInsightRegistry`) compute facts live from real records against an explicit `ManagementInsightScope` DTO, and the AI narrative only paraphrases the already-computed insights it receives, never queries the database, never sees student/staff/guardian names, ratings, evidence content, financial amounts, or record IDs. Three-state reliability (`reliable`/`limited`/`unavailable`) is enforced structurally — when a fact is `unavailable`, its `count` is `null` and can never be a zero the AI might misread as "all clear." Performance Evaluation AI content summarization, Report Card AI, Finance AI, Curriculum Q&A, RAG/vector search and autonomous agents remain future, unapproved phases |

## 12. Future Roadmap (not committed, not designed in detail)

- **Academic & Teaching Administration (V5)** — Curriculum, Capaian Pembelajaran (CP), Tujuan Pembelajaran (TP), Alur Tujuan Pembelajaran (ATP), Program Tahunan (Prota), Program Semester (Prosem), Teaching Modules (Modul Ajar) and the Daily Teacher Journal (Jurnal Harian Guru). **All built (Phases 5A–5F).** The teaching-administration dashboards were not built and have no scope yet. *Correcting the original proposal:* it anchored everything to the existing `class_subject` teaching-assignment record. The truth turned out to be three-way. The **standards** layers (Curriculum → CP → TP → ATP) are anchored to a **curriculum scope + subject**, so one Phase C pathway serves Year 5 and Year 6 without duplication. The **planning** layers (Prota → Prosem) are anchored to the **roster**, so a plan survives a mid-year teacher handover. The **teacher's own work** (Module, Journal — and the existing Assessment) is anchored to the **assignment**, so authorship and teaching records stay with the person who made them. Each layer owns exactly one kind of fact: ATP the sequence, Prota the period and JP budget, Prosem the within-period schedule, Module the "how", Journal the "actual".
- **Teaching assignments for groups (V5 Step 2b)** — Steps 2a-i and 2a-ii built the whole non-class-based roster picture: which programmes and levels exist, which grades they cover, which students are grouped together, and what level each student has been assessed at. What is still missing is who *teaches* a group: `class_subject` has to become a generic teaching-assignment anchor first. Until it does, English groups are not assessable and teachers have no roster access. **Not implemented.**
- Document generation (V6) — structured Phase 5 data rendered into printable Prota/Prosem/ATP/Modul Ajar/Jurnal/Rapor documents. Explicitly deferred until Phase 5's data model exists and is stable.
- Parent portal (login scoped via `student_guardian`, read-only view of their own children's attendance/fees/grades). Communication (V8A) does not depend on this existing: Guardian/Student recipients materialize correctly today even with zero linked logins, ready for a future portal or WhatsApp/email adapter to make them reachable.
- Excel bulk import for Students and Staff — **built (Foundation P2, P2C/P2D)**: template download, full-file validation with row-level errors, preview-before-confirm, CREATE-only (rejects rows matching an existing record rather than overwriting). Staff import can optionally provision a login account per row. Student import deliberately excludes class enrollment — see `PROJECT_STATUS.md`'s Technical Debt. Guardian bulk import, and any export (backup/reporting) direction, remain unbuilt and unscoped. Design note for a future Guardian import: guardian relationships don't fit one flat row cleanly; likely solved with repeated `guardian_1_*`/`guardian_2_*` columns rather than a second linked sheet.
- Communication (V8): **Phase V8A complete** (announcements, targeted notices, in-app inbox). External delivery (email, WhatsApp), scheduled publishing, attachments, priority-driven escalation and two-way conversation all remain future, additive phases — the canonical Communication/Recipient split is the seam that lets them be added without redesigning what already ships.
- AI-Assisted Management (V9): **Phase V9A complete** (provider-neutral infrastructure, Communication Draft Assistant, Daily Journal Reflection Assistant, Teaching Module AI Planning Assistant, Deterministic Management Insights + AI Narrative). Performance Evaluation AI content summarization, Report Card AI, Finance AI, Curriculum Q&A, RAG/vector search, AI search, text-to-SQL and autonomous agents all remain future, additive phases requiring their own explicit approval — none is implied by V9A's infrastructure existing.
- Multi-school support (not blocked by current schema, not built).

---

## Update Rule

**Do not rewrite this file for every feature.** Only update it when a change materially affects product scope, core architecture, a major business rule, a major module, user roles, data architecture, or the phase roadmap. Small feature additions belong in `MODULES.md` and `CHANGELOG.md` only.
