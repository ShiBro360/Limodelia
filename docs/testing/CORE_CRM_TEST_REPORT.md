# Core CRM Test Report

Date: 2026-06-28  
Environment: XAMPP local, `http://limodelia.local`  
Scope: Students, Payments, Expenses, Salaries, Live Dashboard

## Summary

Core CRM integration testing passed.

The tested business flows are stable after the current implementation of Students, Payments, Expenses, Salaries, and Live Dashboard. No module bugs were found during this test run, so no code fixes were required.

Temporary test records were created through existing services and then cleaned up:

- test students were archived
- test payments were cancelled
- test expenses were cancelled
- test salary payouts were cancelled
- linked salary expenses were cancelled

The accountant salary rule was temporarily set to a positive test value for the accountant payout test, then restored to its original value.

## 1. Student Flow

Status: Passed

Tested:

- create student
- edit student
- search student by Telegram username
- filter student by group, subscription, and status
- open student profile through `StudentService::profile()`
- archive student through soft delete

Results:

- Student create: Passed
- Student edit: Passed
- Student search: Passed
- Student filters: Passed
- Student profile open: Passed
- Student archive: Passed

Notes:

- Soft delete correctly sets `students.status = archived`.
- No permanent delete was used.

## 2. Payment Flow

Status: Passed

Tested:

- add payment
- edit payment amount and comment
- cancel payment
- verify payment appears in selected student profile payment data
- verify Dashboard revenue updates after add/edit/cancel

Results:

- Payment add: Passed
- Dashboard revenue after payment add: Passed
- Payment edit: Passed
- Student profile payments tab data: Passed
- Dashboard revenue after payment edit: Passed
- Payment cancel: Passed
- Dashboard revenue after payment cancel: Passed

Observed test values:

- Initial payment amount: `130.00 PLN`
- Edited payment amount: `140.00 PLN`
- Dashboard revenue increased by `130.00 PLN` after add.
- Dashboard revenue increased by `140.00 PLN` after edit.
- Dashboard revenue returned to baseline after cancellation.

## 3. Expense Flow

Status: Passed

Tested:

- add expense
- edit expense
- cancel expense
- verify Dashboard expenses update
- verify Dashboard net profit updates

Results:

- Expense add: Passed
- Dashboard expenses after expense add: Passed
- Dashboard net profit after expense add: Passed
- Expense edit: Passed
- Dashboard expenses after expense edit: Passed
- Expense cancel: Passed
- Dashboard expenses after expense cancel: Passed

Observed test values:

- Initial expense amount: `32.00 PLN`
- Edited expense amount: `64.00 PLN`
- Dashboard expenses increased by `32.00 PLN` after add.
- Dashboard net profit decreased by `32.00 PLN` after add.
- Dashboard expenses increased by `64.00 PLN` after edit.
- Dashboard expenses returned to baseline after cancellation.

## 4. Salary Flow

Status: Passed

Tested:

- create teacher payout
- create accountant payout
- verify linked expense is created for each paid payout
- cancel payouts
- verify linked expenses are cancelled
- verify Dashboard expenses update after payout creation and return after cancellation

Results:

- Salary teacher payout create: Passed
- Salary teacher linked expense: Passed
- Salary accountant payout create: Passed
- Salary accountant linked expense: Passed
- Dashboard after salary payouts: Passed
- Salary payout cancel: Passed
- Salary linked expense cancel: Passed
- Dashboard after salary cancel: Passed

Observed test behavior:

- Teacher payout created a linked salary expense with `expenses.is_salary = 1`.
- Accountant payout created a linked salary expense with `expenses.is_salary = 1`.
- Cancelling payouts set both `salary_payouts.status` and linked `expenses.status` to `cancelled`.
- Dashboard expenses returned to baseline after payout cancellation.

## 5. Dashboard Flow

Status: Passed

Tested:

- current month income
- current month expenses
- net profit formula
- active students count
- recent payments
- recent expenses

Results:

- Dashboard current month income numeric: Passed
- Dashboard current month expenses numeric: Passed
- Dashboard net profit formula: Passed
- Dashboard active students: Passed
- Dashboard recent payments array: Passed
- Dashboard recent expenses array: Passed

Observed values during test:

- Current month income: `1620.00 PLN`
- Current month expenses: `192.00 PLN`
- Net profit: `1428.00 PLN`
- Active students: Dashboard value matched database count.
- Recent payments returned at most 5 rows.
- Recent expenses returned at most 5 rows.

## 6. HTTP Regression

Status: Passed

All required pages returned HTTP 200:

- `dashboard.php`: Passed
- `students.php`: Passed
- `student_profile.php?id=1`: Passed
- `payments.php`: Passed
- `expenses.php`: Passed
- `salaries.php`: Passed

Mojibake regression check:

- `dashboard.php`: 0 suspicious broken-character matches
- `students.php`: 0 suspicious broken-character matches
- `student_profile.php?id=1`: 0 suspicious broken-character matches
- `payments.php`: 0 suspicious broken-character matches
- `expenses.php`: 0 suspicious broken-character matches
- `salaries.php`: 0 suspicious broken-character matches

## 7. Code Quality

Status: Passed

PHP lint was run on core files:

- `pages/dashboard.php`
- `pages/students.php`
- `pages/student_profile.php`
- `pages/payments.php`
- `pages/expenses.php`
- `pages/salaries.php`
- `app/Repositories/StudentRepository.php`
- `app/Repositories/PaymentRepository.php`
- `app/Repositories/ExpenseRepository.php`
- `app/Repositories/SalaryRepository.php`
- `app/Repositories/DashboardRepository.php`
- `app/Services/StudentService.php`
- `app/Services/PaymentService.php`
- `app/Services/ExpenseService.php`
- `app/Services/SalaryService.php`
- `app/Services/DashboardService.php`
- `app/Validators/StudentValidator.php`
- `app/Validators/PaymentValidator.php`
- `app/Validators/ExpenseValidator.php`
- `app/Validators/SalaryValidator.php`
- `app/Helpers/FormatHelper.php`

Result: no syntax errors detected.

## Bugs Found And Fixed

No bugs were found during this integration test run.

No code changes were required beyond creating this test report.

## Stability Assessment

Core CRM is stable for the tested scope.

The tested modules correctly preserve the business rules currently implemented:

- students are soft archived
- payments affect Dashboard revenue and can be cancelled
- expenses affect Dashboard expenses and net profit and can be cancelled
- paid salary payouts create linked salary expenses
- cancelled salary payouts cancel linked expenses
- Dashboard reflects current database state after each operation

Remaining risks for future work:

- automated duplicate salary payout prevention per employee/month is not yet implemented
- accountant salary still depends on the salary rule amount configured in data/settings
- salary approval workflow is not yet implemented
- test coverage is currently manual/scripted rather than PHPUnit-based
