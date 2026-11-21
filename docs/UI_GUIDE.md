# UI Guide

This document defines the visual system for Limodelia CRM. The interface should feel professional, calm, and operational. It is intended for repeated daily work, not marketing presentation.

## Design Principles

- Prioritize clarity over decoration.
- Keep dense information readable.
- Use consistent spacing and controls across all modules.
- Make financial and operational status easy to scan.
- Avoid unnecessary animation.
- Use responsive layouts that preserve usability on tablets and phones.

## Light Theme

The light theme is the default theme.

Approved language:

- Pale gray-brown background.
- White cards and work surfaces.
- Gold accents for focus, active states, and important actions.

Recommended tokens:

- Page background: `#f3f0eb`
- Card background: `#ffffff`
- Surface background: `#fbfaf8`
- Primary text: `#27231f`
- Secondary text: `#6f675f`
- Border: `#ded8d0`
- Gold accent: `#c89b3c`
- Gold hover: `#a97f2e`
- Success: `#2f855a`
- Warning: `#b7791f`
- Error: `#b83232`
- Info: `#2b6cb0`

## Dark Theme

The dark theme should be available as a future user preference.

Approved language:

- Mahogany background.
- Brown surfaces.
- Rose accent.

Recommended tokens:

- Page background: `#241513`
- Card background: `#33211e`
- Surface background: `#3f2a25`
- Primary text: `#f7eee8`
- Secondary text: `#cdbdb4`
- Border: `#5a3d36`
- Rose accent: `#d86f8a`
- Rose hover: `#f08ca4`
- Success: `#68d391`
- Warning: `#f6ad55`
- Error: `#fc8181`
- Info: `#90cdf4`

## Colors

Color usage:

- Gold is used for primary actions, active navigation, selected filters, and important highlights in the light theme.
- Rose is used for primary actions and active states in the dark theme.
- Red is reserved for destructive actions and errors.
- Green is reserved for completed, paid, active, and positive financial states.
- Amber is reserved for pending, overdue warning, and attention-needed states.
- Blue is reserved for informational states.

Financial status colors:

- Paid: success.
- Partial: warning.
- Overdue: error.
- Pending: neutral.
- Refunded: info.
- Cancelled: muted.

## Typography

Recommended font stack:

```css
font-family: Arial, Helvetica, sans-serif;
```

Typography scale:

- Page title: 24-28px, bold.
- Section title: 18-22px, bold.
- Card title: 16-18px, bold.
- Body text: 14-16px, regular.
- Table text: 13-14px.
- Metadata and helper text: 12-13px.

Rules:

- Do not use negative letter spacing.
- Do not scale typography directly with viewport width.
- Keep line height between 1.4 and 1.6 for readable content.
- Use bold text for labels and important values, not for long paragraphs.

## Border Radius

Recommended radius:

- Cards: 8px.
- Buttons: 8px.
- Inputs: 8px.
- Tables: 8px outer container.
- Modals: 10px.
- Small badges: 999px only when they are pill-style status indicators.

Avoid large rounded corners on operational panels.

## Shadows

Use shadows sparingly.

Light theme:

- Card shadow: `0 8px 24px rgba(39, 35, 31, 0.06)`
- Modal shadow: `0 24px 70px rgba(39, 35, 31, 0.22)`
- Dropdown shadow: `0 12px 36px rgba(39, 35, 31, 0.14)`

Dark theme:

- Card shadow: `0 8px 24px rgba(0, 0, 0, 0.20)`
- Modal shadow: `0 24px 70px rgba(0, 0, 0, 0.45)`
- Dropdown shadow: `0 12px 36px rgba(0, 0, 0, 0.35)`

Do not rely on shadows alone to separate surfaces. Use borders as the primary separator.

## Spacing

Base spacing unit: 4px.

Recommended spacing:

- 4px: tight icon and text relationships.
- 8px: compact gaps inside controls.
- 12px: field groups and small card sections.
- 16px: standard component padding.
- 24px: page section spacing.
- 32px: large layout separation.

Page content should use consistent horizontal padding:

- Desktop: 24-32px.
- Tablet: 20-24px.
- Mobile: 16px.

## Sidebar

Purpose:

- Primary application navigation.
- Persistent orientation for users.

Desktop behavior:

- Fixed or sticky left column.
- Width between 248px and 280px.
- Brand area at top.
- Navigation grouped vertically.
- Active item clearly highlighted.

Mobile behavior:

- Hidden by default.
- Opens with a topbar button.
- Covers or slides over content.
- Closes after navigation or outside click in future implementation.

Visual rules:

- Active item uses accent color and a subtle surface change.
- Navigation labels must remain readable.
- Icons may be added later, but labels should remain available.

## Topbar

Purpose:

- Current page title.
- User account context.
- Quick actions.
- Future global search and notifications.

Desktop behavior:

- Horizontal layout above content.
- Page title on the left.
- User actions on the right.

Mobile behavior:

- Menu button appears.
- Title may wrap.
- User actions can move to a second row.

## Cards

Cards are used for:

- Dashboard metrics.
- Repeated list items.
- Forms.
- Summary panels.
- Report sections.

Card rules:

- White cards in light theme.
- Brown surfaces in dark theme.
- Use 8px radius.
- Use a visible border.
- Keep headings compact.
- Do not nest cards inside cards.
- Avoid decorative card grids when a table or list is more useful.

## Buttons

Button types:

- Primary: main action on the screen.
- Secondary: non-primary action.
- Ghost: low-emphasis action.
- Danger: destructive action.
- Icon button: compact toolbar action.

Rules:

- Primary buttons use gold in light theme and rose in dark theme.
- Destructive actions use error color.
- Buttons must have visible focus states.
- Disabled buttons must reduce contrast and ignore pointer events.
- Button text should be short and action-oriented.

Recommended heights:

- Standard button: 40-44px.
- Compact button: 32-36px.
- Icon button: 36-42px with square dimensions.

## Inputs

Input types:

- Text.
- Number.
- Date.
- Select.
- Textarea.
- Checkbox.
- Radio.
- File upload.

Rules:

- Labels are required.
- Helper text should explain constraints when needed.
- Error messages appear under the related field.
- Required fields should be clear.
- Use server-side validation even if client-side validation exists.
- Inputs should have consistent height and padding.

## Tables

Tables are used for:

- Students.
- Payments.
- Expenses.
- Salary items.
- Reports.
- Trips.
- Leads.

Rules:

- Header row uses a muted surface.
- Rows use clear borders.
- Numeric values align right.
- Dates use a consistent format.
- Status uses badges.
- Row actions stay grouped at the right.
- Empty states explain the absence of data and provide the next action when appropriate.

Responsive behavior:

- Important tables may scroll horizontally on mobile.
- High-use tables may get a stacked mobile layout later.
- Filters should remain accessible above the table.

## Modal Windows

Modal use cases:

- Create or edit compact records.
- Confirm destructive actions.
- Preview files or photos.
- Approve expenses or salaries.

Rules:

- Use a clear title.
- Keep actions at the bottom.
- Primary action appears on the right.
- Destructive confirmation must clearly name the affected record.
- Close button must be available.
- Escape key close can be added in JavaScript.
- Background overlay must be visible in both themes.

## Notifications

Notification types:

- Success.
- Error.
- Warning.
- Info.

Rules:

- Notifications should be concise.
- They should explain what happened and what the user can do next when relevant.
- Errors from forms should also appear near the related field.
- Auto-dismiss is acceptable for success messages.
- Errors should remain visible until dismissed or corrected.

## Forms

Form layout:

- Use a single column for narrow forms.
- Use two columns for larger desktop forms when fields are naturally paired.
- Group related fields under clear section headings.
- Keep submit actions sticky or visible for long forms in future versions.

Validation behavior:

- Validate required fields.
- Validate dates and date ranges.
- Validate financial amounts.
- Validate email and phone formats.
- Validate file uploads before storage.
- Preserve user input after validation errors.

## Responsive Behavior

Breakpoints:

- Mobile: up to 640px.
- Tablet: 641px to 1024px.
- Desktop: 1025px and above.

Responsive rules:

- Sidebar collapses on mobile.
- Topbar wraps cleanly.
- Page padding decreases on smaller screens.
- Tables support horizontal scrolling when needed.
- Cards stack vertically on mobile.
- Form grids become single column on mobile.
- Text must not overflow buttons, cards, or navigation items.

## Accessibility

Minimum expectations:

- Semantic HTML.
- Keyboard-accessible controls.
- Visible focus states.
- Sufficient color contrast.
- Form labels connected to inputs.
- Buttons used for actions and links used for navigation.
- Alternative text for meaningful images.

## Icon Usage

Icons may be used for navigation, table actions, and buttons. When an icon-only button is used, it must have an accessible label.

Recommended icon areas:

- Add.
- Edit.
- Delete.
- Search.
- Filter.
- Export.
- Upload.
- Download.
- Open menu.
- Close modal.

Icons should support the action, not replace clear information where text is needed.
