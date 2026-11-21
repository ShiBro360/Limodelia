# Reports Module Specification

## Purpose

The Reports module generates financial and operational reports from existing CRM data. It does not create PDF or Excel files yet; export buttons are visible disabled placeholders for future implementation.

## Architecture

The module follows MVC Lite:

- `pages/reports.php` renders filters, summary cards and report tables.
- `app/Services/ReportService.php` validates filters, coordinates report generation and stores report metadata.
- `app/Repositories/ReportRepository.php` contains all SQL queries.
- `app/Validators/ReportValidator.php` validates report type and date ranges.

There is no SQL inside `pages/reports.php`.

## Supported Report Types

- Financial summary
- Payments report
- Expenses report
- Salaries report
- Trips report
- Students report

## Data Sources

- Payments: `payments`, `students`, `subscriptions`, `payment_methods`
- Expenses: `expenses`, `expense_categories`, `payment_methods`
- Salaries: `salary_payouts`, `salary_rules`, `users`, linked `expenses`
- Trips: `trips`, `trip_participants`, `trip_photos`, linked `expenses`
- Students: `students`, `student_groups`, `student_subscriptions`, `subscriptions`
- Generated metadata: `reports`

## Calculation Rules

Financial summary:

- Total income uses completed payments only.
- Total expenses exclude cancelled and rejected expenses.
- Net profit equals income minus expenses.
- Payment count excludes cancelled payments.
- Salary costs use salary-linked expenses.
- Trip costs use expenses with `trip_id`.
- Marketing costs use the marketing category or Instagram-related descriptions.

Payments report:

- Totals include completed payments only.
- Cancelled payments are excluded from totals.
- Cancelled payments remain visible in the detailed table.

Expenses report:

- Totals exclude cancelled and rejected expenses.
- Cancelled and rejected expenses remain visible in the detailed table.
- Source is derived from salary, trip, marketing campaign/category, or manual expense context.

Salaries report:

- Shows payout period, employee, rule, amount, status and linked expense presence.

Trips report:

- Shows trip count, participants count, trip costs, photos count and linked trip expenses.

Students report:

- Shows active students, archived students, students by group and students by subscription.

## Generated Reports

When the administrator clicks `Generuj raport`, the system stores metadata in the `reports` table:

- report type
- period start
- period end
- generated user
- generated timestamp
- filters JSON
- nullable file URL

No PDF or Excel file is generated yet.

## Export Placeholders

`Eksport PDF` and `Eksport Excel` are disabled placeholders. Future implementation should use `StorageService::exportsFolder()` to store generated files and then update `reports.file_url`.

## Security

- All SQL is inside `ReportRepository`.
- Prepared statements are used for date ranges and stored metadata.
- Report type is validated against an allowlist.
- Date range must be valid and ordered.

## Responsive Behavior

Summary cards wrap using the existing card grid. Report controls collapse into a single-column layout on mobile. Detailed tables use the shared responsive table wrapper.
