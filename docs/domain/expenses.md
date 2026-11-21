# Expenses Domain Model

## 1. Purpose

The Expenses module tracks school costs, approvals, receipts, recurring expenses, trip-related spending, and financial outflow. It supports accurate profit calculation and owner-level reporting.

## 2. Business Rules

- Every expense must have a category.
- Every expense must have an amount and expense date.
- Expenses can be draft, submitted, approved, rejected, or paid.
- Only approved expenses count in final financial reports unless a report explicitly includes drafts.
- Receipts are optional but recommended for accountable categories.
- Trip-related expenses should link to the relevant trip.
- Recurring expenses can generate future expense records.
- Approved expenses should not be edited freely; corrections should be logged.
- Rejected expenses should include a reason.

## 3. Fields

- Expense ID.
- Category.
- Payment method.
- Amount.
- Expense date.
- Description.
- Status.
- Receipt file.
- Related trip.
- Created by.
- Approved by.
- Approved date.
- Rejection reason.
- Created date.
- Updated date.

Recurring expense fields:

- Recurring expense ID.
- Category.
- Amount.
- Frequency.
- Next run date.
- Description.
- Active state.
- Created by.

## 4. Validation Rules

- Category is required.
- Amount must be greater than zero.
- Expense date is required and must be valid.
- Status must be one of the approved values.
- Approved status requires approver and approval date.
- Rejected status should require rejection reason.
- Trip must exist when linked.
- Receipt file must use an allowed file type.
- Recurring expense frequency must be valid.
- Recurring expense next run date is required when active.

## 5. Relationships With Other Modules

- Trips: expenses can belong to a trip.
- Reports: expenses drive cost, profit, and cash flow reports.
- Settings: expense categories and payment methods are configured there.
- Users: staff create and approve expenses.
- Cloudinary or files: receipts may be stored as media or documents.
- Salaries: salary payouts may be represented separately from general expenses or summarized with expenses in reports.

## 6. Automatic Calculations

- Total expenses by date range.
- Expenses by category.
- Trip cost total.
- Monthly recurring expense projection.
- Approved versus pending expense totals.
- Profit calculation from revenue minus expenses.
- Average monthly operating cost.

## 7. Possible Future Extensions

- Supplier management.
- Purchase orders.
- Receipt OCR.
- Budget limits by category.
- Approval thresholds.
- Multi-step approval workflow.
- Expense reimbursement.
- Bank export.
- Tax category mapping.

## 8. Edge Cases

- Expense is paid before approval.
- Receipt is uploaded after approval.
- Expense category is disabled but exists in historical records.
- Recurring expense generates duplicate entry.
- Expense is linked to a cancelled trip.
- Expense date belongs to a closed financial period.
- User edits amount after approval.
- Receipt file fails upload after expense form submission.

## 9. User Actions

- View expense list.
- Filter by category, date, status, or trip.
- Create expense.
- Upload receipt.
- Edit draft expense.
- Submit expense for approval.
- View rejection reason.
- Export expense data.

## 10. Admin Actions

- Configure expense categories.
- Approve expense.
- Reject expense.
- Lock approved expense.
- Manage recurring expenses.
- Review expense audit log.
- Reopen expense for correction.
- Configure approval thresholds.
