# Limodelia CRM ER Diagram

This document describes the relationships in the pre-production MySQL database schema after the architectural refactor. It is a textual ER diagram intended for implementation review and long-term maintenance.

## Identity, Roles, and Access

### users

`users` stores staff accounts and authentication identity. It no longer stores a direct role enum. Roles are assigned through `user_roles`.

Relationships:

- `users.id` -> `user_roles.user_id`
- `users.id` -> `user_roles.assigned_by`
- `users.id` -> `students.created_by`
- `users.id` -> `student_subscriptions.created_by`
- `users.id` -> `payments.created_by`
- `users.id` -> `expenses.created_by`
- `users.id` -> `expenses.approved_by`
- `users.id` -> `marketing_campaigns.created_by`
- `users.id` -> `trips.created_by`
- `users.id` -> `trip_photos.uploaded_by`
- `users.id` -> `salary_rules.user_id`
- `users.id` -> `salary_payouts.user_id`
- `users.id` -> `salary_payouts.approved_by`
- `users.id` -> `reports.generated_by`
- `users.id` -> `settings.updated_by`
- `users.id` -> `system_logs.user_id`
- `users.id` -> `activity_logs.user_id`
- `users.id` -> `notifications.user_id`
- `users.id` -> `sessions.user_id`

Delete behavior:

- Historical operational records usually keep data and set user references to `NULL`.
- `user_roles`, `sessions`, and `notifications` are deleted when the related user is deleted.
- `salary_payouts.user_id` restricts user deletion because payroll history must remain accountable.

### roles

`roles` stores reusable access roles such as owner, administrator, finance, teacher, marketing, and viewer.

Relationships:

- `roles.id` -> `user_roles.role_id`

Delete behavior:

- Roles referenced by users are restricted from deletion.
- Roles should normally be marked inactive instead of deleted.

### user_roles

`user_roles` connects users to one or more roles.

Relationships:

- Parent: `users.id`
- Child: `user_roles.user_id`
- Relationship: one-to-many from user perspective
- Delete behavior: `ON DELETE CASCADE`

- Parent: `roles.id`
- Child: `user_roles.role_id`
- Relationship: one-to-many from role perspective
- Delete behavior: `ON DELETE RESTRICT`

- Parent: `users.id`
- Child: `user_roles.assigned_by`
- Relationship: optional assigning user
- Delete behavior: `ON DELETE SET NULL`

## Settings

### users -> settings

Settings can track the last user who updated each key.

- Parent: `users.id`
- Child: `settings.updated_by`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Settings are global key-value records. They influence other modules but do not own their records.

Seeded setting groups include:

- General app settings.
- School profile settings.
- Finance settings.
- UI settings.
- Payment settings.
- Upload settings.
- Cloudinary settings.
- Backup settings.

## Students, Groups, and Subscriptions

### student_groups -> students

One student group can contain many students.

- Parent: `student_groups.id`
- Child: `students.student_group_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Group metadata includes maximum size, color, visibility, status, and sort order.

### students -> student_subscriptions

A student can have many subscription assignments over time.

- Parent: `students.id`
- Child: `student_subscriptions.student_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Subscriptions are historical business records. A student with subscription history should be archived rather than deleted.

### subscriptions -> student_subscriptions

A subscription plan can be assigned to many students over time.

- Parent: `subscriptions.id`
- Child: `student_subscriptions.subscription_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Plans used in history should be disabled or archived instead of deleted.

### users -> student_subscriptions

Subscription assignment can track the staff user who created it.

- Parent: `users.id`
- Child: `student_subscriptions.created_by`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

## Payments

### students -> payments

Every payment belongs to one student.

- Parent: `students.id`
- Child: `payments.student_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Payments are financial history. A student with payments cannot be deleted without deliberate data migration or archival strategy.

### subscriptions -> payments

A payment can reference the subscription plan it was paid for.

- Parent: `subscriptions.id`
- Child: `payments.subscription_id`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

`payments.subscription_id` remains as a historical snapshot of what was paid for. It is intentionally separate from the current or past subscription assignments in `student_subscriptions`.

### payment_methods -> payments

Every payment uses one payment method.

- Parent: `payment_methods.id`
- Child: `payments.payment_method_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Historical payments must keep their payment method. Methods should be disabled instead of deleted.

### users -> payments

Payments can track the user who recorded them.

- Parent: `users.id`
- Child: `payments.created_by`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Payment records include unique `payment_number`, optional `receipt_number`, and `is_manual` for distinguishing manually entered records from future automated imports.

## Expenses

### expense_categories -> expenses

Every expense belongs to one category.

- Parent: `expense_categories.id`
- Child: `expenses.expense_category_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

### payment_methods -> expenses

An expense can optionally reference a payment method.

- Parent: `payment_methods.id`
- Child: `expenses.payment_method_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### trips -> expenses

An expense can optionally belong to a trip.

- Parent: `trips.id`
- Child: `expenses.trip_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### marketing_campaigns -> expenses

An expense can optionally belong to a marketing campaign.

- Parent: `marketing_campaigns.id`
- Child: `expenses.marketing_campaign_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### salary_payouts -> expenses

A salary payout can be reflected as an expense.

- Parent: `salary_payouts.id`
- Child: `expenses.salary_payout_id`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Payroll history remains in `salary_payouts`. The expense record allows salary payouts to be included in expense and profit reports without losing payroll-specific details.

### users -> expenses

Expenses track creator and approver.

- Parent: `users.id`
- Child: `expenses.created_by`
- Delete behavior: `ON DELETE SET NULL`

- Parent: `users.id`
- Child: `expenses.approved_by`
- Delete behavior: `ON DELETE SET NULL`

## Marketing

### users -> marketing_campaigns

Marketing campaigns can be created by users.

- Parent: `users.id`
- Child: `marketing_campaigns.created_by`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### marketing_campaigns -> expenses

Campaign expenses connect marketing spend to financial reports.

- Parent: `marketing_campaigns.id`
- Child: `expenses.marketing_campaign_id`
- Relationship: one-to-many optional

Campaign records support `instagram_post_url` and `campaign_url` for linking external campaign material.

## Trips and Cloudinary Photos

### trips -> trip_participants -> students

Trips and students have a many-to-many relationship through `trip_participants`.

- Parent: `trips.id`
- Join table: `trip_participants.trip_id`
- Parent: `students.id`
- Join table: `trip_participants.student_id`
- Relationship: many-to-many
- Trip delete behavior: `ON DELETE CASCADE`
- Student delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

Participant status values:

- `planned`: the student is selected for the trip but not confirmed yet.
- `confirmed`: the student is confirmed for the trip.
- `cancelled`: the student was removed or cancelled participation.

Reasoning:

Trip cancellation is handled by `trips.status` and does not delete students. Physical deletion of a trip removes only participant links, photos and dependent trip-only metadata. Student deletion is restricted so historical trip participation cannot silently lose its student reference.

### trips -> trip_photos

A trip can have many photos.

- Parent: `trips.id`
- Child: `trip_photos.trip_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE CASCADE`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Trip photo relationship records are deleted with the trip record. Actual Cloudinary asset deletion must still be handled by the application workflow because the database only stores Cloudinary metadata.

### trip_photos -> trips as cover

A trip can reference one photo as its cover.

- Parent: `trip_photos.id`
- Child: `trips.cover_photo_id`
- Relationship: one-to-one optional from trip perspective
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Implementation note:

The cover foreign key is added after `trip_photos` is created. This avoids circular creation-order problems while preserving database-level integrity.

### users -> trip_photos

Photos can be linked to the user who uploaded them.

- Parent: `users.id`
- Child: `trip_photos.uploaded_by`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Trip photo metadata includes:

- Cloudinary public ID.
- Folder.
- Version.
- Secure URL.
- Thumbnail URL.
- Original URL.
- File name.
- File size.
- Dimensions.
- Format.
- Description.
- Sort order.
- Cover flag.

## Salaries

### users -> salary_rules

A salary rule can be assigned to a specific user or apply by role scope.

- Parent: `users.id`
- Child: `salary_rules.user_id`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### users -> salary_payouts

Every salary payout belongs to one staff user.

- Parent: `users.id`
- Child: `salary_payouts.user_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE RESTRICT`
- Update behavior: `ON UPDATE CASCADE`

Reasoning:

Payroll history must remain accountable.

### salary_rules -> salary_payouts

A payout can reference the rule used for calculation.

- Parent: `salary_rules.id`
- Child: `salary_payouts.salary_rule_id`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### users -> salary_payouts as approver

Salary payouts can be approved by a user.

- Parent: `users.id`
- Child: `salary_payouts.approved_by`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

## Reports

### users -> reports

Reports can be generated by users.

- Parent: `users.id`
- Child: `reports.generated_by`
- Relationship: one-to-many
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Reports are only for generated or exported report artifacts. They store report type, period, optional filter snapshot, generated user, generation date, status, and optional file URL.

Reports are not live dashboard data. Live dashboard values should be calculated from source tables or future optimized summary tables.

## Logs

### system_logs

`system_logs` stores technical and system-level events:

- Errors.
- Warnings.
- Background process events.
- Database seed events.
- Integration failures.
- Security-relevant system events.

It can reference a user when available, but it is not intended to be the main human activity timeline.

### activity_logs

`activity_logs` stores human-readable user action history:

- User created a student.
- User updated a payment.
- User approved an expense.
- User changed trip cover photo.
- User assigned a role.

It supports old and new JSON values for audit-style diffs and can reference any entity through `entity_type` and `entity_id`.

### users -> system_logs

- Parent: `users.id`
- Child: `system_logs.user_id`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

### users -> activity_logs

- Parent: `users.id`
- Child: `activity_logs.user_id`
- Relationship: one-to-many optional
- Delete behavior: `ON DELETE SET NULL`
- Update behavior: `ON UPDATE CASCADE`

Both log tables use polymorphic entity references. These are intentionally not enforced by foreign keys because they can point to records across many modules.

## Notifications

### users -> notifications

Notifications can be assigned to a user.

- Parent: `users.id`
- Child: `notifications.user_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE CASCADE`
- Update behavior: `ON UPDATE CASCADE`

Notifications can also reference any entity by `related_entity_type` and `related_entity_id`. This is a polymorphic reference and is intentionally not enforced by a foreign key.

## Sessions

### users -> sessions

Sessions can belong to authenticated users.

- Parent: `users.id`
- Child: `sessions.user_id`
- Relationship: one-to-many
- Delete behavior: `ON DELETE CASCADE`
- Update behavior: `ON UPDATE CASCADE`

Anonymous sessions can be stored with `user_id = NULL` if needed.

## High-Level Relationship Summary

```text
users
  1 -> many user_roles
  1 -> many students
  1 -> many student_subscriptions
  1 -> many payments
  1 -> many expenses
  1 -> many marketing_campaigns
  1 -> many trips
  1 -> many trip_photos
  1 -> many salary_rules
  1 -> many salary_payouts
  1 -> many reports
  1 -> many settings
  1 -> many system_logs
  1 -> many activity_logs
  1 -> many notifications
  1 -> many sessions

roles
  1 -> many user_roles

student_groups
  1 -> many students

students
  1 -> many student_subscriptions
  1 -> many payments

subscriptions
  1 -> many student_subscriptions
  1 -> many payments

payment_methods
  1 -> many payments
  1 -> many expenses

expense_categories
  1 -> many expenses

marketing_campaigns
  1 -> many expenses

trips
  1 -> many expenses
  many -> many students through trip_participants
  1 -> many trip_photos
  1 -> optional cover trip_photo

salary_rules
  1 -> many salary_payouts

salary_payouts
  1 -> many expenses
```
