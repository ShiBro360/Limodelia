# Payments Module Specification

The Payments module provides a database-backed register of student payments using the MVC Lite architecture.

## Page Layout

`pages/payments.php` uses the shared application layout with sidebar, topbar, content area, and footer.

The screen contains:

- module hero with title `Płatności`
- subtitle `Zarządzaj płatnościami uczennic.`
- disabled CSV export placeholder
- `Dodaj płatność` action
- filter panel and payments table
- right summary card for the selected month

## Filters

Filters are submitted with GET and rendered with reusable form components.

Available filters:

- search by student first name, last name, phone, Telegram, or payment number
- month
- payment method
- status
- subscription
- reset filters

Pagination uses SQL `LIMIT` and `OFFSET`.

## Table

Columns:

- Numer
- Data
- Uczennica
- Telefon / Telegram
- Abonament
- Kwota
- Metoda
- Status
- Akcje

Rows use payment status badges:

- `completed`: Opłacona
- `pending`: Oczekuje
- `partial`: Częściowa
- `refunded`: Zwrócona
- `cancelled`: Anulowana

## Add Payment Modal

The add modal uses standard POST/Redirect/GET.

Fields:

- Uczennica
- Abonament
- Kwota
- Data płatności
- Miesiąc / okres
- Metoda płatności
- Status
- Komentarz

Selecting a student fills the current subscription and amount when available. Selecting a subscription fills the amount from the subscription plan price.

Supported active payment plans:

- `4 zajęcia`: 130 PLN, monthly group subscription
- `8 zajęć`: 180 PLN, monthly group subscription
- `Indywidualne zajęcia`: 120 PLN, one individual lesson
- `Indywidualne 4 zajęcia`: 390 PLN, four individual lessons valid for 30 days

Monthly subscriptions use the selected month as the payment period. A one-time individual lesson uses the payment date as both period start and end. The four-lesson individual package starts on the payment date and ends after 30 days.

## Edit Payment Modal

Editable fields:

- amount
- payment date
- payment method
- status
- comment

The payment number is displayed but not editable.

## Payment Number Logic

Payment numbers are generated in `PaymentRepository::nextPaymentNumber()`.

Format:

```text
PAY-YYYY-000001
```

The year comes from the payment date. The sequence is based on the largest existing payment number for that year.

## Database Integration

The module uses:

- `payments`
- `students`
- `student_groups`
- `subscriptions`
- `payment_methods`

All SQL lives in `PaymentRepository`. `pages/payments.php` does not contain inline SQL.

## Cancellation

Payments are never permanently deleted through the UI. Cancel action sets:

```text
status = cancelled
```

## Student Profile Integration

`pages/student_profile.php` displays real payments for the selected student through `StudentService` and `StudentRepository`.

The profile tab action links to:

```text
pages/payments.php?modal=add&student_id={student_id}
```

## Future Improvements

- CSV export
- receipt PDF generation
- AJAX student/subscription selector
- payment audit log
- dashboard charts using `PaymentService::monthlyRevenue()`
- group revenue chart using `PaymentService::revenueByGroup()`
- duplicate payment detection by student, subscription, and period
