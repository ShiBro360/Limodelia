# Development Roadmap

This roadmap breaks Limodelia CRM development into practical phases. Each phase should leave the project in a usable and testable state.

## Phase 1: Authentication

Objective:

Create secure login, logout, session handling, password hashing, and access guards.

Files:

- `login.php`
- `logout.php`
- `includes/bootstrap.php`
- `includes/auth.php`
- `config/db.php`
- `pages/settings.php`

Dependencies:

- Users table.
- Roles table.
- Password hashing strategy.
- Session configuration.

Expected result:

Staff can log in, log out, and access protected pages based on authentication state.

## Phase 2: Base Layout and Navigation

Objective:

Finalize the shared application shell, responsive sidebar, topbar, footer, and reusable UI patterns.

Files:

- `layouts/header.php`
- `layouts/sidebar.php`
- `layouts/topbar.php`
- `layouts/footer.php`
- `includes/page.php`
- `assets/css/app.css`
- `assets/js/app.js`

Dependencies:

- Approved UI guide.
- Route naming convention.
- User session context.

Expected result:

All pages use one consistent layout with stable navigation and responsive behavior.

## Phase 3: Dashboard

Objective:

Create the first operational dashboard with key metrics and recent activity.

Files:

- `pages/dashboard.php`
- `includes/dashboard.php`
- `api/dashboard-metrics.php`
- `assets/js/dashboard.js`

Dependencies:

- Students table.
- Payments table.
- Expenses table.
- Trips table.
- Activity logs table.

Expected result:

Administrators can see active students, revenue, overdue payments, expenses, upcoming trips, and recent activity.

## Phase 4: Students

Objective:

Build student list, student profile, create/edit forms, statuses, notes, and group assignments.

Files:

- `pages/students.php`
- `includes/students.php`
- `api/students.php`
- `assets/js/students.js`

Dependencies:

- Students table.
- Student statuses table.
- Student notes table.
- Groups table.
- Student group memberships table.

Expected result:

Staff can manage student records and view a connected student profile.

## Phase 5: Payments

Objective:

Implement payment plans, expected payments, actual received payments, statuses, and payment history.

Files:

- `pages/payments.php`
- `includes/payments.php`
- `api/payments.php`
- `assets/js/payments.js`

Dependencies:

- Students module.
- Payment plans table.
- Payment categories table.
- Payment methods table.
- Expected payments table.
- Payments table.

Expected result:

Finance staff can create expected charges, record payments, track overdue balances, and review payment history.

## Phase 6: Expenses

Objective:

Implement expense recording, categories, receipts, review statuses, and approval workflow.

Files:

- `pages/expenses.php`
- `includes/expenses.php`
- `api/expenses.php`
- `assets/js/expenses.js`
- `uploads/temp`

Dependencies:

- Users module.
- Expense categories table.
- Payment methods table.
- Expenses table.
- Files table.

Expected result:

Finance staff can record, review, approve, and report school expenses.

## Phase 7: Marketing

Objective:

Build lead tracking, source management, lead statuses, assignment, and conversion to student.

Files:

- `pages/marketing.php`
- `includes/marketing.php`
- `api/leads.php`
- `assets/js/marketing.js`

Dependencies:

- Users table.
- Marketing sources table.
- Leads table.
- Students module.

Expected result:

Marketing staff can manage inquiries, track lead sources, and convert qualified leads into students.

## Phase 8: Trips

Objective:

Create trip management with participants, trip pricing, payment status, expenses, and media links.

Files:

- `pages/trips.php`
- `includes/trips.php`
- `api/trips.php`
- `assets/js/trips.js`
- `uploads/trips`

Dependencies:

- Students module.
- Payments module.
- Expenses module.
- Trips table.
- Trip participants table.
- Files table.

Expected result:

Staff can plan trips, manage participants, track payments and expenses, and attach trip media.

## Phase 9: Salaries

Objective:

Implement salary rules, period calculations, salary line items, approval, and paid status.

Files:

- `pages/salaries.php`
- `includes/salaries.php`
- `api/salaries.php`
- `assets/js/salaries.js`

Dependencies:

- Users table.
- Roles table.
- Lessons table.
- Attendance records table.
- Payments table.
- Salary rules table.
- Salary periods table.
- Salary items table.

Expected result:

Finance staff can calculate, review, approve, and mark staff salaries as paid.

## Phase 10: Photo Gallery and Cloudinary

Objective:

Add photo upload, albums, student photos, trip photos, and Cloudinary-backed storage.

Files:

- `includes/uploads.php`
- `includes/cloudinary.php`
- `api/uploads.php`
- `api/gallery.php`
- `assets/js/gallery.js`
- `uploads/temp`

Dependencies:

- Files table.
- Cloudinary assets table.
- Albums table.
- Album photos table.
- Student photos table.
- Trip photos table.
- Cloudinary account configuration.

Expected result:

Staff can upload, organize, and view student and trip photos through a structured media workflow.

## Phase 11: Reports

Objective:

Create financial, student, marketing, salary, and trip reports with filters and export-ready structure.

Files:

- `pages/reports.php`
- `includes/reports.php`
- `api/reports.php`
- `assets/js/reports.js`

Dependencies:

- Students module.
- Payments module.
- Expenses module.
- Salaries module.
- Trips module.
- Marketing module.

Expected result:

Owners and managers can review reliable operational and financial summaries.

## Phase 12: Settings

Objective:

Create administrative settings for users, roles, categories, statuses, payment methods, Cloudinary, and backups.

Files:

- `pages/settings.php`
- `includes/settings.php`
- `api/settings.php`
- `assets/js/settings.js`

Dependencies:

- Authentication.
- Roles and permissions.
- Settings table.
- Category and status tables.

Expected result:

Authorized administrators can configure the CRM without editing source files.

## Phase 13: Automatic Processes

Objective:

Add controlled automation for monthly payments, recurring expenses, salary calculations, reminders, and backups.

Files:

- `includes/automation.php`
- `includes/backups.php`
- `api/automation.php`
- `database`

Dependencies:

- Payments module.
- Expenses module.
- Salaries module.
- Settings module.
- Backup jobs table.

Expected result:

Routine operational records can be generated consistently with logs and administrator review.

## Phase 14: Security Hardening

Objective:

Strengthen authorization, CSRF protection, audit logging, validation, upload safety, and error handling.

Files:

- `includes/auth.php`
- `includes/csrf.php`
- `includes/validation.php`
- `includes/security.php`
- `includes/uploads.php`
- `config`

Dependencies:

- Authentication.
- Roles and permissions.
- Audit logs table.
- File upload system.

Expected result:

The CRM is ready for controlled production use on a secured XAMPP environment.

## Phase 15: Backup and Release Preparation

Objective:

Prepare backup procedures, restore documentation, deployment checklist, and first internal release.

Files:

- `docs/PROJECT.md`
- `docs/DATABASE.md`
- `docs/CHANGELOG.md`
- `docs/ROADMAP.md`
- `includes/backups.php`
- `database`

Dependencies:

- Core modules.
- Backup jobs table.
- Tested database export process.

Expected result:

The project has a documented release baseline, backup workflow, and restore plan.
