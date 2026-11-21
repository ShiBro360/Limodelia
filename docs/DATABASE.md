# Database Documentation

This document describes the planned database structure for Limodelia CRM. It does not contain SQL. Field types are described conceptually and should be converted into MySQL definitions during implementation.

## General Rules

- All tables should use UTF8MB4 encoding.
- Every main table should have a numeric primary key.
- Every operational table should include creation and update timestamps.
- Financial amounts should use decimal values, not floating-point values.
- Status fields should use controlled values documented in settings tables.
- Foreign key relationships should be enforced where practical.
- Deletions should usually be soft deletes for operational records.

## users

Purpose: Stores staff accounts that can log in to the CRM.

Fields:

- id: primary identifier.
- role_id: linked role.
- name: display name.
- email: unique login email.
- password_hash: hashed password.
- phone: optional contact number.
- status: active, inactive, suspended.
- last_login_at: latest successful login time.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `roles`.
- Has many `audit_logs`.
- May approve `expenses` and `salary_periods`.

Indexes:

- Unique index on email.
- Index on role_id.
- Index on status.

Validation:

- Email is required and must be unique.
- Password hash is required.
- Status must be one of the approved values.
- Role must exist.

## roles

Purpose: Defines user access groups.

Fields:

- id: primary identifier.
- name: role name.
- slug: stable machine-readable key.
- description: role purpose.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Has many `users`.
- Has many `role_permissions`.

Indexes:

- Unique index on slug.

Validation:

- Name is required.
- Slug is required and unique.

## permissions

Purpose: Stores individual permission keys used for access control.

Fields:

- id: primary identifier.
- name: human-readable permission name.
- slug: stable permission key.
- module: related module.
- description: permission details.

Relationships:

- Has many `role_permissions`.

Indexes:

- Unique index on slug.
- Index on module.

Validation:

- Slug is required and unique.
- Module is required.

## role_permissions

Purpose: Connects roles to permissions.

Fields:

- id: primary identifier.
- role_id: linked role.
- permission_id: linked permission.

Relationships:

- Belongs to `roles`.
- Belongs to `permissions`.

Indexes:

- Unique composite index on role_id and permission_id.
- Index on permission_id.

Validation:

- Role must exist.
- Permission must exist.
- Duplicate permission assignments are not allowed.

## students

Purpose: Stores student profiles.

Fields:

- id: primary identifier.
- first_name: student first name.
- last_name: student last name.
- birth_date: date of birth.
- gender: optional profile value.
- phone: optional student phone.
- telegram_username: optional student Telegram username, stored without `@` when possible.
- photo_path: relative path to the cropped profile photo stored by the Students module.
- guardian_name: parent or guardian name.
- guardian_phone: parent or guardian phone.
- guardian_telegram_username: optional parent or guardian Telegram username, stored without `@` when possible.
- enrollment_date: first enrollment date.
- status_id: linked student status.
- payment_plan_id: linked payment plan.
- notes: internal notes.
- created_at: creation timestamp.
- updated_at: update timestamp.
- deleted_at: soft delete timestamp.

Relationships:

- Belongs to `student_statuses`.
- Belongs to `payment_plans`.
- Has many `student_group_memberships`.
- Has many `payments`.
- Has many `student_photos`.
- Has many `trip_participants`.
- Has many `student_notes`.
- Has many `attendance_records`.

Indexes:

- Index on last_name and first_name.
- Index on status_id.
- Index on payment_plan_id.
- Index on telegram_username.
- Index on guardian_phone.
- Index on guardian_telegram_username.
- Index on enrollment_date.

Validation:

- First name and last name are required.
- Birth date must be a valid date when provided.
- Phone fields must match expected formats when provided.
- Telegram username fields may be empty.
- Telegram username fields should be stored without `@` when possible.
- Complex Telegram username validation is handled in PHP, not SQL.
- Email is not collected for students.
- Status must exist.

## student_statuses

Purpose: Defines available student lifecycle statuses.

Fields:

- id: primary identifier.
- name: status label.
- slug: stable status key.
- color: UI color token.
- is_active: whether the status means active participation.
- sort_order: display order.

Relationships:

- Has many `students`.

Indexes:

- Unique index on slug.
- Index on is_active.

Validation:

- Name and slug are required.
- Slug must be unique.

## student_notes

Purpose: Stores internal notes attached to students.

Fields:

- id: primary identifier.
- student_id: linked student.
- user_id: author.
- note: note body.
- visibility: internal, finance, education, management.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `students`.
- Belongs to `users`.

Indexes:

- Index on student_id.
- Index on user_id.
- Index on visibility.

Validation:

- Student and author must exist.
- Note body is required.

## groups

Purpose: Stores learning groups or classes.

Fields:

- id: primary identifier.
- name: group name.
- teacher_id: linked teacher user.
- description: optional group notes.
- status: active, archived.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `users` as teacher.
- Has many `student_group_memberships`.
- Has many `lessons`.

Indexes:

- Index on teacher_id.
- Index on status.

Validation:

- Name is required.
- Teacher must exist when provided.

## student_group_memberships

Purpose: Connects students to groups.

Fields:

- id: primary identifier.
- student_id: linked student.
- group_id: linked group.
- joined_at: membership start date.
- left_at: membership end date.
- status: active, completed, removed.

Relationships:

- Belongs to `students`.
- Belongs to `groups`.

Indexes:

- Composite index on student_id and group_id.
- Index on group_id.
- Index on status.

Validation:

- Student and group must exist.
- Join date is required.
- Left date cannot be earlier than join date.

## lessons

Purpose: Stores scheduled or completed lessons.

Fields:

- id: primary identifier.
- group_id: linked group.
- teacher_id: linked teacher user.
- lesson_date: lesson date.
- start_time: start time.
- end_time: end time.
- status: planned, completed, cancelled.
- topic: lesson topic.
- notes: lesson notes.

Relationships:

- Belongs to `groups`.
- Belongs to `users` as teacher.
- Has many `attendance_records`.
- May be used by `salary_items`.

Indexes:

- Index on group_id.
- Index on teacher_id.
- Index on lesson_date.
- Index on status.

Validation:

- Lesson date is required.
- End time must be later than start time when both are provided.
- Status must be valid.

## attendance_records

Purpose: Tracks student attendance for lessons.

Fields:

- id: primary identifier.
- lesson_id: linked lesson.
- student_id: linked student.
- status: present, absent, late, excused.
- comment: optional note.

Relationships:

- Belongs to `lessons`.
- Belongs to `students`.

Indexes:

- Unique composite index on lesson_id and student_id.
- Index on student_id.
- Index on status.

Validation:

- Lesson and student must exist.
- Attendance status must be valid.

## payment_plans

Purpose: Defines tuition plans and recurring billing rules.

Fields:

- id: primary identifier.
- name: plan name.
- amount: expected amount.
- billing_period: monthly, once, custom.
- description: plan details.
- is_active: whether the plan can be assigned.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Has many `students`.
- Has many `expected_payments`.

Indexes:

- Index on is_active.
- Index on billing_period.

Validation:

- Name and amount are required.
- Amount cannot be negative.
- Billing period must be valid.

## payment_categories

Purpose: Defines payment types.

Fields:

- id: primary identifier.
- name: category name.
- slug: stable category key.
- is_active: whether category is available.
- sort_order: display order.

Relationships:

- Has many `payments`.
- Has many `expected_payments`.

Indexes:

- Unique index on slug.
- Index on is_active.

Validation:

- Name and slug are required.
- Slug must be unique.

## payment_methods

Purpose: Defines accepted payment methods.

Fields:

- id: primary identifier.
- name: method name.
- slug: stable method key.
- is_active: whether method is available.

Relationships:

- Has many `payments`.

Indexes:

- Unique index on slug.
- Index on is_active.

Validation:

- Name and slug are required.
- Slug must be unique.

## expected_payments

Purpose: Stores planned charges before actual payment is received.

Fields:

- id: primary identifier.
- student_id: linked student.
- payment_plan_id: linked plan.
- payment_category_id: linked category.
- due_date: expected payment date.
- amount_due: expected amount.
- status: pending, partial, paid, overdue, cancelled.
- source_type: manual, automatic, trip, plan.
- source_id: optional source record identifier.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `students`.
- Belongs to `payment_plans`.
- Belongs to `payment_categories`.
- Has many `payments`.

Indexes:

- Index on student_id.
- Index on due_date.
- Index on status.
- Composite index on source_type and source_id.

Validation:

- Student and category must exist.
- Due date is required.
- Amount due cannot be negative.
- Status must be valid.

## payments

Purpose: Stores actual received payments.

Fields:

- id: primary identifier.
- student_id: linked student.
- expected_payment_id: optional planned charge.
- payment_category_id: linked category.
- payment_method_id: linked method.
- amount: received amount.
- payment_date: date received.
- status: completed, refunded, cancelled.
- reference_number: optional external reference.
- comment: internal comment.
- created_by: user who recorded payment.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `students`.
- Belongs to `expected_payments`.
- Belongs to `payment_categories`.
- Belongs to `payment_methods`.
- Belongs to `users` as creator.

Indexes:

- Index on student_id.
- Index on expected_payment_id.
- Index on payment_date.
- Index on status.
- Index on payment_method_id.

Validation:

- Student, category, method, and creator must exist.
- Amount must be greater than zero.
- Payment date is required.
- Status must be valid.

## expense_categories

Purpose: Defines expense categories.

Fields:

- id: primary identifier.
- name: category name.
- slug: stable category key.
- is_active: whether category is available.
- sort_order: display order.

Relationships:

- Has many `expenses`.

Indexes:

- Unique index on slug.
- Index on is_active.

Validation:

- Name and slug are required.
- Slug must be unique.

## expenses

Purpose: Stores school expenses.

Fields:

- id: primary identifier.
- expense_category_id: linked category.
- payment_method_id: optional payment method.
- amount: expense amount.
- expense_date: date of expense.
- description: expense details.
- status: draft, submitted, approved, rejected, paid.
- receipt_file_id: optional uploaded receipt.
- trip_id: optional related trip.
- created_by: user who created expense.
- approved_by: user who approved expense.
- approved_at: approval timestamp.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `expense_categories`.
- Belongs to `payment_methods`.
- Belongs to `trips`.
- Belongs to `users` as creator.
- Belongs to `users` as approver.
- Belongs to `files` as receipt.

Indexes:

- Index on expense_category_id.
- Index on expense_date.
- Index on status.
- Index on trip_id.
- Index on created_by.

Validation:

- Category and creator must exist.
- Amount must be greater than zero.
- Expense date is required.
- Approved status requires approver and approval timestamp.

## recurring_expenses

Purpose: Stores templates for automatically generated expenses.

Fields:

- id: primary identifier.
- expense_category_id: linked category.
- amount: recurring amount.
- frequency: monthly, quarterly, yearly, custom.
- next_run_date: next generation date.
- description: expense description.
- is_active: whether automation is enabled.
- created_by: user who created template.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `expense_categories`.
- Belongs to `users`.

Indexes:

- Index on next_run_date.
- Index on is_active.
- Index on expense_category_id.

Validation:

- Category and creator must exist.
- Amount must be greater than zero.
- Frequency must be valid.
- Next run date is required when active.

## salary_rules

Purpose: Defines how staff salary is calculated.

Fields:

- id: primary identifier.
- user_id: linked staff member.
- rule_type: fixed, per_lesson, per_student, percentage_net_profit, remaining_profit, bonus, deduction.
- amount: fixed or unit amount.
- percentage: percentage value when relevant.
- effective_from: start date.
- effective_to: optional end date.
- is_active: whether rule is currently active.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `users`.
- Has many `salary_items`.

Indexes:

- Index on user_id.
- Index on rule_type.
- Index on effective_from.
- Index on is_active.

Validation:

- User must exist.
- Rule type must be valid.
- Amount or percentage must match the selected rule type.
- The `remaining_profit` rule has neither amount nor percentage and assigns the undistributed salary pool.
- Effective end date cannot be earlier than effective start date.

## salary_periods

Purpose: Stores salary calculation periods.

Fields:

- id: primary identifier.
- period_start: start date.
- period_end: end date.
- status: draft, calculated, approved, paid.
- calculated_at: calculation timestamp.
- approved_by: approving user.
- approved_at: approval timestamp.
- paid_at: payment timestamp.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Has many `salary_items`.
- Belongs to `users` as approver.

Indexes:

- Index on period_start and period_end.
- Index on status.

Validation:

- Start and end dates are required.
- End date cannot be earlier than start date.
- Approved status requires approver and approval timestamp.

## salary_items

Purpose: Stores salary line items for staff within a salary period.

Fields:

- id: primary identifier.
- salary_period_id: linked salary period.
- user_id: linked staff member.
- salary_rule_id: optional linked rule.
- source_type: fixed, lesson, student, payment, bonus, deduction, trip.
- source_id: optional source record identifier.
- description: line item description.
- quantity: unit count.
- rate: unit rate.
- amount: calculated line amount.
- created_at: creation timestamp.

Relationships:

- Belongs to `salary_periods`.
- Belongs to `users`.
- Belongs to `salary_rules`.

Indexes:

- Index on salary_period_id.
- Index on user_id.
- Composite index on source_type and source_id.

Validation:

- Salary period and user must exist.
- Amount is required.
- Quantity and rate cannot be negative.

## trips

Purpose: Stores school trips, events, camps, and external activities.

Fields:

- id: primary identifier.
- name: trip name.
- location: trip location.
- start_date: start date.
- end_date: end date.
- capacity: maximum participants.
- price: student price.
- internal_cost: estimated internal cost.
- status: planned, active, completed, cancelled.
- description: trip details.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Has many `trip_participants`.
- Has many `trip_photos`.
- Has many `expenses`.
- May generate `expected_payments`.

Indexes:

- Index on start_date.
- Index on end_date.
- Index on status.

Validation:

- Name is required.
- End date cannot be earlier than start date.
- Capacity cannot be negative.
- Price and internal cost cannot be negative.

## trip_participants

Purpose: Connects students to trips through a normalized many-to-many relationship.

Fields:

- trip_id: linked trip and first part of the composite primary key.
- student_id: linked student and second part of the composite primary key.
- status: planned, confirmed, cancelled.
- notes: optional notes.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `trips`.
- Belongs to `students`.
- `trips` have many `students` through `trip_participants`.
- `students` can belong to many `trips` through `trip_participants`.
- Deleting a trip cascades and removes only its participant links.
- Deleting a student is restricted while linked trip participant records exist.

Indexes:

- Composite primary key on trip_id and student_id.
- Index on student_id.
- Index on status.

Validation:

- Trip and student must exist.
- Duplicate participant records are not allowed.
- Status must be planned, confirmed, or cancelled.
- `planned` means the student is selected for the trip but not yet confirmed.
- `confirmed` means participation is accepted.
- `cancelled` means the student is no longer participating but the history can remain.

## files

Purpose: Stores uploaded file and media metadata.

Fields:

- id: primary identifier.
- original_name: original filename.
- storage_provider: local, cloudinary.
- storage_path: local path or provider path.
- public_url: accessible URL when available.
- mime_type: file MIME type.
- file_size: file size.
- width: image width when available.
- height: image height when available.
- uploaded_by: user who uploaded file.
- created_at: creation timestamp.

Relationships:

- Belongs to `users`.
- Has many `student_photos`.
- Has many `trip_photos`.
- May be referenced by `expenses`.

Indexes:

- Index on storage_provider.
- Index on mime_type.
- Index on uploaded_by.
- Index on created_at.

Validation:

- Original name is required.
- Storage provider is required.
- File size must be greater than zero.
- MIME type must be allowed.

## cloudinary_assets

Purpose: Stores Cloudinary-specific metadata for uploaded media.

Fields:

- id: primary identifier.
- file_id: linked file record.
- public_id: Cloudinary public ID.
- secure_url: Cloudinary secure URL.
- resource_type: image, video, raw.
- format: asset format.
- version: Cloudinary version value.
- folder: Cloudinary folder.
- tags: stored tag list or serialized metadata.
- created_at: creation timestamp.

Relationships:

- Belongs to `files`.

Indexes:

- Unique index on public_id.
- Index on file_id.
- Index on resource_type.
- Index on folder.

Validation:

- File and public ID are required.
- Secure URL is required.
- Resource type must be valid.

## albums

Purpose: Groups photos into logical collections.

Fields:

- id: primary identifier.
- name: album name.
- description: album details.
- student_id: optional related student.
- trip_id: optional related trip.
- visibility: internal, portfolio, public_link.
- created_by: user who created album.
- created_at: creation timestamp.
- updated_at: update timestamp.

Relationships:

- Belongs to `students`.
- Belongs to `trips`.
- Belongs to `users`.
- Has many `album_photos`.

Indexes:

- Index on student_id.
- Index on trip_id.
- Index on visibility.

Validation:

- Name is required.
- Creator must exist.
- Visibility must be valid.

## album_photos

Purpose: Connects file records to albums.

Fields:

- id: primary identifier.
- album_id: linked album.
- file_id: linked file.
- caption: optional caption.
- sort_order: display order.
- is_featured: featured photo flag.

Relationships:

- Belongs to `albums`.
- Belongs to `files`.

Indexes:

- Unique composite index on album_id and file_id.
- Index on file_id.
- Index on sort_order.

Validation:

- Album and file must exist.
- Duplicate album photo assignments are not allowed.

## student_photos

Purpose: Connects photos directly to students.

Fields:

- id: primary identifier.
- student_id: linked student.
- file_id: linked file.
- photo_type: profile, portfolio, event, document.
- is_primary: primary photo flag.
- created_at: creation timestamp.

Relationships:

- Belongs to `students`.
- Belongs to `files`.

Indexes:

- Index on student_id.
- Index on file_id.
- Index on photo_type.
- Index on is_primary.

Validation:

- Student and file must exist.
- Photo type must be valid.

## trip_photos

Purpose: Connects photos directly to trips.

Fields:

- id: primary identifier.
- trip_id: linked trip.
- file_id: linked file.
- caption: optional caption.
- created_at: creation timestamp.

Relationships:

- Belongs to `trips`.
- Belongs to `files`.

Indexes:

- Index on trip_id.
- Index on file_id.

Validation:

- Trip and file must exist.

## marketing_sources

Purpose: Defines lead acquisition sources.

Fields:

- id: primary identifier.
- name: source name.
- slug: stable source key.
- is_active: whether source is available.

Relationships:

- Has many `leads`.

Indexes:

- Unique index on slug.
- Index on is_active.

Validation:

- Name and slug are required.
- Slug must be unique.

## leads

Purpose: Stores prospective student inquiries.

Fields:

- id: primary identifier.
- name: lead name.
- phone: contact phone.
- email: contact email.
- source_id: linked marketing source.
- status: new, contacted, trial, converted, lost.
- assigned_to: responsible user.
- notes: lead notes.
- created_at: creation timestamp.
- updated_at: update timestamp.
- converted_student_id: linked student after conversion.

Relationships:

- Belongs to `marketing_sources`.
- Belongs to `users` as assignee.
- Belongs to `students` after conversion.

Indexes:

- Index on source_id.
- Index on status.
- Index on assigned_to.
- Index on created_at.
- Index on converted_student_id.

Validation:

- Name and at least one contact method are required.
- Source must exist when provided.
- Converted status requires converted student.

## activity_logs

Purpose: Stores business activity shown in dashboards and timelines.

Fields:

- id: primary identifier.
- user_id: user who performed action.
- entity_type: affected entity type.
- entity_id: affected entity identifier.
- action: action key.
- description: human-readable summary.
- created_at: creation timestamp.

Relationships:

- Belongs to `users`.

Indexes:

- Index on user_id.
- Composite index on entity_type and entity_id.
- Index on action.
- Index on created_at.

Validation:

- Action and description are required.
- Entity type and entity ID must be provided for entity-specific activity.

## audit_logs

Purpose: Stores security-relevant actions and sensitive changes.

Fields:

- id: primary identifier.
- user_id: acting user.
- event: event key.
- ip_address: request IP.
- user_agent: browser user agent.
- details: structured event details.
- created_at: creation timestamp.

Relationships:

- Belongs to `users`.

Indexes:

- Index on user_id.
- Index on event.
- Index on created_at.
- Index on ip_address.

Validation:

- Event is required.
- Details must not store raw passwords or secrets.

## settings

Purpose: Stores application configuration values.

Fields:

- id: primary identifier.
- setting_key: unique setting key.
- setting_value: setting value.
- setting_group: group name.
- value_type: string, number, boolean, json.
- is_private: whether value is sensitive.
- updated_by: user who changed setting.
- updated_at: update timestamp.

Relationships:

- Belongs to `users` as updater.

Indexes:

- Unique index on setting_key.
- Index on setting_group.
- Index on is_private.

Validation:

- Setting key is required and unique.
- Value type must be valid.
- Private values must not be displayed in plain text.

## backup_jobs

Purpose: Tracks manual and scheduled backup runs.

Fields:

- id: primary identifier.
- job_type: database, files, full.
- status: queued, running, completed, failed.
- file_path: backup file location.
- started_at: start timestamp.
- finished_at: finish timestamp.
- created_by: user who started backup.
- error_message: failure details.

Relationships:

- Belongs to `users`.

Indexes:

- Index on job_type.
- Index on status.
- Index on started_at.

Validation:

- Job type and status are required.
- Completed jobs require finished timestamp.
- Failed jobs should include an error message.
