# Expenses Module Specification

## Purpose

The Expenses module manages school costs in the Limodelia CRM. It records manual expenses, links costs to categories and payment methods, supports filtering and pagination, and feeds the live Dashboard expense, net profit, and recent expense cards.

## Page Layout

The page is implemented in `pages/expenses.php` and follows the existing operational module layout:

- module hero with title `Wydatki`
- subtitle `Zarządzaj kosztami szkoły i kontroluj zysk netto.`
- disabled CSV export placeholder
- primary `Dodaj wydatek` action
- four KPI cards
- main filter and table panel
- right summary column with monthly total, category totals, and largest expense

The page uses the same UI foundation as Dashboard and Payments:

- `.module-hero`
- `.module-actions`
- `.kpi-grid`
- `.kpi-card`
- `.payments-layout`
- `.module-panel`
- `.filter-bar`
- `.table`
- `.pagination`
- `.payments-side`
- `.payments-summary-card`
- `.modal`

## Filters

The filter bar supports:

- search by expense description
- month
- expense category
- payment method
- reset filters

Filters are normalized in `ExpenseService::filters()` and executed through prepared SQL in `ExpenseRepository`.

## Table

The expenses table contains:

- Data
- Kategoria
- Opis
- Kwota
- Metoda
- Status
- Akcje

Rows support edit and cancel actions. Cancelling does not delete records. It sets `expenses.status = cancelled`.

## Modal Behavior

The add/edit modal supports:

- date
- category
- description
- amount
- payment method
- comment
- status

The category is the primary classifier. The UI does not use a separate `source` field because it would duplicate category logic.

The modal adapts to selected categories:

- `Wynajem`: shows room booking fields, booking date, lesson hours, and hourly rate. The default hourly rate is `32.00 PLN`; amount is calculated as hours multiplied by the rate.
- `Marketing`: shows an Instagram advertising field for the campaign or promoted post description.
- Other categories use the standard expense fields.

The database schema also has no dedicated `comment` column for expenses. Comments entered in the modal are appended to `description` with a `Komentarz:` prefix.

Category-specific details are also appended to `description` because the current schema has no dedicated metadata table for expense details.

## Status Logic

Supported database statuses:

- `draft`
- `submitted`
- `approved`
- `rejected`
- `paid`
- `cancelled`

The current UI allows safe manual statuses that do not require approval metadata:

- `draft`
- `submitted`
- `rejected`
- `cancelled`

Existing `approved` or `paid` records can still be displayed. Approval workflow can be added later when user permissions and approval metadata are implemented.

## Dashboard Integration

Dashboard integration already reads from `expenses`:

- monthly expenses exclude `cancelled` and `rejected`
- net profit is revenue minus expenses
- recent expenses exclude `cancelled` and `rejected`
- Instagram spending is calculated from marketing categories or Instagram descriptions

After adding or editing an active expense, Dashboard cards update on the next page load. After cancelling an expense, it is excluded from Dashboard totals and recent expenses.

## Architecture

The module follows MVC Lite:

- `pages/expenses.php`: request handling and rendering only
- `ExpenseRepository`: SQL queries and persistence
- `ExpenseCategoryRepository`: category select data
- `ExpenseService`: filtering, normalization, category-specific detail handling, business operations
- `ExpenseValidator`: required field and amount validation
- `FormatHelper`: expense status labels and badge classes

No SQL is written directly inside `pages/expenses.php`.

## Future Integrations

Future phases should connect expenses to:

- salary payouts through `salary_payout_id`
- trips through `trip_id`
- marketing campaigns through `marketing_campaign_id`
- receipt upload through `receipt_url`
- approval workflow through `approved_by` and `approved_at`
- recurring expense generation
- detailed profit and loss reports
