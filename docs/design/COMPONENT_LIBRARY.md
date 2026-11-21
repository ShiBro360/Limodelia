# Limodelia CRM Component Library

This document describes the reusable UI foundation for Limodelia CRM. The current implementation uses HTML, CSS, and vanilla JavaScript only.

## Theme System

The default theme is `Elegant Sand`.

Light theme variables are defined in:

- `assets/css/variables.css`

Dark theme variables are defined in:

- `assets/css/themes.css`

The dark theme exists as variables and can be toggled by adding:

```html
<html data-theme="dark">
```

The topbar theme button currently switches the `data-theme` value and stores the choice in `localStorage`.

## Layout

### App Shell

Use `.app-shell` as the main page wrapper.

```html
<div class="app-shell">
    <aside class="sidebar"></aside>
    <main class="main"></main>
</div>
```

### Sidebar

Classes:

- `.sidebar`
- `.brand`
- `.brand-mark`
- `.sidebar-nav`
- `.nav-link`
- `.nav-link.is-active`
- `.sidebar-footer`

Purpose:

- Persistent navigation on desktop.
- Slide-in navigation on mobile.

### Topbar

Classes:

- `.topbar`
- `.topbar-title`
- `.topbar-actions`
- `.topbar-user`
- `.menu-button`
- `.theme-toggle`

Purpose:

- Shows page title.
- Provides sidebar toggle on mobile.
- Provides theme toggle.
- Shows user context and logout action.

### Content Area

Classes:

- `.content`
- `.content-inner`

Use `.content-inner` to constrain page width and keep layouts consistent.

### Footer

Class:

- `.footer`

Use for copyright, version, and low-priority system metadata.

## Buttons

Base class:

- `.btn`

Variants:

- `.btn-primary`
- `.btn-secondary`
- `.btn-ghost`
- `.btn-icon`
- `.btn-sm`
- `.btn-block`

Example:

```html
<button class="btn btn-primary" type="button">Zapisz</button>
<button class="btn btn-secondary" type="button">Anuluj</button>
<button class="btn btn-ghost" type="button">Usuń filtr</button>
```

Use `.btn-icon` for square icon-only buttons.

Use `.btn-sm` for compact filters and `.btn-block` for full-width calls to action inside cards.

## Links

Class:

- `.text-link`

Example:

```html
<a class="text-link" href="#">Zobacz wszystkie</a>
```

Use for low-weight card header actions.

## Inputs, Selects, and Textareas

Classes:

- `.form-field`
- `.form-label`
- `.input`
- `.select`
- `.textarea`

Example:

```html
<label class="form-field">
    <span class="form-label">Imię</span>
    <input class="input" type="text" name="first_name">
</label>
```

Select example:

```html
<label class="form-field">
    <span class="form-label">Status</span>
    <select class="select" name="status">
        <option>Aktywna</option>
    </select>
</label>
```

Textarea example:

```html
<label class="form-field">
    <span class="form-label">Notatka</span>
    <textarea class="textarea" name="note"></textarea>
</label>
```

## Search Field

Class:

- `.search-field`

Example:

```html
<div class="search-field">
    <input type="search" placeholder="Szukaj">
</div>
```

The search icon is drawn with CSS pseudo-elements.

## Cards

Classes:

- `.card`
- `.card-header`
- `.card-title`
- `.card-description`

Example:

```html
<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Uczniowie</h2>
            <p class="card-description">Krótki opis sekcji.</p>
        </div>
    </div>
</section>
```

Do not nest cards inside cards.

## Module Headers

Classes:

- `.module-hero`
- `.module-kicker`
- `.module-actions`
- `.module-panel`

Example:

```html
<section class="module-hero">
    <div>
        <span class="module-kicker">Baza uczennic</span>
        <h1>Uczennice</h1>
        <p>Zarządzaj wszystkimi uczennicami szkoły.</p>
    </div>
    <div class="module-actions">
        <button class="btn btn-secondary" type="button">Eksportuj CSV</button>
        <a class="btn btn-primary" href="#">Dodaj uczennicę</a>
    </div>
</section>
```

Use `.module-hero` for operational module screens such as students, payments, expenses, and trips. Use `.module-panel` when a card should contain filters, a table, and pagination as one continuous surface.

## Filter Bars

Classes:

- `.filter-bar`
- `.filter-search`
- `.filter-actions`
- `.select-compact`

Example:

```html
<form class="filter-bar">
    <label class="form-field filter-search">
        <span class="form-label">Szukaj</span>
        <span class="search-field">
            <input type="search" placeholder="Szukaj...">
        </span>
    </label>
    <label class="form-field">
        <span class="form-label">Status</span>
        <select class="select">
            <option>Wszystkie</option>
        </select>
    </label>
    <div class="filter-actions">
        <button class="btn btn-secondary" type="submit">Filtruj</button>
        <a class="btn btn-ghost" href="#">Reset filtrów</a>
    </div>
</form>
```

Use filter bars for reusable search, select filters, sorting, and reset actions. On desktop they can be sticky below the topbar. On mobile they collapse to one column.

## Stat Cards

Classes:

- `.stats-grid`
- `.stat-card`
- `.stat-label`
- `.stat-value`
- `.stat-note`

Example:

```html
<div class="stats-grid">
    <article class="stat-card">
        <div class="stat-label">Aktywne uczennice</div>
        <div class="stat-value">24</div>
        <div class="stat-note">+3 w tym miesiącu</div>
    </article>
</div>
```

Use stat cards for high-level dashboard metrics only.

## KPI Cards

Classes:

- `.kpi-grid`
- `.kpi-card`
- `.kpi-icon`
- `.kpi-label`
- `.kpi-value`
- `.kpi-trend`
- `.kpi-trend.is-positive`

Example:

```html
<section class="kpi-grid">
    <article class="kpi-card">
        <span class="kpi-icon">▱</span>
        <div>
            <p class="kpi-label">Przychód</p>
            <strong class="kpi-value">9 340,00 zł</strong>
            <span class="kpi-trend is-positive">↑ +18% od kwietnia</span>
        </div>
    </article>
</section>
```

Use KPI cards for primary dashboard summaries.

## Dashboard Grids

Classes:

- `.dashboard-compact`
- `.dashboard-hero`
- `.dashboard-grid`
- `.dashboard-main`
- `.dashboard-middle`
- `.dashboard-side`
- `.dashboard-card-sm`
- `.dashboard-chart-sm`
- `.dashboard-list-compact`

These classes define the approved dashboard composition. They are responsive and collapse to a single column on mobile.

Use `.dashboard-compact` on the dashboard wrapper when the page needs to fit better into one desktop viewport. Use `.dashboard-card-sm` for medium card padding, `.dashboard-chart-sm` for reduced chart height, and `.dashboard-list-compact` for tighter recent-activity lists.


## Static Chart Placeholders

Classes:

- `.line-chart`
- `.chart-scale`
- `.chart-canvas`
- `.chart-months`
- `.donut-layout`
- `.donut-chart`
- `.donut-center`
- `.legend-list`
- `.legend-item`
- `.legend-dot`

Use these only for static placeholders until a real charting layer is approved.

## Activity Lists

Classes:

- `.activity-list`
- `.activity-item`
- `.activity-primary`
- `.activity-date`
- `.activity-amount`
- `.expense-list`
- `.expense-icon`

Use for compact recent payments, expenses, actions, and similar dashboard lists.

## Progress Bars

Classes:

- `.progress-bar`
- `.progress-fill`

Example:

```html
<div class="progress-bar">
    <span class="progress-fill"></span>
</div>
```

Width is currently controlled by CSS for static placeholders. When real data is added, use a controlled class or CSS variable from server-rendered values.

## Tables

Classes:

- `.table-wrap`
- `.table`

Example:

```html
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Nazwa</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Jan Kowalski</td>
                <td><span class="badge badge-success">Aktywny</span></td>
            </tr>
        </tbody>
    </table>
</div>
```

Tables are horizontally scrollable on small screens through `.table-wrap`.

Additional module table classes:

- `.students-table-wrap`
- `.students-table`
- `.clickable-row`
- `.table-footer`
- `.rows-control`

Use `.clickable-row` with `data-row-link` when the whole row should open a detail page. `assets/js/ui.js` handles mouse click and Enter key navigation while ignoring clicks on buttons, links, inputs, selects, and dropdowns.

Use `.table-footer` for row count controls, result text, and pagination.

## Badges

Base class:

- `.badge`

Variants:

- `.badge-success`
- `.badge-warning`
- `.badge-danger`
- `.badge-muted`
- `.badge-sand`
- `.badge-rose`
- `.badge-dot`

Example:

```html
<span class="badge">Robocze</span>
<span class="badge badge-success">Opłacone</span>
<span class="badge badge-warning">Częściowe</span>
<span class="badge badge-danger">Zaległe</span>
```

Use `.badge-dot` for status badges with a small circular indicator. Use `.badge-sand` and `.badge-rose` for soft category badges such as student groups.

## Alerts

Classes:

- `.alert`
- `.alert-info`

Example:

```html
<div class="alert alert-info">
    Funkcjonalność zostanie dodana w kolejnym etapie.
</div>
```

Additional alert variants can be added later using the existing status color tokens.

## Tabs

Classes:

- `.tabs`
- `.tab`
- `.tab.is-active`

Example:

```html
<div class="tabs">
    <button class="tab is-active" type="button">Aktywne</button>
    <button class="tab" type="button">Archiwum</button>
</div>
```

Tabs are visual only until module JavaScript is implemented.

## Modals

Classes:

- `.modal-backdrop`
- `.modal-backdrop.is-open`
- `.modal`
- `.student-modal`
- `.student-form-grid`
- `.student-form-wide`
- `.student-form-actions`

Example:

```html
<div class="modal-backdrop is-open">
    <section class="modal">
        <h2>Potwierdzenie</h2>
        <p>Treść okna modalnego.</p>
    </section>
</div>
```

Modal visibility is controlled by server-rendered page state. For the Students module, modal forms are opened with query parameters and submitted with standard POST requests.

Student CRUD forms use the modal classes with standard POST/Redirect/Get flow. `.student-modal` constrains modal width and viewport height, `.student-form-grid` creates a two-column desktop form, `.student-form-wide` spans textarea fields across the full form width, and `.student-form-actions` aligns submit/cancel actions to the right.

## Dropdowns

Classes:

- `.dropdown`
- `.dropdown.is-open`
- `.dropdown-menu`
- `.dropdown-item`

Example:

```html
<div class="dropdown">
    <button class="btn btn-secondary" type="button" data-dropdown-toggle>Opcje</button>
    <div class="dropdown-menu">
        <a class="dropdown-item" href="#">Edytuj</a>
    </div>
</div>
```

`assets/js/ui.js` toggles `.is-open` for dropdowns.

## Pagination

Classes:

- `.pagination`
- `.page-link`
- `.page-link.is-active`

Example:

```html
<nav class="pagination" aria-label="Paginacja">
    <a class="page-link" href="#">1</a>
    <a class="page-link is-active" href="#">2</a>
</nav>
```

## Empty States

Classes:

- `.empty-state`
- `.empty-state-title`
- `.empty-state-text`

Example:

```html
<section class="empty-state">
    <h2 class="empty-state-title">Brak danych</h2>
    <p class="empty-state-text">Dane pojawią się po dodaniu pierwszego rekordu.</p>
</section>
```

Use empty states for modules that have no records yet or are not implemented.

Additional empty and loading state classes:

- `.empty-illustration`
- `.module-empty`
- `.students-loading`
- `.skeleton-row`
- `.is-hidden`

Example:

```html
<section class="students-loading">
    <div class="skeleton-row"></div>
    <div class="skeleton-row"></div>
</section>
```

Use skeleton rows for table loading placeholders. Use `.is-hidden` to keep inactive states mounted but visually hidden.

## Avatars

Class:

- `.avatar`
- `.avatar-photo`
- `.student-photo`

Example:

```html
<span class="avatar">A</span>
```

Use initials until user profile images are implemented.

## JavaScript Utilities

Files:

- `assets/js/theme.js`
- `assets/js/ui.js`
- `assets/js/app.js`

`theme.js`:

- Reads theme preference from `localStorage`.
- Applies `data-theme`.
- Updates the topbar theme toggle label.

`ui.js`:

- Opens and closes the mobile sidebar.
- Closes sidebar after navigation on mobile.
- Toggles dropdown menus.

`app.js`:

- Reserved for future page-specific scripts.
