# Reports Domain Model

## 1. Purpose

The Reports module provides structured insight into school performance, finances, students, marketing, trips, and staff compensation. Reports should help owners and managers make decisions from reliable operational data.

## 2. Business Rules

- Reports must respect user permissions.
- Financial reports should clearly define included statuses.
- Date filters are required for time-based reports.
- Cancelled records should not count in totals unless explicitly selected.
- Refunded payments should reduce net revenue.
- Approved expenses should be used for final profit reports.
- Draft data may be included only in operational previews.
- Reports should be reproducible for the same filters.
- Exported reports should include generation date and filters.

## 3. Fields

Report filter fields:

- Date from.
- Date to.
- Module.
- Status.
- Category.
- User.
- Student.
- Trip.
- Payment method.
- Source.

Report output fields vary by report:

- Total revenue.
- Net revenue.
- Outstanding balance.
- Total expenses.
- Profit.
- Active student count.
- New student count.
- Lead count.
- Conversion rate.
- Salary total.
- Trip profitability.

## 4. Validation Rules

- Date range must be valid.
- End date cannot be earlier than start date.
- Filter values must exist when selected.
- User must have permission to view the selected report.
- Export format must be supported.
- Large date ranges may require pagination or background generation.

## 5. Relationships With Other Modules

- Students: enrollment and status reports.
- Payments: revenue and debt reports.
- Expenses: cost and profit reports.
- Trips: trip participation and profitability.
- Marketing: source and conversion reports.
- Salaries: payroll and staff cost reports.
- Users: report permissions and generated-by metadata.
- Settings: default reporting periods and export preferences.

## 6. Automatic Calculations

- Revenue by period.
- Net revenue after refunds.
- Outstanding debt.
- Expense totals.
- Profit and loss.
- Student growth.
- Lead conversion.
- Average revenue per student.
- Salary cost as percentage of revenue.
- Trip profit margin.
- Payment method distribution.

## 7. Possible Future Extensions

- Scheduled monthly reports.
- PDF export.
- Excel export.
- Report snapshots.
- Custom report builder.
- Visual charts.
- Branch comparison.
- Forecasting.
- Owner dashboard.
- Email delivery.

## 8. Edge Cases

- Report includes deleted or archived records.
- Historical category was renamed.
- Payment refund occurs after report export.
- Closed financial period is corrected.
- User loses permission after report was generated.
- Very large report causes timeout.
- Time zone changes affect date boundaries.
- Incomplete data causes misleading totals.

## 9. User Actions

- Open reports page.
- Select report type.
- Apply filters.
- View totals.
- Drill into source records.
- Export report.
- Save preferred filters when available.

## 10. Admin Actions

- Configure report permissions.
- Configure default reporting periods.
- Lock financial periods.
- Generate official monthly report.
- Review report exports.
- Create report snapshots.
- Manage export formats.
