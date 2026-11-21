# Students Domain Model

## 1. Purpose

The Students module is the central domain of Limodelia CRM. It stores the full operational profile of each student and connects education, payments, trips, gallery assets, marketing conversion, attendance, notes, and reporting.

The student profile should become the primary source of truth for school staff.

## 2. Business Rules

- Every active student must have a clear status.
- Every student should have at least one reliable phone contact for the guardian or student.
- Phone is the primary contact method for students and guardians.
- Telegram is optional and can be used as an additional contact channel.
- Email is not collected for student profiles.
- Email remains available only for staff and administrator user accounts.
- A student can belong to multiple groups over time.
- A student can have one active payment plan at a time unless future business rules allow mixed plans.
- Student records should not be hard-deleted after financial activity exists.
- Inactive students remain available for historical reports.
- Guardian data is required for underage students.
- Internal notes are visible only to authorized staff.
- Student status changes should be logged.
- Converted leads should be linked to the created student profile.

## 3. Fields

- Student ID.
- First name.
- Last name.
- Birth date.
- Age, calculated from birth date.
- Gender, optional.
- Phone.
- Telegram username, optional.
- Guardian name.
- Guardian phone.
- Guardian Telegram username, optional.
- Enrollment date.
- Student status.
- Payment plan.
- Assigned groups.
- Notes.
- Primary profile photo.
- Source lead, when converted from marketing.
- Created date.
- Updated date.
- Archived or deleted date for soft deletion.

## 4. Validation Rules

- First name is required.
- Last name is required.
- Birth date must be a valid date and cannot be in the future.
- Enrollment date cannot be in the future unless explicitly allowed for pre-enrollment.
- Phone fields must use accepted phone format.
- Telegram usernames may be empty.
- Telegram usernames should be stored without `@` when possible.
- Complex Telegram username validation should happen in PHP, not SQL.
- Student status must exist in settings.
- Payment plan must exist when assigned.
- Guardian contact is required for students below the configured adult age.
- Duplicate student detection should warn on matching name, birth date, and guardian phone.
- A student cannot be assigned to an archived group as an active member.

## 5. Relationships With Other Modules

- Payments: a student has expected payments and actual received payments.
- Expenses: a student may indirectly relate to expenses through trips or custom services.
- Trips: a student can participate in multiple trips.
- Marketing: a student can originate from a converted lead.
- Salaries: student counts can influence teacher salary calculations.
- Reports: student data contributes to enrollment, activity, and revenue reports.
- Settings: statuses, payment plans, groups, and validation options are configured there.
- Users: staff create, edit, and comment on student records.
- Cloudinary: student profile and portfolio photos can be stored externally.

## 6. Automatic Calculations

- Age from birth date.
- Active student count by status.
- Outstanding student balance from expected payments minus completed payments.
- Student lifetime value from completed payments.
- Attendance percentage from lesson attendance.
- Trip payment balance for each trip.
- Days since last activity.
- Conversion date when created from a lead.

## 7. Possible Future Extensions

- Parent portal access.
- Student portal access.
- Contract generation.
- Medical or emergency contact information.
- Attendance analytics.
- Skills and portfolio tracking.
- Model measurements and casting profile.
- Document upload and expiry reminders.
- Multi-branch student assignment.
- Student timeline view.

## 8. Edge Cases

- Two students have the same name and guardian phone.
- Student changes guardian contact.
- Student becomes inactive with unpaid balance.
- Student is deleted by mistake and must be restored.
- Student is converted twice from the same lead.
- Student changes payment plan mid-month.
- Student joins a trip after the payment deadline.
- Student has photos but no active enrollment.
- Student has financial records and cannot be permanently deleted.

## 9. User Actions

- View student list.
- Search and filter students.
- Open student profile.
- Create student.
- Edit student details.
- Add internal note.
- Assign student to group.
- Change student status.
- View payment history.
- View trip participation.
- Upload student photo.
- Mark primary photo.

## 10. Admin Actions

- Configure student statuses.
- Configure payment plans.
- Merge duplicate student records.
- Archive or restore student.
- Export student data.
- Manage sensitive profile fields.
- Review student activity history.
- Control who can view notes and financial data.
