# Dashboard Specification

This document describes the current Dashboard implementation for the Limodelia CRM. It documents the existing layout, component usage, responsive behavior, and dashboard-specific CSS classes without defining new functionality.

## Screen Structure

The Dashboard is rendered by `pages/dashboard.php` inside the shared application layout.

The page keeps the global CRM shell:

- left sidebar
- topbar
- main content area
- footer

The Dashboard content itself is wrapped in:

```html
<div class="dashboard-compact">
```

This wrapper activates compact spacing and card padding for the Dashboard screen.

## Grid Structure

The main Dashboard area uses:

```css
.dashboard-grid
```

Desktop layout uses CSS Grid with three columns and two rows:

```css
grid-template-columns: minmax(0, 1.22fr) minmax(320px, .92fr) minmax(300px, .8fr);
grid-template-rows: auto 270px;
grid-template-areas:
    "chart group side"
    "payments expenses side";
```

The areas are:

- `chart`: revenue line chart
- `group`: group revenue donut chart
- `side`: right sidebar stack
- `payments`: latest payments card
- `expenses`: latest expenses card

The right sidebar spans both rows, so its bottom aligns with the bottom of the latest payments and latest expenses cards on desktop.

## Number Of Columns

Desktop Dashboard content uses three columns:

- Column 1: wide revenue chart and latest payments
- Column 2: group revenue chart and latest expenses
- Column 3: compact right sidebar with stacked summary cards

The KPI row above the main grid uses four equal columns:

```css
.kpi-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
```

## Card Layout

Dashboard cards use the shared `.card` component with Dashboard-specific compact sizing:

```html
<section class="card dashboard-card-sm ...">
```

Current Dashboard cards:

- `.chart-card`
- `.group-card`
- `.limit-card`
- `.payroll-card`
- `.instagram-card`
- `.payments-card`
- `.expenses-card`

All dashboard cards use:

- white card background from the Elegant Sand theme
- subtle border
- rounded corners
- soft shadow
- compact internal padding

The compact padding value is controlled by:

```css
.dashboard-compact {
    --dashboard-card-padding: 18px;
}
```

## KPI Layout

The KPI section uses:

```html
<section class="kpi-grid">
```

It contains four `.kpi-card` elements:

- Przychód
- Wydatki
- Zysk brutto
- Aktywne uczennice

Each KPI card contains:

- `.kpi-icon`: circular gold icon area
- `.kpi-label`: uppercase metric label
- `.kpi-value`: main numeric value
- `.kpi-trend`: supporting trend text

In compact Dashboard mode:

- card minimum height is `108px`
- icon size is `50px`
- value size is `23px`
- card padding is `16px 18px`

## Chart Layout

### Revenue Chart

The revenue chart card uses:

```html
<section class="card dashboard-card-sm chart-card dashboard-chart-sm">
```

The chart body uses:

- `.line-chart`
- `.chart-scale`
- `.chart-canvas`
- `.chart-gridline`
- `.chart-months`

The current chart is a static HTML/SVG placeholder. No chart library is used.

Compact chart height is:

```css
.dashboard-chart-sm .chart-scale,
.dashboard-chart-sm .chart-canvas {
    height: 188px;
}
```

### Group Revenue Chart

The group chart card uses:

```html
<section class="card dashboard-card-sm group-card dashboard-chart-sm">
```

The donut layout uses:

- `.donut-layout`
- `.donut-chart`
- `.donut-center`
- `.legend-list`
- `.legend-item`
- `.legend-dot`

In compact chart mode:

- donut size is `160px`
- donut inner cutout uses `24px` inset
- legend spacing is reduced

## Right Sidebar Layout

The right Dashboard column uses:

```html
<aside class="dashboard-side">
```

It is assigned to the `side` grid area and spans both rows of the desktop grid.

The right sidebar contains:

1. `.limit-card`
   - business limit progress
   - progress bar
   - yearly revenue
   - legal limit
   - remaining limit note

2. `.payroll-card`
   - accountant fixed amount
   - teacher net profit amount

3. `.instagram-card`
   - Instagram marketing summary
   - month selector
   - mini metrics
   - highlighted acquisition cost

The right sidebar uses the same Dashboard gap as the main grid:

```css
.dashboard-compact .dashboard-side {
    gap: var(--dashboard-gap);
}
```

## Latest Lists

The latest payments and latest expenses cards use compact list styling:

```html
<ul class="activity-list dashboard-list-compact">
```

Payments use:

- `.activity-item`
- `.avatar`
- `.avatar-photo`
- `.activity-primary`
- `.activity-date`
- `.activity-amount`

Expenses use:

- `.activity-item`
- `.expense-icon`
- `.activity-primary`
- `.activity-amount`

Both cards use `display: flex` with column direction. Their bottom buttons use:

```css
.payments-card .btn-block,
.expenses-card .btn-block {
    margin-top: auto;
}
```

This keeps the action buttons aligned at the bottom of each card.

## Spacing

Dashboard compact spacing is defined by:

```css
.dashboard-compact {
    --dashboard-gap: 14px;
}
```

This variable controls:

- gap between main grid cards
- gap inside right sidebar stack
- margin below the hero section
- margin below the KPI grid

The main grid uses the compact gap through:

```css
.dashboard-compact .dashboard-grid {
    gap: var(--dashboard-gap);
}
```

The Dashboard is intentionally compact so that the most important cards remain visible above the fold on common desktop screens.

## Padding

Dashboard cards use compact padding:

```css
.dashboard-card-sm {
    padding: var(--dashboard-card-padding);
}
```

Current compact padding:

```css
--dashboard-card-padding: 18px;
```

Additional compact adjustments:

- card header bottom margin: `14px`
- compact progress bar margin
- compact summary row padding
- compact highlighted metric padding

## Border Radius

Dashboard cards inherit the shared card radius from the UI foundation.

Relevant shared radius tokens:

- `--radius-md`
- `--radius-lg`
- `--radius-pill`

Usage examples:

- KPI cards use `--radius-lg`
- progress bars use `--radius-pill`
- highlighted metrics use `--radius-md`
- circular icons and avatars use `50%`

## Shadow Style

Dashboard cards use the shared soft shadow style from the Elegant Sand UI foundation.

KPI cards explicitly use:

```css
box-shadow: var(--shadow-soft);
```

The visual style should remain soft and restrained. Shadows are used to separate surfaces from the pale gray-brown background without creating heavy contrast.

## Typography

The Dashboard follows the global typography tokens from the UI foundation.

Dashboard-specific typography:

- hero title: `26px` in compact mode
- hero subtitle: small text token in compact mode
- card titles: `var(--font-size-lg)`
- KPI values: `23px` in compact mode
- KPI labels: small uppercase text
- supporting labels: secondary text color
- chart labels: extra small text

Primary values use strong weight and the main text color. Supporting labels use the secondary text color.

## Responsive Behavior

### Desktop

Desktop uses the full three-column layout:

```text
Row 1: chart | group | side
Row 2: payments | expenses | side
```

The KPI cards remain in one row with four columns.

### Tablet

At `max-width: 980px`, the main grid changes to two columns:

```css
grid-template-columns: 1fr 1fr;
grid-template-areas:
    "chart group"
    "side side"
    "payments expenses";
```

The right sidebar spans the full width and changes to two internal columns:

```css
.dashboard-side {
    grid-column: 1 / -1;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
```

### Mobile

At `max-width: 700px`, Dashboard sections become a single column.

The following layouts collapse to one column:

- `.kpi-grid`
- `.dashboard-grid`
- `.dashboard-side`
- `.donut-layout`
- `.mini-metrics`

Grid areas are reset on mobile so cards follow normal document order.

The hero section becomes vertical, and the date range button becomes full width.

## Dashboard-Specific CSS Classes

### Layout

- `.dashboard-compact`: enables compact Dashboard spacing and card padding variables
- `.dashboard-hero`: page greeting and date selector row
- `.dashboard-grid`: main Dashboard CSS Grid container
- `.dashboard-side`: right sidebar stack

### KPI

- `.kpi-grid`: four-column KPI container
- `.kpi-card`: KPI surface
- `.kpi-icon`: circular gold icon
- `.kpi-label`: metric label
- `.kpi-value`: metric value
- `.kpi-trend`: metric trend text
- `.kpi-trend.is-positive`: positive trend color

### Cards

- `.dashboard-card-sm`: compact Dashboard card padding
- `.chart-card`: revenue chart grid area
- `.group-card`: group chart grid area
- `.limit-card`: business limit card
- `.payroll-card`: salary summary card
- `.instagram-card`: marketing summary card
- `.payments-card`: latest payments card
- `.expenses-card`: latest expenses card

### Charts

- `.dashboard-chart-sm`: compact chart sizing
- `.line-chart`: revenue chart layout
- `.chart-scale`: Y-axis labels
- `.chart-canvas`: SVG chart area
- `.chart-gridline`: horizontal gridline
- `.chart-months`: month labels
- `.donut-layout`: donut chart and legend layout
- `.donut-chart`: CSS donut chart
- `.donut-center`: center value inside donut
- `.legend-list`: chart legend list
- `.legend-item`: legend row
- `.legend-dot`: legend marker

### Lists And Metrics

- `.activity-list`: base activity list
- `.dashboard-list-compact`: compact Dashboard list spacing
- `.activity-item`: activity row layout
- `.activity-primary`: primary row text
- `.activity-date`: date text
- `.activity-amount`: amount and method text
- `.expense-list`: expense-specific list grid
- `.expense-icon`: circular expense marker
- `.progress-bar`: progress track
- `.progress-fill`: progress value
- `.limit-values`: business limit values
- `.limit-note`: highlighted limit note
- `.summary-row`: salary row
- `.mini-metrics`: marketing metrics grid
- `.highlight-metric`: highlighted acquisition cost metric

## Component Usage

The Dashboard reuses existing UI foundation components:

- `.card` for surfaces
- `.card-header` for card title/action rows
- `.card-title` for card headings
- `.btn` for controls
- `.btn-secondary` for secondary actions
- `.btn-sm` for compact buttons
- `.btn-block` for full-width action buttons
- `.text-link` for inline card links
- `.avatar` and `.avatar-photo` for payment list identities

The Dashboard does not use database queries yet. All displayed values are placeholder/sample data defined in `pages/dashboard.php`.

## Implementation Notes

- The Dashboard currently uses only HTML, CSS, PHP templates, and vanilla JavaScript from the shared UI foundation.
- Charts are static placeholders and must remain framework-free until a charting phase is approved.
- Desktop alignment depends on the current grid structure where `.dashboard-side` spans both rows and the lower cards use a `270px` second row.
- Future Dashboard changes should preserve the Elegant Sand visual language: pale background, white cards, gold accents, soft borders, and restrained shadows.
