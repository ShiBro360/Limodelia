# Payments Domain Model

## 1. Purpose

The Payments module manages expected charges, received payments, payment statuses, balances, refunds, and payment history. It is the financial center for student tuition, trips, workshops, registration fees, and custom services.

## 2. Business Rules

- Every received payment must be connected to a student.
- Payments should be connected to an expected payment when possible.
- Expected payments can be generated manually or automatically.
- Completed payments affect revenue reports.
- Cancelled payments do not count as revenue.
- Refunded payments must be visible in history and reports.
- Partial payments are allowed.
- Overdue status is based on due date and unpaid balance.
- Payment method and category must be selected.
- Financial records should not be permanently deleted after creation; corrections should use cancellation or adjustment records.

## 3. Fields

Expected payment fields:

- Expected payment ID.
- Student.
- Payment plan.
- Category.
- Due date.
- Amount due.
- Status.
- Source type.
- Source ID.
- Created date.
- Updated date.

Received payment fields:

- Payment ID.
- Student.
- Expected payment.
- Category.
- Payment method.
- Amount.
- Payment date.
- Status.
- Reference number.
- Comment.
- Created by.
- Created date.
- Updated date.

## 4. Validation Rules

- Student is required.
- Amount must be greater than zero for received payments.
- Amount due cannot be negative.
- Payment date must be valid.
- Due date must be valid.
- Category is required.
- Payment method is required for received payments.
- Payment status must be one of the approved values.
- A completed payment cannot have zero amount.
- Refund amount cannot exceed the originally completed amount.
- A cancelled expected payment should not accept new payments unless reactivated.

## 5. Relationships With Other Modules

- Students: payments belong to students.
- Trips: trip participation can generate expected payments.
- Salaries: teacher salary may be calculated as a percentage of payments.
- Reports: payments drive revenue, debt, and profit reports.
- Settings: payment categories, methods, statuses, and plans are configured there.
- Users: staff record and correct payments.
- Marketing: conversion value can be measured through payments from converted students.

## 6. Automatic Calculations

- Paid amount per expected payment.
- Remaining balance.
- Payment status from due date and balance.
- Student total debt.
- Monthly revenue.
- Revenue by category.
- Revenue by payment method.
- Trip payment completion.
- Student lifetime value.
- Refund impact on net revenue.

## 7. Possible Future Extensions

- Online card payment integration.
- Invoice generation.
- Receipt generation.
- Automated reminders.
- Payment import from bank statements.
- Installment schedules.
- Family or sibling discounts.
- Promo codes.
- Tax reporting exports.
- Multi-currency support.

## 8. Edge Cases

- Student pays more than expected.
- Student pays the wrong category.
- Payment is recorded on the wrong student.
- Payment is received before expected payment exists.
- Refund is issued after salary has been calculated.
- Payment plan changes during billing period.
- Expected payment is generated twice.
- Overdue payment becomes paid after deadline.
- Payment method is disabled after historical use.

## 9. User Actions

- View payment list.
- Filter by student, date, status, category, or method.
- Create expected payment.
- Record received payment.
- Add comment.
- Mark payment as cancelled.
- Record refund.
- View student balance.
- Export payment data.

## 10. Admin Actions

- Configure payment plans.
- Configure payment categories.
- Configure payment methods.
- Approve corrections.
- Lock closed financial periods.
- Review payment audit history.
- Generate monthly expected payments.
- Configure overdue rules and reminder timing.
