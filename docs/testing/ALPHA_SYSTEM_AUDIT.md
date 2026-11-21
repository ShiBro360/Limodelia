# Limodelia CRM Alpha System Audit

Audit date: 2026-06-29  
Environment: XAMPP, PHP 8, MySQL/MariaDB, local domain `limodelia.local`

## Scope

This audit covered the alpha CRM modules:

- Dashboard
- Students
- Payments
- Expenses
- Salaries
- Trips
- Cloudinary
- Reports
- Marketing
- Settings

No new features were added and the UI was not redesigned.

## HTTP Regression

All checked pages returned HTTP 200:

| Page | Result |
| --- | --- |
| `dashboard.php` | PASS |
| `students.php` | PASS |
| `student_profile.php?id=1` | PASS |
| `payments.php` | PASS |
| `expenses.php` | PASS |
| `salaries.php` | PASS |
| `trips.php` | PASS |
| `reports.php` | PASS |
| `marketing.php` | PASS |
| `settings.php` | PASS |

## PHP Lint

PHP lint was executed for all project PHP files.

Result: PASS

No syntax errors were detected in:

- root PHP files
- `app/Helpers`
- `app/Repositories`
- `app/Services`
- `app/Validators`
- `config`
- `includes`
- `layouts`
- `pages`

## Architecture Audit

| Check | Result | Notes |
| --- | --- | --- |
| No SQL inside `pages/*.php` | PASS | Search found no SQL keywords in pages. |
| Pages use Services | PASS | Core module pages instantiate services and delegate business logic. |
| Services use Repositories | PASS | Database logic is isolated in repositories. |
| No hardcoded storage paths outside `StorageService` | PASS | Trip uploads use `StorageService`; storage paths are centralized. |
| Cloudinary credentials are not hardcoded | PASS | Credentials are loaded through `SettingsService`. |
| API Secret display | PASS | Cloudinary API secret is masked in Settings. |

## Database Consistency

| Check | Result | Notes |
| --- | --- | --- |
| `database.sql` matches current local schema | PASS | Column comparison between current DB and schema-built temp DB returned 0 differences. |
| `seed.sql` imports successfully | PASS | Imported into temporary database. |
| Tables created from schema | PASS | Temporary DB contained 23 tables. |
| `marketing_campaigns` fields synced | PASS | `leads_count` and `new_students_count` exist. |
| `trip_participants` exists | PASS | Table and required columns exist. |
| No orphaned `trip_photos` | PASS | Count: 0. |
| No orphaned trip participants | PASS | Trip and student references valid. |
| No orphaned salary linked expenses | PASS | Count: 0. |
| No orphaned trip linked expenses | PASS | Count: 0. |
| No orphaned marketing linked expenses | PASS | Count: 0. |

## Business Flow Tests

Temporary records were created through the same services used by the CRM and then removed.

| Flow | Result | Notes |
| --- | --- | --- |
| Create student | PASS | Student and active subscription created. |
| Add payment | PASS | Payment saved and Dashboard revenue changed. |
| Add expense | PASS | Expense saved with valid approval metadata. |
| Add teacher salary payout | PASS | Payout and linked expense created. |
| Add accountant salary payout | PASS | Payout and linked expense created. |
| Add trip with participants | PASS | Participant stored through `trip_participants`. |
| Add trip expense | PASS | Linked expense created. |
| Add marketing campaign | PASS | Linked marketing expense created. |
| Generate report | PASS | Report metadata stored. |
| Dashboard updates | PASS | Revenue and expenses changed after test records. |

## Cloudinary

Cloudinary credentials were configured, so a real upload/delete test was performed.

| Check | Result | Notes |
| --- | --- | --- |
| Connection test | PASS | Cloudinary API responded successfully. |
| Upload test image | PASS | Image uploaded through `pages/trips.php` multipart flow. |
| Metadata stored | PASS | `trip_photos` received `public_id`, `secure_url`, dimensions and folder metadata. |
| Delete image | PASS | Delete action removed the Cloudinary asset through `TripService`. |
| DB cleanup | PASS | `trip_photos` count for test trip returned 0 after delete. |

## Settings

Settings were tested through `SettingsService` with rollback where possible.

| Check | Result |
| --- | --- |
| School settings save | PASS |
| Subscription update | PASS |
| Group update | PASS |
| Salary rule update | PASS |
| Cloudinary settings save | PASS |
| Cloudinary test | PASS |
| Theme setting save | PASS |

## Documentation

All required specs exist:

| Document | Result |
| --- | --- |
| `docs/design/DASHBOARD_SPEC.md` | PASS |
| `docs/design/DASHBOARD_LIVE_SPEC.md` | PASS |
| `docs/design/STUDENT_PROFILE_SPEC.md` | PASS |
| `docs/design/PAYMENTS_SPEC.md` | PASS |
| `docs/design/EXPENSES_SPEC.md` | PASS |
| `docs/design/SALARIES_SPEC.md` | PASS |
| `docs/design/TRIPS_SPEC.md` | PASS |
| `docs/design/REPORTS_SPEC.md` | PASS |
| `docs/design/MARKETING_SPEC.md` | PASS |
| `docs/design/SETTINGS_SPEC.md` | PASS |
| `docs/architecture/STORAGE_ARCHITECTURE.md` | PASS |
| `docs/architecture/MVC_LITE.md` | PASS |

## Fixed Issues

### Expense approval metadata

Problem: `ExpenseRepository` created or updated expenses with status `paid` or `approved` without `approved_by` and `approved_at`. The database CHECK constraint correctly rejected those rows.

Fix: `ExpenseRepository` now binds `approved_by = 1` and `approved_at = current timestamp` when status is `paid` or `approved`.

### Accountant default salary

Problem: the default accountant salary rule was seeded as `0.00`, which made paid accountant payout creation fail because salary expenses require a positive amount.

Fix: `database/seed.sql` now seeds accountant fixed salary as `1000.00`. The local active accountant rule was updated to the same working default.

## Remaining Risks

- The application is still alpha and does not yet have authentication/authorization enforcement around module actions.
- Export buttons for CSV/PDF/Excel are placeholders.
- Some Polish UI strings in older files may still contain encoding artifacts and should be cleaned in a dedicated language pass.
- Cloudinary upload works, but production limits, folder retention and large file behavior still need operational testing.
- Automated browser tests are not yet part of the project.

## Recommended Next Steps

1. Add authentication and role-based authorization before production use.
2. Add automated integration tests for Services and Repositories.
3. Add browser smoke tests for the main POST/Redirect/GET flows.
4. Clean remaining text encoding artifacts across older modules.
5. Implement export generation for reports.
6. Add audit logging for create/update/cancel actions.

## Alpha Stability Verdict

Alpha status: STABLE FOR LOCAL CONTINUED DEVELOPMENT.

The core CRM modules are internally consistent after the fixes above. The project is not production-ready until authentication, authorization, audit logging and export workflows are completed.
