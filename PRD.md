# RAHAI SCHOOL MANAGEMENT SYSTEM (RSMS) — Master PRD

| | |
|---|---|
| School | Rahai School |
| Organization | Yayasan Pendidikan Halmahera Membangun Bangsa |
| Location | North Halmahera, Indonesia |
| Document type | Living master blueprint — update only for material changes (see rule at bottom) |
| Last updated | 2026-08-10 (V4.2) |

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
- **Audit logging:** an `Auditable` trait (`app/Models/Concerns/Auditable.php`) writes to `audit_logs` on create/update/delete. Currently applied to: `Student`, `Guardian`, `Staff`, `Attendance`, `Invoice`, `Payment`, `Discount`, `Assessment`.
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
| Communication | V5 (not started) | Planned |
| AI-assisted reporting | V6 (not started) | Planned |

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
| V4.2 | Staff position type-to-add + new default positions | Complete (current) |
| V5.0 | Communication | Not started |
| V6.0 | AI-assisted management | Not started |

## 12. Future Roadmap (not committed, not designed in detail)

- Parent portal (login scoped via `student_guardian`, read-only view of their own children's attendance/fees/grades).
- Excel/CSV bulk import + export for Students (and later Guardians/Staff) — requested, not yet scoped or built. Design note: guardian relationships don't fit one flat row cleanly; likely solved with repeated `guardian_1_*`/`guardian_2_*` columns rather than a second linked sheet.
- Communication module (announcements, follow-up logs, notifications).
- AI-assisted natural-language reporting (read-only, query-grounded — never a free-form number generator).
- Multi-school support (not blocked by current schema, not built).

---

## Update Rule

**Do not rewrite this file for every feature.** Only update it when a change materially affects product scope, core architecture, a major business rule, a major module, user roles, data architecture, or the phase roadmap. Small feature additions belong in `MODULES.md` and `CHANGELOG.md` only.
