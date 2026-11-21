# Marketing Module Specification

## Purpose

The Marketing module tracks Instagram campaign performance and connects every campaign budget with the CRM financial layer. It is designed for monitoring acquisition spending, leads, new students, conversion and cost efficiency.

## Page Layout

The page uses the existing Limodelia CRM layout:

- Left sidebar navigation.
- Topbar with global search and user controls.
- Main content area with the page title `Marketing`.
- Subtitle: `Analizuj skuteczność działań marketingowych na Instagramie.`
- Top actions with a date period selector and `Dodaj kampanię`.

The visual style follows the Elegant Sand UI system used by Dashboard, Students, Payments, Expenses and Reports.

## KPI Cards

The top row contains five KPI cards:

- `Wydatki na reklamy` - total non-cancelled campaign budget in the selected period.
- `Zapytania` - total leads count.
- `Nowe uczennice` - total new students count.
- `Koszt pozyskania uczennicy` - budget divided by new students.
- `Konwersja` - new students divided by leads as a percentage.

All division calculations are protected against division by zero. If leads or new students are zero, the calculated value is shown as `0`.

## Filters

Campaigns can be filtered by:

- Search text.
- Date start.
- Date end.
- Status.

Search checks campaign name, Instagram post URL, campaign URL and description. Reset filters return the page to the current month.

## Campaign Table

The campaign list contains:

- Campaign name.
- Period.
- Budget.
- Leads.
- New students.
- Cost per lead.
- Cost per student.
- Conversion.
- Status.
- Actions.

Rows use existing table, badge and action button components. The `Edytuj` action opens the edit modal. The `Anuluj` action cancels a campaign through POST/Redirect/GET.

## Campaign Statuses

Supported statuses:

- `planned` - planned campaign.
- `active` - running campaign.
- `paused` - temporarily paused campaign.
- `completed` - finished campaign.
- `cancelled` - cancelled campaign.

Cancelled campaigns remain visible in the CRM but are excluded from financial campaign totals.

## Add Campaign Modal

The add modal contains:

- Campaign name.
- Date start.
- Date end.
- Budget.
- Leads count.
- New students count.
- Instagram post URL.
- Campaign URL.
- Status.
- Notes.

Validation is handled by `MarketingValidator`.

## Edit Campaign Modal

The edit modal uses the same fields as the add modal and loads campaign data through `MarketingService`. Editing a campaign updates both the campaign record and its linked expense.

## Calculations

For each campaign:

- Cost per lead = `budget / leads_count`.
- Cost per student = `budget / new_students_count`.
- Conversion = `new_students_count / leads_count * 100`.

Zero values are handled safely:

- If leads are zero, cost per lead and conversion are zero.
- If new students are zero, cost per student is zero.

## Expense Integration

When a campaign is created, `MarketingService` creates a linked expense through `ExpenseService`:

- Category: Marketing.
- Description: campaign name.
- Amount: campaign budget.
- Expense date: campaign start date.
- Link: `expenses.marketing_campaign_id`.

When a campaign is edited, the linked expense is updated. When a campaign is cancelled, the linked expense is cancelled instead of deleted.

The campaign source is stored on `marketing_campaigns.source` as `instagram`. The current expenses table does not have a separate source column, so the financial link is represented through the Marketing category and `marketing_campaign_id`.

## Dashboard Integration

Dashboard marketing spending is calculated from expenses. Because every marketing campaign creates or updates a linked marketing expense, Dashboard values update automatically after:

- Adding a campaign.
- Editing campaign budget.
- Cancelling a campaign.

## Reports Integration

Reports read marketing costs from the expenses layer. Marketing campaign budgets are therefore included in financial and marketing cost summaries through their linked expenses.

## MVC Lite Structure

The module uses:

- `MarketingRepository` for database access.
- `MarketingService` for business logic, calculations and expense synchronization.
- `MarketingValidator` for validation.
- `pages/marketing.php` for request handling and rendering only.

No SQL is written inside `pages/marketing.php`.

## Security

- All database writes use prepared statements.
- POST/Redirect/GET is used for create, update and cancel actions.
- URLs are validated before saving.
- Budget and count fields are normalized and validated.
- Campaign cancellation is soft cancellation.

## Future Improvements

- Real chart library integration.
- Campaign channel support beyond Instagram.
- Automatic import of Instagram metrics.
- PDF and Excel exports.
- Campaign attachments and screenshots through StorageService.
- Detailed attribution from leads to enrolled students.
