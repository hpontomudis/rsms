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
- Assessments: name, `term` (free-text, e.g. "Term 1" — not a separate Terms table), max score, date, scoped to a class-subject
- Score recording: roster with numeric score inputs per student, validated against the assessment's max score
- A teacher can only create assessments / record scores for class-subjects they are assigned to (object-level policy check)
- A class-subject with existing assessments cannot be deleted (`restrictOnDelete` at the DB level, not just app-level)
- Report Card: per student, per academic year, grouped by subject, averaged by term (hardcoded terms: Term 1/2/3) with an overall average
- Assessments list/create/show screens; Subjects list/create/edit screens

Known simplification: `term` is a free-text string on `assessments`, and the report card hardcodes `['Term 1', 'Term 2', 'Term 3']` rather than reading from a database-driven term list. This was a deliberate "avoid unnecessary complexity" call per the PRD (no separate Terms table), but it means a school using different term names would need a code change, not a data change.

---

## V5 — Academic & Teaching Administration

Status: **Proposed** — full architecture report and database design produced and reviewed; **no migrations, models, or UI exist yet**. Do not treat anything below as built.

Vision: connect the full teaching cycle — Curriculum → CP → TP → ATP → Prota → Prosem → Teaching Modules → Daily Teacher Journal → (existing) Assessment → (existing) Report Card — as structured data, anchored to the existing `class_subject` "teaching assignment" record so teacher/subject/class/grade/academic-year never need re-entering.

Proposed features:
- **Curriculum:** name/code/description, `status` (draft/active/archived) — admin-managed, framework-level, not tied to one academic year
- **Capaian Pembelajaran (CP):** linked to curriculum + subject + optional grade; admin-managed
- **Tujuan Pembelajaran (TP):** linked to a CP, ordered via a `sequence` field; admin-managed
- **Alur Tujuan Pembelajaran (ATP):** an ordered selection of TPs for a specific teaching assignment (`class_subject`), via header + `atp_items`; teacher-managed, scoped to their own assignment
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

- Grouped sidebar layout (People / Academics / Finance sections + icons), permission-driven group visibility
- Mobile slide-in drawer
- User menu with logout
