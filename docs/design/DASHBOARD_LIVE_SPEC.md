# Live Dashboard Specification

The Dashboard uses the approved Elegant Sand layout and replaces placeholder values with live MySQL data through MVC Lite.

## Architecture

Files:

- `pages/dashboard.php`
- `app/Repositories/DashboardRepository.php`
- `app/Services/DashboardService.php`

`pages/dashboard.php` contains rendering only. SQL is isolated in `DashboardRepository`. Business calculations are isolated in `DashboardService`.

## Data Sources

KPIs:

- revenue from `payments` where `status = completed`
- expenses from `expenses` excluding `cancelled` and `rejected`
- net profit as revenue minus expenses
- active students from `students.status = active`

Charts:

- monthly revenue from `payments`
- revenue by group from `payments`, `students`, and `student_groups`

Lists:

- recent payments from `payments`
- recent expenses from `expenses`

Other cards:

- activity limit from `settings.non_registered_activity_limit` when present, otherwise `75000`
- salaries from active `salary_rules`
- Instagram spending from marketing/Instagram expenses

## UI Behavior

The dashboard keeps the existing visual structure:

- page hero and date range
- four KPI cards
- revenue line chart
- group revenue donut chart
- activity limit card
- salaries card
- Instagram card
- recent payments card
- recent expenses card

Empty recent lists show an empty-state message inside the existing card.

## Automatic Updates

The Dashboard reads live data on every request. After adding, editing, or cancelling a payment, refreshing the Dashboard updates:

- revenue KPI
- net profit KPI
- revenue chart
- revenue by group
- recent payments
- activity limit

## Future Improvements

- AJAX refresh
- previous-month KPI trends
- live marketing lead counts
- configurable dashboard period selector
- expense module integration for richer category analytics
