# Limodelia CRM Project

## Project Overview

Limodelia CRM is a custom web-based management system for a modeling school. The project is designed to centralize student management, payments, expenses, salaries, trips, marketing activity, reports, media assets, and administrative settings in one internal application.

The system is intended for daily operational use by school administrators, managers, teachers, finance staff, and owners. It should remain lightweight, transparent, and maintainable without relying on large PHP frameworks or JavaScript frontend frameworks.

## Goals

- Provide one reliable internal workspace for school operations.
- Keep student, payment, salary, and expense data connected.
- Reduce manual work through scheduled calculations and reminders.
- Support future media workflows for trips, events, and student portfolios.
- Make financial reporting clear for owners and managers.
- Keep the codebase simple enough for long-term maintenance on XAMPP.
- Build a modular architecture that can grow without rewriting the core project.

## Technology Stack

- PHP 8.2+
- MySQL
- PDO for database access
- HTML5
- CSS3
- Vanilla JavaScript
- XAMPP with Apache and MySQL
- UTF8MB4 database encoding

The project explicitly avoids Laravel, Symfony, React, Vue, and other large application frameworks.

## Folder Structure

```text
/config
  db.php

/database
  Database planning files, migrations, and future seed documentation.

/includes
  Shared bootstrap, helpers, layout orchestration, authentication guards, and reusable PHP utilities.

/layouts
  Shared visual structure: header, sidebar, topbar, footer, and future layout components.

/pages
  User-facing application pages.

/api
  Future JSON endpoints for asynchronous actions.

/assets
  /css
  /js
  /icons

/uploads
  /trips
  /temp

/docs
  Long-term project documentation.
```

## Architecture

The application follows a modular procedural PHP architecture. Shared configuration and bootstrapping are loaded once, then each page delegates page structure to common layout components.

Core architectural principles:

- One entry point per page.
- Shared layout components for consistent interface.
- Centralized database connection through PDO.
- Shared helper functions in `includes`.
- Business logic separated from layout output as the project grows.
- Future API endpoints kept in `api` for AJAX-based operations.
- Uploaded files kept outside business logic folders.

The architecture should evolve toward clear layers:

- Configuration layer: database, environment settings, constants.
- Infrastructure layer: PDO connection, file upload helpers, Cloudinary client wrapper.
- Domain layer: students, payments, expenses, salaries, trips, reports.
- Presentation layer: pages and layout components.
- API layer: small JSON endpoints for dynamic UI actions.

## Modules

The CRM is planned around these modules:

- Dashboard
- Students
- Payments
- Expenses
- Marketing
- Trips
- Salaries
- Reports
- Settings
- Photo gallery
- User management
- Automatic processes
- Cloudinary media integration

Each module should have its own page, future service functions, validation rules, and database tables.

## Navigation

Primary navigation is handled by the sidebar. It should remain visible on desktop and collapsible on mobile.

Main navigation items:

- Dashboard
- Students
- Payments
- Expenses
- Marketing
- Trips
- Salaries
- Reports
- Settings

The topbar contains the current page title, user context, and quick account actions. Future versions may add global search, notifications, and quick-create actions.

## User Roles

Planned roles:

- Owner: full access to all financial, operational, and configuration data.
- Administrator: manages students, payments, trips, schedules, and general operations.
- Finance manager: manages payments, expenses, salaries, and financial reports.
- Teacher: views assigned students, groups, attendance, and salary summaries.
- Marketing manager: manages leads, campaigns, sources, and conversion tracking.
- Viewer: read-only access to selected dashboards and reports.

Role permissions should be stored in the database and enforced on every page and API endpoint.

## Automatic Processes

Future automatic processes:

- Monthly tuition payment generation.
- Overdue payment detection.
- Payment reminder queue creation.
- Salary calculation from lessons, groups, bonuses, and deductions.
- Expense recurring entry generation.
- Trip payment status updates.
- Student status updates based on inactivity.
- Backup scheduling.
- Report snapshots for monthly financial summaries.

Automation can start as admin-triggered actions and later move to scheduled tasks using Windows Task Scheduler or cron-compatible tooling.

## Salary System

The salary system should support multiple compensation models:

- Fixed monthly salary.
- Payment per lesson.
- Payment per student.
- Percentage of received student payments.
- Bonuses.
- Deductions.
- Trip-related compensation.

Salary records should be calculated for a defined period, reviewed by finance staff, then marked as approved and paid. The system must preserve historical salary calculations even if teacher rates change later.

## Payment Workflow

Payment workflow:

1. Student or guardian is assigned a tuition plan or service.
2. The system creates expected payment records.
3. Staff records actual payments with date, amount, method, and comment.
4. The payment status changes to paid, partial, overdue, cancelled, or refunded.
5. Reports and dashboard metrics update from payment records.

Supported payment categories should include tuition, registration fees, trips, workshops, portfolio sessions, and other custom services.

## Expense Workflow

Expense workflow:

1. Staff creates an expense with category, amount, date, payment method, and description.
2. Optional receipt or document is attached.
3. Finance staff reviews and approves the expense.
4. Approved expenses appear in reports.
5. Recurring expenses can generate future expected expense records.

Expense categories should remain configurable from settings.

## Student Management

Student management should include:

- Student profile.
- Guardian or parent contacts.
- Age, birth date, and basic personal details.
- Enrollment date.
- Status tracking.
- Groups and classes.
- Payment plan.
- Notes.
- Documents.
- Photo gallery.
- Trip participation.
- Attendance history.

The student profile should become the central operational view that connects education, finance, trips, and media history.

## Trips

Trips represent school events such as modeling camps, photo sessions, competitions, workshops, and external activities.

Trip records should include:

- Name.
- Location.
- Date range.
- Capacity.
- Price.
- Internal cost.
- Student participants.
- Payment tracking.
- Expense tracking.
- Uploaded files and photos.
- Status.

Trips should connect to payments, expenses, students, and the photo gallery.

## Photo Gallery

The photo gallery should organize images by student, trip, event, and album.

Expected features:

- Upload photos.
- Store metadata.
- Assign photos to students.
- Assign photos to trips.
- Mark portfolio-ready images.
- Filter by date, student, album, or trip.
- Support local temporary uploads before cloud storage.

The local `uploads/temp` folder can be used for upload staging. The local `uploads/trips` folder can be used during early development or as a fallback.

## Cloudinary Integration Concept

Cloudinary should be used as the long-term media storage and transformation provider.

Planned concept:

- Upload files temporarily to `uploads/temp`.
- Validate file type, size, and dimensions.
- Send approved files to Cloudinary.
- Store Cloudinary public ID, secure URL, width, height, format, and resource type in the database.
- Use folders or tags for students, trips, albums, and school years.
- Generate thumbnails through Cloudinary transformations.
- Keep local files only as temporary staging files unless configured otherwise.

Cloudinary credentials must not be committed to the repository. They should be stored in a local configuration file or environment-specific settings.

## Reports

Reports should provide operational and financial visibility.

Planned reports:

- Monthly revenue.
- Outstanding payments.
- Payment method summary.
- Expense summary.
- Profit and loss.
- Salary summary.
- Student enrollment trends.
- Marketing lead conversion.
- Trip profitability.
- Teacher activity.

Reports should support date filters, export options, and role-based visibility.

## Dashboard

The dashboard should provide high-signal operational metrics:

- Total active students.
- Monthly revenue.
- Outstanding payments.
- Current month expenses.
- Upcoming trips.
- New leads.
- Pending salary approvals.
- Recent activity.

Dashboard data should be calculated from database records, not manually entered.

## Settings

Settings should manage:

- School profile.
- User accounts.
- Roles and permissions.
- Payment methods.
- Payment categories.
- Expense categories.
- Salary rules.
- Student statuses.
- Trip statuses.
- Cloudinary settings.
- Backup settings.
- UI preferences.

Settings must be protected by permissions because they affect the behavior of the whole system.

## Security

Security requirements:

- Password hashing using PHP password APIs.
- Session-based authentication.
- Role-based access control.
- CSRF protection for forms.
- Server-side validation for all input.
- Prepared statements through PDO.
- Escaped output in HTML.
- File upload validation.
- Restricted upload file types.
- Private configuration for credentials.
- Audit log for important actions.

The system should never trust client-side validation as the only validation layer.

## Backup Strategy

Backup strategy:

- Regular MySQL database backups.
- Separate backup of uploaded local files.
- Export of Cloudinary asset metadata.
- Manual backup action for administrators.
- Scheduled backup process for production use.
- Backup retention policy.
- Restore documentation.

Backups should be tested periodically. A backup is only useful if it can be restored reliably.

## Future Modules

Possible future modules:

- Attendance tracking.
- Class schedule.
- Teacher calendar.
- Contracts and document generation.
- Email and SMS notifications.
- Lead pipeline.
- Parent portal.
- Student portal.
- Online payment integration.
- Certificate generation.
- Inventory management.
- Advanced analytics.
- Multi-branch support.
