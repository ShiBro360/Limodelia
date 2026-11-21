# Settings Domain Model

## 1. Purpose

The Settings module controls system configuration, reference data, permissions, categories, statuses, integrations, backup options, and UI preferences. It defines how the CRM behaves without requiring source code changes.

## 2. Business Rules

- Only authorized administrators can access settings.
- Critical settings changes should be logged.
- Reference values used by historical records should not be hard-deleted.
- Disabled values remain visible in historical records but unavailable for new records.
- Sensitive settings must not be displayed in plain text.
- Changes to salary, payment, and automation settings may affect calculations.
- System defaults should exist before core modules are used.

## 3. Fields

General setting fields:

- Setting ID.
- Setting key.
- Setting value.
- Setting group.
- Value type.
- Private flag.
- Updated by.
- Updated date.

Reference fields:

- Name.
- Slug.
- Active state.
- Sort order.
- Color token.
- Description.

Configurable areas:

- School profile.
- Payment methods.
- Payment categories.
- Expense categories.
- Student statuses.
- Lead sources.
- Lead statuses.
- Trip statuses.
- Salary rule types.
- Roles and permissions.
- Cloudinary settings.
- Backup settings.
- UI preferences.

## 4. Validation Rules

- Setting key is required and unique.
- Setting group is required.
- Value type must match stored value.
- Private values must be protected.
- Slugs are required for reference records and must be unique within their group.
- Disabled reference records cannot be selected for new records.
- Required system defaults cannot be disabled without replacement.
- Color values must use approved tokens or valid color format.

## 5. Relationships With Other Modules

- Students: statuses and payment plans are configured in settings.
- Payments: methods, categories, plans, and overdue rules are configured in settings.
- Expenses: categories and approval rules are configured in settings.
- Trips: statuses and default payment behavior are configured in settings.
- Marketing: sources and lead statuses are configured in settings.
- Salaries: rule types and approval settings are configured in settings.
- Reports: default periods and export options are configured in settings.
- Users: roles and permissions are configured in settings.
- Cloudinary: credentials and upload rules are configured in settings.

## 6. Automatic Calculations

- None directly for most settings.
- Settings influence automatic calculations in other modules.
- Overdue rules influence payment status.
- Salary settings influence salary calculations.
- Backup settings influence backup schedules.
- UI preference settings influence theme selection.

## 7. Possible Future Extensions

- Environment profiles.
- Multi-branch settings.
- Feature flags.
- Advanced permission matrix.
- Notification templates.
- Email provider settings.
- SMS provider settings.
- Online payment provider settings.
- Data retention settings.
- Localization settings.

## 8. Edge Cases

- Admin disables a status used by active records.
- Payment method is removed after payments exist.
- Private API key is accidentally displayed.
- Setting value has invalid type.
- Two admins edit settings at the same time.
- Required default category is missing.
- Permission change locks out all administrators.
- Theme setting breaks contrast requirements.

## 9. User Actions

- View allowed personal preferences.
- Change own theme when enabled.
- Update own notification preferences when enabled.
- View non-sensitive school settings when permitted.

## 10. Admin Actions

- Update school profile.
- Manage roles.
- Manage permissions.
- Manage users.
- Manage categories.
- Manage statuses.
- Configure payment plans.
- Configure Cloudinary.
- Configure backups.
- Configure automation rules.
- Review settings audit log.
