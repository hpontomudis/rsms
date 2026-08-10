# RSMS Modules

This file tracks each module and its current, actually-implemented functionality. It is the first thing to check before starting new work, and the first thing to update after finishing it.

Status values: `Complete`, `In Progress`, `Planned`, `Not Started`.

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

## V5 — Communication

Status: Planned (not started)

Anticipated features (from the original PRD, not yet scoped in detail):
- Announcements (school-wide / grade / class scoped)
- Parent follow-up logs
- Notifications (in-app / email)

---

## V6 — AI-Assisted Management

Status: Planned (not started)

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
