# MVC Lite Architecture

Limodelia CRM uses a lightweight MVC-style structure without a framework. The goal is to keep `pages/*.php` focused on request handling and rendering, while reusable business logic lives under `app/`.

## Folder Structure

```text
app/
├── Controllers/
├── Repositories/
├── Services/
├── Validators/
└── Helpers/
```

## Pages

Files in `pages/` are thin presentation entry points.

Responsibilities:

- read `GET` and `POST` values
- call services
- pass prepared data to the existing UI markup
- keep POST/Redirect/GET flow
- render layout through `render_page()`

Pages must not contain SQL queries or transaction logic.

## Repositories

Repositories own database access.

Responsibilities:

- execute prepared SQL statements
- bind parameters
- return arrays for pages and services
- keep joins and pagination SQL in one reusable place
- keep soft delete updates consistent

Current repositories:

- `StudentRepository`
- `GroupRepository`
- `SubscriptionRepository`

## Services

Services own business workflows.

Responsibilities:

- coordinate repositories
- run validators
- normalize input data
- manage transactions through repositories
- save uploaded/cropped student photos
- return validation or workflow errors to pages

Current service:

- `StudentService`

## Validators

Validators own form validation rules.

Current validator:

- `StudentValidator`

It validates required student fields, dates, group, subscription, and status.

## Helpers

Helpers provide reusable formatting and response utilities.

Current helpers:

- `FormatHelper`: escaping, dates, money, status labels, badges, initials, Telegram formatting.
- `ResponseHelper`: redirects with optional query parameters.

## Students Module Pattern

The Students module now follows MVC Lite:

- `pages/students.php` handles request state and renders the list/modal UI.
- `StudentService` handles add, edit, soft delete, filters, pagination, and profile data orchestration.
- `StudentRepository` contains all student SQL.
- `StudentValidator` contains student form validation.
- `FormatHelper` formats display values.

## Future Modules

Payments, Expenses, Trips, Salaries, and Reports should follow the same pattern:

- create one repository per database area
- create one service per workflow-heavy module
- create validators for every write form
- keep pages free of SQL
- keep UI classes and layout markup in pages
- use prepared statements only
- keep POST/Redirect/GET for non-AJAX forms
- keep delete operations soft when business history must remain auditable

## Database Connection

All repositories receive the existing PDO connection from `config/db.php`.

No new connection layer should be introduced unless the project later adopts a framework or dependency container.
