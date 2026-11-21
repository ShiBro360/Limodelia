# Salaries Domain Model

## 1. Purpose

The Salaries module calculates, reviews, approves, and tracks staff compensation. It supports fixed salaries, per-lesson rates, per-student rates, payment percentages, bonuses, deductions, and trip-related compensation.

## 2. Business Rules

- Salary calculations are grouped into salary periods.
- Salary periods have start and end dates.
- Salary rules must preserve historical accuracy.
- Approved salary periods should not be recalculated without admin action.
- Paid salary periods should be locked.
- Bonuses and deductions must be visible as separate line items.
- Teacher rates can change over time.
- Salary calculations should reference source records when possible.
- Salary records should not be hard-deleted after approval.
- Fixed and per-student obligations are deducted before percentage-based salary distribution.
- The accountant percentage is calculated from the pool remaining after fixed and per-student obligations.
- The teacher receives the final undistributed profit through the `remaining_profit` rule.

## 3. Fields

Salary rule fields:

- Rule ID.
- Staff user.
- Rule type.
- Amount.
- Percentage.
- Effective start date.
- Effective end date.
- Active state.

Salary period fields:

- Period ID.
- Start date.
- End date.
- Status.
- Calculated date.
- Approved by.
- Approved date.
- Paid date.

Salary item fields:

- Item ID.
- Salary period.
- Staff user.
- Salary rule.
- Source type.
- Source ID.
- Description.
- Quantity.
- Rate.
- Amount.
- Created date.

## 4. Validation Rules

- Staff user is required for salary rules.
- Rule type must be valid.
- Amount is required for fixed, per-lesson, and per-student rules.
- Percentage is required for percentage rules.
- Amount and percentage must be empty for a remaining-profit rule.
- Effective end date cannot be earlier than effective start date.
- Salary period start and end dates are required.
- Salary period end date cannot be earlier than start date.
- Approved period requires approver and approval date.
- Paid period requires paid date.
- Quantity cannot be negative.
- Salary item amount is required.

## 5. Relationships With Other Modules

- Users: salaries belong to staff users.
- Students: per-student rules may use active student counts.
- Payments: percentage-based rules may use completed payments.
- Trips: trip compensation may be included.
- Reports: salary data contributes to cost and profit reports.
- Settings: default salary rule types and approval behavior are configured there.
- Expenses: paid salaries may be summarized as personnel cost in reports.

## 6. Automatic Calculations

- Fixed salary line item.
- Lesson-based compensation from completed lessons.
- Student-count compensation.
- Payment percentage compensation.
- Bonus total.
- Deduction total.
- Gross salary.
- Net salary.
- Distributable pool after fixed and per-student obligations.
- Accountant percentage of the distributable pool.
- Teacher remainder after all other salary obligations.
- Salary cost by month.
- Salary cost by staff member.

## 7. Possible Future Extensions

- Staff contracts.
- Tax deductions.
- Payroll export.
- Teacher self-service salary preview.
- Multi-currency salaries.
- Commission tiers.
- Attendance-based salary rules.
- Salary advances.
- Approval workflow with multiple approvers.

## 8. Edge Cases

- Teacher rate changes in the middle of a salary period.
- Payment is refunded after salary approval.
- Lesson is cancelled after salary calculation.
- Salary period overlaps another period.
- Staff member becomes inactive before salary is paid.
- Bonus is entered twice.
- Paid period needs correction.
- Source record is deleted or archived.

## 9. User Actions

- View salary periods.
- View own salary summary when permitted.
- Review salary line items.
- Filter by staff member or period.
- Add bonus when permitted.
- Add deduction when permitted.
- Export salary summary.

## 10. Admin Actions

- Configure salary rules.
- Create salary period.
- Run calculation.
- Review calculated items.
- Approve salary period.
- Mark salary period as paid.
- Lock paid period.
- Reopen period for correction.
- Review salary audit history.
