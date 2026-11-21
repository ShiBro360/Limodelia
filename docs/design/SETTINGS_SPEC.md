# Settings Module Specification

## Purpose

The Settings module is the central control panel for Limodelia CRM. It manages school data, subscriptions, student groups, salary rules, external services, default appearance and read-only system information.

## Architecture

The module follows MVC Lite:

- `pages/settings.php` renders the UI and handles POST/Redirect/GET.
- `SettingsService` validates and coordinates business logic.
- `SettingsRepository` performs database reads and writes with prepared statements.
- `CloudinaryService` is used only for testing Cloudinary connectivity.

No SQL is written inside `pages/settings.php`.

## Sections

The page contains tabbed sections:

- `Dane szkoły`
- `Abonamenty`
- `Grupy`
- `Wynagrodzenia`
- `Usługi zewnętrzne`
- `Wygląd`
- `System`

Tabs use the existing vanilla JS tab behavior and reusable `.tabs`, `.tab` and settings-specific panel classes.

## Dane Szkoły

Editable fields:

- School name.
- Phone.
- Instagram.
- Logo URL.
- NIP.
- REGON.

Validation rules:

- School name is required.
- Logo URL must be a valid URL when present.
- NIP must contain 10 digits when present.
- REGON must contain 9 or 14 digits when present.
- Phone is normalized to groups of three digits.
- Instagram is stored without `@`.

Values are stored in the `settings` table under the `school` group.

## Abonamenty

Existing subscriptions are edited in place. The module supports:

- Name.
- Lessons count.
- Price.
- Status.

Supported statuses:

- `active`
- `inactive`
- `archived`

Subscriptions are never permanently deleted from Settings. Archiving is used to remove a plan from active use while preserving historical payments and student subscription links.

## Grupy

Existing student groups are edited in place. The module supports:

- Name.
- Max students.
- Color.
- Status.
- Visibility.

Supported statuses:

- `active`
- `archived`

Visibility controls whether a group should appear in normal selection flows. Archiving preserves existing student relationships.

## Wynagrodzenia

Salary rules for the CRM payroll model are editable:

- Accountant fixed salary.
- Teacher net-profit percentage rule.

Supported rule types:

- `fixed`
- `percentage_net_profit`

Validation rules:

- Rule name is required.
- Fixed amount cannot be negative.
- Percentage must be between 0 and 100.
- Status must be active, inactive or archived.

The Payroll Center uses these rules for payout calculations.

## Usługi Zewnętrzne

Cloudinary settings are managed in this section:

- Enabled.
- Cloud Name.
- API Key.
- API Secret.
- Root Folder.

Security rules:

- API Secret is never displayed in plain text after saving.
- The secret field displays a masked placeholder when a secret exists.
- Leaving the secret field empty preserves the current secret.
- Replacing the secret is possible by entering a new value.

The `Test Cloudinary` button saves the posted configuration and then calls `CloudinaryService::testConnection()`. The UI displays either `Connected` or `Connection failed`.

## Wygląd

The appearance section stores the default system theme:

- `light`
- `dark`

The setting is stored as `ui.default_theme`. Full visual dark-theme rollout remains controlled by the existing theme system.

## System

The system section is read-only and displays:

- App version.
- Database name.
- PHP version.
- Current timezone.

These values are informational and are not editable from the UI.

## Reusable Components

The implementation uses existing components:

- Cards.
- Tabs.
- Inputs.
- Selects.
- Buttons.
- Badges.
- Alerts.

Additional settings-specific CSS classes:

- `.settings-shell`
- `.settings-tabs`
- `.settings-tab-panel`
- `.settings-school-grid`
- `.settings-logo-preview`
- `.settings-list`
- `.settings-row-form`
- `.settings-row-form-wide`
- `.settings-row-form-salary`
- `.settings-theme-grid`
- `.settings-theme-option`
- `.settings-system-grid`

## Security

- All database updates use prepared statements.
- `pages/settings.php` contains no SQL.
- POST/Redirect/GET prevents duplicate submissions.
- Private Cloudinary values are stored with `is_private = 1`.
- API Secret is masked in the UI.

## Future Improvements

- Role-based access control for each settings section.
- Audit log entries for every settings change.
- Dedicated add/archive modals for subscriptions and groups.
- Per-user theme preferences.
- Cloudinary diagnostics with detailed quota and folder checks.
