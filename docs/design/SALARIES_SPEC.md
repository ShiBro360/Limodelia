# Salaries Module Specification

## Purpose

The Salaries module manages payroll for three Limodelia payroll roles:

- Teacher
- Accountant
- Marketing specialist

It calculates monthly salary previews, creates salary payout records, creates linked salary expenses for paid payouts, and keeps Dashboard and Expenses reporting consistent.

## Architecture

The module follows MVC Lite:

- `pages/salaries.php`: renders Payroll Center and handles POST/Redirect/GET actions.
- `SalaryRepository`: reads rules, salary history, financial base, creates payouts, creates linked expenses, and cancels payouts.
- `SalaryService`: owns salary calculations and payout orchestration.
- `SalaryValidator`: validates payout form input.
- `DashboardService`: displays salary estimates using month financial results.

No SQL is written directly in `pages/salaries.php`.

## Employees

Three payroll employees are supported:

- `teacher`: Teacher
- `accountant`: Accountant
- `marketing`: Marketing specialist

The database requires `salary_payouts.user_id`. If internal users for these employees do not yet exist, the repository creates service users:

- `teacher@limodelia.local`
- `accountant@limodelia.local`
- `marketing@limodelia.local`

These users are used only to keep salary history accountable.

## Accountant Salary

The accountant salary uses an active `salary_rules` record with:

- `role_scope = accountant`
- `rule_type = fixed` or `rule_type = percentage_net_profit`

For a percentage rule, the percentage is calculated from the distributable pool after fixed and per-student obligations.

## Teacher Salary

The teacher salary uses an active `salary_rules` record with:

- `role_scope = teacher`
- `rule_type = remaining_profit`

The current implementation calculates the teacher salary from current month financial results:

```text
profit base = completed payments - non-salary expenses
priority obligations = fixed salaries + per-student salaries
distribution pool = profit base - priority obligations
accountant salary = distribution pool * accountant percentage
teacher salary = profit base - priority obligations - accountant salary
```

Salary expenses are excluded from the salary base to avoid circular recalculation after a salary payout creates an expense. With an accountant percentage of 50%, the accountant and teacher receive equal halves of the distribution pool.

## Marketing Specialist Salary

The marketing specialist salary uses an active `salary_rules` record with:

- `role_scope = marketing`
- `rule_type = per_student`
- `amount = 45.00`

The calculation uses the existing Marketing module logic. For the selected month, the service sums `marketing_campaigns.new_students_count` for non-cancelled campaigns overlapping that month.

```text
marketing salary = new students from ads * 45 PLN
```

The amount is editable in Settings through the salary rule. The payout can be created from the Payroll Center like other employees. When paid, it creates a linked salary expense and updates Expenses, Reports, and Dashboard totals.

## Salary Payout

When a payout is saved with status `paid`:

1. `salary_payouts` record is created.
2. Related `expenses` record is created.
3. The expense is linked through `expenses.salary_payout_id`.
4. The expense uses `is_salary = 1`.
5. Dashboard monthly expenses and net profit update automatically.
6. Expenses module shows the linked salary cost.

When a payout is saved with status `calculated`, only the payroll record is created. No expense is created until a paid payout workflow is used.

## Cancel Logic

Cancel does not delete records.

It sets:

- `salary_payouts.status = cancelled`
- linked `expenses.status = cancelled`

Cancelled payroll expenses are excluded from Dashboard totals because Dashboard ignores cancelled expenses.

## Filters

The Payroll Center supports:

- month
- employee
- status

Filters are normalized by `SalaryService::filters()`.

## Page Structure

The page includes:

- module hero
- CSV placeholder
- add payout action
- filter panel
- KPI cards
- salary history table
- process explanation card
- right summary column
- add payout modal

## Dashboard Integration

Dashboard salary estimates are based on active salary rules and current month financial results. Dashboard net profit still uses all active expenses, including paid salary expenses.

This keeps two separate concepts clear:

- salary estimate: calculated before salary expenses
- net profit: calculated after all recorded expenses

## Reports Compatibility

Reports can use:

- `salary_payouts` for payroll history
- `expenses` with `is_salary = 1` for profit and loss calculations
- `expenses.salary_payout_id` to trace a salary expense back to its payout

## Future Improvements

Future phases can add:

- settings UI for accountant fixed salary
- salary approval workflow
- edit payout modal
- duplicate payout prevention per employee/month
- detailed salary reports
- payroll exports
- user-facing teacher salary preview
