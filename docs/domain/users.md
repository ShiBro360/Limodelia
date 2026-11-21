# Users Domain Model

## 1. Purpose

The Users module manages staff accounts, authentication identity, roles, permissions, account status, and user activity. It controls who can access each part of the CRM.

## 2. Business Rules

- Every user must have a unique login identity.
- Passwords must be stored only as hashes.
- Users must have at least one role or a direct permission strategy defined.
- Inactive users cannot log in.
- Suspended users cannot access protected pages.
- Owner or administrator access must always remain available.
- Sensitive actions should be logged.
- User deletion should be restricted when historical records reference the user.
- Role changes should take effect on the next permission check.

## 3. Fields

User fields:

- User ID.
- Name.
- Email.
- Password hash.
- Phone.
- Role.
- Status.
- Last login date.
- Created date.
- Updated date.

Role fields:

- Role ID.
- Name.
- Slug.
- Description.

Permission fields:

- Permission ID.
- Name.
- Slug.
- Module.
- Description.

## 4. Validation Rules

- Name is required.
- Email is required and must be unique.
- Email must use valid email format.
- Password must meet configured strength rules before hashing.
- Role must exist.
- Status must be valid.
- User cannot remove the last active owner or administrator.
- Suspended users cannot be assigned new operational tasks.
- Phone must use accepted phone format when provided.

## 5. Relationships With Other Modules

- Students: users create notes and update profiles.
- Payments: users record payments.
- Expenses: users create and approve expenses.
- Trips: users manage trips and participant lists.
- Marketing: users are assigned leads.
- Salaries: users may receive salary calculations.
- Reports: users need permissions to view reports.
- Settings: users manage configuration when permitted.
- Cloudinary: users upload media.

## 6. Automatic Calculations

- Last login timestamp.
- User activity count.
- Assigned lead count.
- Created payment total.
- Approved expense total.
- Salary amount for staff users.
- Permission set resolved from role.

## 7. Possible Future Extensions

- Two-factor authentication.
- Password reset flow.
- User avatars.
- Direct user permissions.
- Teams or departments.
- Login history.
- Device sessions.
- Staff profile pages.
- Teacher availability.
- User notification preferences.

## 8. Edge Cases

- User forgets password.
- User email changes.
- User is responsible for historical records but leaves the school.
- Last administrator is accidentally disabled.
- Role loses permission while user is active.
- Session remains active after suspension.
- Duplicate user account is created.
- User attempts to access a deleted record.

## 9. User Actions

- Log in.
- Log out.
- View own profile.
- Change password when enabled.
- Update own contact details when enabled.
- View permitted pages.
- Perform actions allowed by role.

## 10. Admin Actions

- Create user.
- Edit user.
- Disable user.
- Suspend user.
- Reset password.
- Assign role.
- Manage roles.
- Manage permissions.
- Review user activity.
- Force logout when needed.
