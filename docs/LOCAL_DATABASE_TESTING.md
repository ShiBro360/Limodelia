# Local Database Testing with XAMPP and phpMyAdmin

This guide explains how to test the Limodelia CRM database locally with XAMPP and phpMyAdmin.

Use these files:

- `database/database.sql`
- `database/seed.sql`

Import `database.sql` first. Import `seed.sql` second.

## 1. Start Apache and MySQL in XAMPP

1. Open the XAMPP Control Panel.
2. Find the `Apache` row.
3. Click `Start`.
4. Wait until Apache turns green.
5. Find the `MySQL` row.
6. Click `Start`.
7. Wait until MySQL turns green.

If both Apache and MySQL are green, XAMPP is running correctly.

## 2. Open phpMyAdmin

1. Open your browser.
2. Go to:

```text
http://localhost/phpmyadmin
```

3. phpMyAdmin should open.
4. If it asks for login credentials, use your local MySQL credentials.

Default XAMPP credentials are often:

```text
User: root
Password: empty
```

## 3. Import `database/database.sql`

1. In phpMyAdmin, click the `Import` tab.
2. Click `Choose File`.
3. Select:

```text
C:\Users\shibr\OneDrive\Documents\New project\Limodelia\database\database.sql
```

4. Make sure the format is `SQL`.
5. Click `Import`.
6. Wait for the import to finish.

Expected result:

- phpMyAdmin shows a success message.
- A database named `limodelia_crm` appears in the left sidebar.
- The database contains the CRM tables.

## 4. Import `database/seed.sql`

1. In the left sidebar, click the `limodelia_crm` database.
2. Click the `Import` tab.
3. Click `Choose File`.
4. Select:

```text
C:\Users\shibr\OneDrive\Documents\New project\Limodelia\database\seed.sql
```

5. Make sure the format is `SQL`.
6. Click `Import`.
7. Wait for the import to finish.

Expected result:

- phpMyAdmin shows a success message.
- Default roles, admin user, settings, groups, subscriptions, payment methods, expense categories, and salary rules are inserted.

## 5. Verify That All Tables Were Created

1. Click the `limodelia_crm` database in the left sidebar.
2. Open the `Structure` tab.
3. Confirm that these tables exist:

```text
activity_logs
expense_categories
expenses
marketing_campaigns
notifications
payment_methods
payments
reports
roles
salary_payouts
salary_rules
sessions
settings
student_groups
student_subscriptions
students
subscriptions
system_logs
trip_photos
trips
user_roles
users
```

There should be 22 tables.

## 6. Check That Seed Data Exists

In phpMyAdmin:

1. Click the `limodelia_crm` database.
2. Click the `SQL` tab.
3. Run each query below.

Check default administrator:

```sql
SELECT id, name, email, status
FROM users;
```

Expected result:

- One user exists.
- Email is `admin@limodelia.local`.
- Status is `active`.

Check roles:

```sql
SELECT slug, name, status
FROM roles
ORDER BY id;
```

Expected roles:

```text
owner
administrator
finance
teacher
marketing
viewer
```

Check administrator role assignment:

```sql
SELECT u.email, r.slug
FROM user_roles ur
JOIN users u ON u.id = ur.user_id
JOIN roles r ON r.id = ur.role_id;
```

Expected result:

- `admin@limodelia.local` has the `owner` role.

Check student groups:

```sql
SELECT name, slug, status
FROM student_groups;
```

Expected groups:

```text
Dorosłe
Dziecięca
```

Check subscription plans:

```sql
SELECT name, lessons_count, price, currency
FROM subscriptions;
```

Expected plans:

```text
4 zajęcia | 4 | 130.00 | PLN
8 zajęć  | 8 | 180.00 | PLN
```

Check payment methods:

```sql
SELECT name, slug
FROM payment_methods
ORDER BY sort_order;
```

Expected methods:

```text
Gotówka
Karta
Przelew
BLIK
```

Check expense categories:

```sql
SELECT name, slug
FROM expense_categories
ORDER BY sort_order;
```

Expected categories:

```text
Wynajem
Marketing
Księgowość
Wyjazdy
Sprzęt
Podatki
Inne
```

Check school settings:

```sql
SELECT setting_key, setting_value
FROM settings
WHERE setting_key LIKE 'school.%'
ORDER BY setting_key;
```

Expected settings:

```text
school.email
school.instagram
school.logo_url
school.name
school.nip
school.phone
school.regon
```

## 7. Test Foreign Keys

Foreign keys protect the database from invalid relationships.

Use phpMyAdmin's `SQL` tab and run these tests.

### Test 1: Invalid Student Group

Run:

```sql
INSERT INTO students (
  student_group_id,
  first_name,
  last_name,
  enrollment_date
) VALUES (
  999999,
  'Test',
  'Invalid Group',
  CURRENT_DATE
);
```

Expected result:

- MySQL rejects the insert.
- This confirms that `students.student_group_id` must reference a real group.

### Test 2: Valid Student

Run:

```sql
INSERT INTO students (
  student_group_id,
  first_name,
  last_name,
  enrollment_date,
  created_by
) VALUES (
  1,
  'Test',
  'Student',
  CURRENT_DATE,
  1
);
```

Expected result:

- MySQL accepts the insert.

### Test 3: Invalid Student Subscription

Run:

```sql
INSERT INTO student_subscriptions (
  student_id,
  subscription_id,
  start_date,
  created_by
) VALUES (
  999999,
  1,
  CURRENT_DATE,
  1
);
```

Expected result:

- MySQL rejects the insert.
- This confirms that `student_subscriptions.student_id` must reference a real student.

### Test 4: Invalid Date Range

Run:

```sql
INSERT INTO student_subscriptions (
  student_id,
  subscription_id,
  start_date,
  end_date,
  created_by
) VALUES (
  1,
  1,
  '2026-06-25',
  '2026-06-01',
  1
);
```

Expected result:

- MySQL rejects the insert.
- This confirms that `end_date` cannot be earlier than `start_date`.

### Test 5: Invalid Payment Method

Run:

```sql
INSERT INTO payments (
  payment_number,
  student_id,
  subscription_id,
  payment_method_id,
  amount,
  payment_date,
  created_by
) VALUES (
  'TEST-001',
  1,
  1,
  999999,
  130.00,
  CURRENT_DATE,
  1
);
```

Expected result:

- MySQL rejects the insert.
- This confirms that `payments.payment_method_id` must reference a real payment method.

### Test 6: Valid Payment

Run:

```sql
INSERT INTO payments (
  payment_number,
  student_id,
  subscription_id,
  payment_method_id,
  amount,
  payment_date,
  created_by
) VALUES (
  'TEST-001',
  1,
  1,
  1,
  130.00,
  CURRENT_DATE,
  1
);
```

Expected result:

- MySQL accepts the insert.

### Clean Up Test Data

After testing, remove test records:

```sql
DELETE FROM payments WHERE payment_number = 'TEST-001';
DELETE FROM student_subscriptions WHERE student_id = 1 AND created_by = 1;
DELETE FROM students WHERE first_name = 'Test' AND last_name IN ('Student', 'Invalid Group');
```

Some failed inserts will not create records, so it is normal if one of these delete queries removes `0` rows.

## 8. Common Errors and How to Fix Them

### Error: Cannot connect to MySQL

Cause:

- MySQL is not running in XAMPP.

Fix:

1. Open XAMPP Control Panel.
2. Click `Start` next to MySQL.
3. Refresh phpMyAdmin.

### Error: Apache does not start

Cause:

- Port 80 or 443 is already used by another program.

Fix:

1. Close programs that may use web ports, such as Skype, IIS, or another local server.
2. Restart Apache in XAMPP.
3. If needed, change Apache ports in XAMPP configuration.

### Error: MySQL does not start

Cause:

- Port 3306 is already used.
- MySQL data files may be locked or damaged.

Fix:

1. Close other MySQL or MariaDB services.
2. Restart XAMPP as administrator.
3. Check XAMPP MySQL logs from the XAMPP Control Panel.

### Error: Unknown database `limodelia_crm`

Cause:

- `database.sql` was not imported yet.

Fix:

1. Import `database/database.sql`.
2. Then import `database/seed.sql`.

### Error: Table already exists

Cause:

- The schema was imported before.

Fix:

- This schema includes `DROP TABLE IF EXISTS`, so importing `database.sql` again should recreate the tables.
- Be careful: this removes existing local test data.

### Error: Cannot add or update a child row

Cause:

- A foreign key value does not exist in the parent table.

Example:

- You inserted a payment with a non-existing `student_id`.
- You inserted a student subscription with a non-existing `subscription_id`.

Fix:

1. Check that the parent record exists.
2. Use the correct ID.
3. Import `seed.sql` if required default data is missing.

### Error: Cannot delete or update a parent row

Cause:

- Another table depends on this record.

Example:

- A payment method cannot be deleted if payments use it.
- A student cannot be deleted if payments reference the student.

Fix:

- Do not delete historical records.
- Mark records inactive or archived instead.
- Delete dependent test records first if this is only local testing.

### Error: Check constraint is violated

Cause:

- A value breaks a database rule.

Examples:

- Payment amount is `0`.
- Subscription price is negative.
- End date is earlier than start date.

Fix:

1. Read the error message.
2. Find the failing field.
3. Correct the value.
4. Run the query again.

### Error: Duplicate entry

Cause:

- A unique value already exists.

Examples:

- Duplicate user email.
- Duplicate role slug.
- Duplicate payment number.

Fix:

- Use a different unique value.
- Or update the existing record instead of inserting a new one.

### Error: Invalid JSON text

Cause:

- A JSON column received invalid JSON.

Fix:

- Use valid JSON syntax.

Valid example:

```json
{"status": "ok"}
```

Invalid example:

```text
{status: ok}
```

### Polish Characters Look Broken

Cause:

- Browser, file, or database connection is not using UTF-8.

Fix:

1. Make sure the SQL files are saved as UTF-8.
2. Make sure the database uses `utf8mb4_unicode_ci`.
3. Confirm that `SET NAMES utf8mb4` exists in `database.sql`.
4. Re-import `database.sql`.
5. Re-import `seed.sql`.

## Final Success Checklist

The local database test is successful when:

- Apache is running.
- MySQL is running.
- phpMyAdmin opens.
- Database `limodelia_crm` exists.
- All 22 tables exist.
- Default administrator exists.
- Default roles exist.
- Default administrator has the owner role.
- Student groups exist.
- Subscription plans exist.
- Payment methods exist.
- Expense categories exist.
- School settings exist.
- Invalid foreign key test queries fail.
- Valid test queries succeed.
