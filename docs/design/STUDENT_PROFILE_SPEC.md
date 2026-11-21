# Student Profile Screen Specification

This document describes the current implementation of `pages/student_profile.php`. It is intended as a development reference for ongoing backend integration, CRUD flows, and data-driven rendering.

## Page Purpose

The Student Profile screen presents a single student as an operational record for school staff. It combines identification, contact details, learning information, remaining lesson balance, payment preview, subscription history, notes, and documents in one compact workspace.

The screen loads the selected student from MySQL through the existing PDO connection. It reads student identity/contact data, current subscription data, subscription history, and payment rows. Notes and documents remain visual placeholders until those modules receive database-backed tables and upload flows.

## Contact Model

Student contact data uses the current Telegram-based model:

- phone
- Telegram username
- guardian phone
- guardian Telegram

Student email is not collected or displayed. Email remains reserved for staff and administrator user accounts.

## Layout Structure

The page uses the shared application layout:

- sidebar
- topbar
- main content area

The profile content uses a fixed desktop grid:

```css
.student-profile-grid
```

Desktop grid columns:

- left profile column: `255px`
- main content column 1: flexible
- main content column 2: flexible
- right column: `238px`

Desktop grid areas:

```text
student | learning | learning | next
student | tabs     | tabs     | history
bottom  | bottom   | bottom   | history
```

The layout is designed as a compact workspace. The footer is hidden on this screen, and internal panels handle overflow where needed.

## Header Structure

The header contains:

- breadcrumb: `Uczennice / Szczegóły uczennicy`
- student name
- status badge
- student ID
- joined date
- back button: `Powrót do listy`
- edit button: opens the existing Students edit modal through `students.php?modal=edit&id=...`
- more-actions placeholder button

Primary classes:

- `.breadcrumb`
- `.module-hero`
- `.profile-ref-hero`
- `.module-actions`
- `.badge`
- `.badge-dot`
- `.badge-success`

## Profile Card

The profile card is placed in the `student` grid area.

It contains:

- circular student avatar/photo placeholder
- phone
- Telegram username
- age and birth date
- guardian phone
- guardian Telegram
- notes shortcut

Primary classes:

- `.student-card`
- `.profile-area-student`
- `.student-portrait`
- `.avatar`
- `.avatar-photo`
- `.student-contact-stack`
- `.contact-icon`
- `.contact-icon-telegram`
- `.student-note-shortcut`

The avatar is circular and sized to fill the visual width of the profile card while remaining centered.

## Statistics Card

Statistics are not a separate card in the current implementation. They are embedded below `Informacje o nauce` inside the learning card.

Current statistics:

- number of payments
- total paid amount
- last payment
- remaining lessons

Primary classes:

- `.learning-stats-title`
- `.learning-stats`

The remaining lesson count is also shown in the next payment card icon.

## Learning Information Card

The learning information card is placed in the `learning` grid area.

It contains:

- group
- subscription
- subscription price
- lesson day
- lesson time
- start date
- status
- embedded statistics

Primary classes:

- `.learning-card`
- `.profile-area-learning`
- `.learning-grid`
- `.learning-stats`
- `.badge-sand`
- `.badge-success`

## Next Payment Card

The next payment card is placed in the `next` grid area.

It contains:

- remaining lesson count in the circular icon
- next payment month
- next payment amount
- due reminder

Primary classes:

- `.next-payment-card`
- `.profile-area-next`
- `.payment-card-icon`
- `.limit-note`

Important behavior:

- The number in `.payment-card-icon` represents remaining lessons, not total subscription lessons.
- The value is calculated from the latest completed subscription payment, the payment date, the paid lesson count, and the configured lesson schedule.
- The payment date starts the current lesson package. If the payment date is not a configured lesson day, counting starts from the next configured lesson day.
- A 4-lesson subscription consumes one configured lesson day per week. When the payment date falls on a configured lesson day, that weekday is used for the package; otherwise the next configured weekday is used.
- An 8-lesson subscription consumes two configured lesson days per week.
- The next payment date is the next configured lesson day after the last paid lesson.

## Subscription History

The subscription history card is placed in the `history` grid area.

It spans the middle and bottom rows of the desktop layout so its bottom aligns with the notes/documents row.

It contains a vertical timeline loaded from `student_subscriptions` and `subscriptions` with:

- current subscription period
- previous subscription period
- subscription name
- price

Primary classes:

- `.subscription-timeline-card`
- `.profile-area-history`
- `.subscription-timeline`
- `.panel-scroll`

## Notes Card

The notes card is placed in the bottom row through `.profile-bottom-row`.

It contains:

- card title
- add note placeholder button
- latest note preview
- link to all notes

Primary classes:

- `.notes-card`
- `.student-side-card`
- `.panel-scroll`
- `.profile-show-all`

The notes content currently uses `students.notes`. A dedicated notes table can replace this preview later.

## Documents Card

The documents card shares the bottom row with notes at a 50/50 width split.

It contains:

- card title
- add document placeholder button
- document list
- document type marker
- download placeholder button
- link to all documents

Primary classes:

- `.documents-card`
- `.document-list`
- `.document-row`
- `.document-icon`
- `.panel-scroll`
- `.profile-show-all`

The document list is currently a visual placeholder derived from the enrollment date. A dedicated documents/upload module should replace this preview later.

## Tabs

The tabbed panel is placed in the `tabs` grid area.

Tabs:

- `Płatności`
- `Obecności`
- `Notatki`
- `Dokumenty`

Default active tab:

- `Płatności`

Primary classes and attributes:

- `.profile-tabs-panel`
- `.profile-tabs`
- `.tabs`
- `.tab`
- `.tab.is-active`
- `.tab-panel`
- `[data-tabs]`
- `[data-tab-target]`
- `[data-tab-panel]`

The inactive panels are present in the DOM and hidden with the `hidden` attribute.

## Payments Table

The payments tab contains database-backed rows from `payments`, joined with `subscriptions` and `payment_methods`.

Columns:

- Data płatności
- Miesiąc
- Abonament
- Kwota
- Metoda płatności
- Status
- Komentarz
- Akcje

Primary classes:

- `.profile-table-wrap`
- `.profile-payments-table`
- `.table`
- `.badge`
- `.badge-dot`
- `.badge-success`
- `.btn`
- `.btn-ghost`
- `.btn-icon`
- `.btn-sm`

The table area scrolls internally when rows exceed the available panel height. If the student has no payments, the table shows an empty row instead of sample data.

## Responsive Behavior

Desktop behavior:

- fixed workspace-like profile grid
- no page footer
- no full-page scroll for the main profile workspace
- growing content scrolls inside specific panels

Tablet behavior:

- profile grid becomes two columns
- areas reflow into:

```text
student | learning
next    | history
tabs    | tabs
bottom  | bottom
```

Mobile behavior:

- profile grid collapses to one column
- tabs can scroll horizontally
- bottom cards stack vertically
- normal page flow is restored for narrow screens

## CSS Classes Used

Page and header:

- `.breadcrumb`
- `.module-hero`
- `.profile-ref-hero`
- `.module-actions`

Main layout:

- `.student-profile-grid`
- `.profile-area-student`
- `.profile-area-learning`
- `.profile-area-next`
- `.profile-area-tabs`
- `.profile-area-history`
- `.profile-area-bottom`
- `.profile-bottom-row`

Profile card:

- `.student-card`
- `.student-portrait`
- `.student-contact-stack`
- `.student-note-shortcut`
- `.contact-icon`
- `.contact-icon-telegram`

Learning and statistics:

- `.learning-card`
- `.learning-grid`
- `.learning-stats-title`
- `.learning-stats`

Payments and tabs:

- `.profile-tabs-panel`
- `.profile-tabs`
- `.profile-tab-action`
- `.tab-panel`
- `.profile-table-wrap`
- `.profile-payments-table`

Right and bottom cards:

- `.next-payment-card`
- `.payment-card-icon`
- `.subscription-timeline-card`
- `.subscription-timeline`
- `.notes-card`
- `.documents-card`
- `.document-list`
- `.document-row`
- `.document-icon`
- `.panel-scroll`
- `.profile-show-all`

Shared components:

- `.card`
- `.card-title`
- `.card-header`
- `.btn`
- `.btn-primary`
- `.btn-secondary`
- `.btn-ghost`
- `.btn-icon`
- `.btn-sm`
- `.badge`
- `.badge-dot`
- `.badge-success`
- `.badge-sand`
- `.table`
- `.table-wrap`
- `.avatar`
- `.avatar-photo`
- `.text-link`

## JavaScript Behavior

Tab switching is handled by `assets/js/ui.js`.

The script:

- finds containers with `[data-tabs]`
- listens for clicks on `[data-tab-target]`
- updates `.is-active` on the selected tab
- updates `aria-selected`
- shows the matching `[data-tab-panel]`
- hides inactive panels with the `hidden` property

No API calls are made. Profile corrections use standard POST/Redirect/GET requests.

The profile supports two protected administrative corrections:

- manual correction of the remaining lesson count for the current paid subscription
- retrospective editing of an attendance record that already exists

Manual lesson correction requires a value from zero to the subscription lesson limit and a mandatory reason. The correction is stored against the current payment together with a usage baseline, so lessons recorded after the correction continue to reduce the adjusted balance normally. Every correction is recorded in `activity_logs` with old and new values.

Retrospective attendance editing cannot create a new historical attendance record. It can only change the status or note of an existing record. After saving, attendance consumption is recalculated for the complete subscription payment in lesson-date order. The first two `absent_notice` records remain free, subsequent warned absences consume a lesson, `absent_no_notice` consumes a lesson, and `school_cancelled` does not. The old and new attendance values are written to `activity_logs`.

## Reusable Components Used

The screen reuses the existing UI foundation:

- card surfaces
- button variants
- badges
- tabs
- table wrapper
- table styling
- text links
- avatars
- internal scroll panels

The profile-specific layout classes are intentionally scoped to `student_profile.php` and should not be used for generic module pages unless the same workspace layout is required.

## Database Integration Notes

The current screen already reads:

- student identity from `students`
- phone and Telegram fields from `students`
- guardian phone and guardian Telegram from `students`
- group from `student_groups`
- current subscription from the active `student_subscriptions` record
- subscription metadata from `subscriptions`
- payment rows from `payments`
- payment method labels from `payment_methods`
- subscription history from `student_subscriptions`

Future backend work should add:

- notes from a dedicated student notes table
- documents from upload/document metadata

The `remaining_lessons` value is calculated from the active paid subscription and attendance consumption. A manual override is an explicit audited exception and does not replace the normal calculation for future payments.

The screen should continue to avoid collecting student email. Staff/admin authentication may continue using `users.email`.
