<?php

declare(strict_types=1);

/**
 * Limodelia CRM — demo data seeder.
 *
 * Fills a LOCAL DEMO database with fictional staff, students, groups, lessons,
 * attendance, payments, salaries and expenses. It never reads or writes the
 * production database, and it refuses to run unless the database name looks
 * like a demo copy.
 *
 * Usage:
 *   php database/seed_demo.php --list
 *   php database/seed_demo.php
 *   php database/seed_demo.php --students=24 --weeks=10
 *   php database/seed_demo.php --reset --yes
 *
 * Connection (environment variables, no secrets in the repository):
 *   DEMO_DSN   default mysql:host=127.0.0.1;dbname=limodelia_demo;charset=utf8mb4
 *   DEMO_USER  default root
 *   DEMO_PASS  default empty
 *
 * Every person, phone number and e-mail below is invented. E-mail addresses use
 * example.com, which is reserved for documentation and cannot receive mail.
 * The demo staff password is printed at the end and works on the demo database
 * only.
 */

const DEMO_PASSWORD   = 'Demo1234!';
const DEFAULT_STUDENTS = 30;
const DEFAULT_WEEKS    = 8;
const MONTHS_BACK      = 4;

/** Salary model of the demo school: see App\Services\SalaryService::preview(). */
const SALARY_ACCOUNTANT_FEE = 600.00;
const SALARY_MARKETING_FEE  = 300.00;
const SALARY_TEACHER_SHARE   = 40.0; // percent of the profit left after the fixed fees

/** Databases that must never be seeded, even if the name matches the pattern. */
const BLOCKED_DATABASES = [
    'limodelia', 'limodelia_crm', 'limodelia_prod', 'mediplus',
    'mysql', 'information_schema', 'performance_schema', 'sys',
];

/** Tables the seeder writes to, children first (used by --reset). */
const SEED_TABLES = [
    'trip_participants',
    'trips',
    'marketing_campaigns',
    'expenses',
    'expense_categories',
    'salary_payouts',
    'salary_rules',
    'attendance_records',
    'payments',
    'payment_methods',
    'student_subscriptions',
    'students',
    'subscriptions',
    'student_groups',
    'settings',
    'user_roles',
    'roles',
    'users',
];

$opts = parse_args($argv);
$dsn  = getenv('DEMO_DSN') ?: 'mysql:host=127.0.0.1;dbname=limodelia_demo;charset=utf8mb4';
$user = getenv('DEMO_USER') ?: 'root';
$pass = getenv('DEMO_PASS') ?: '';

$dbName = dsn_database($dsn);
assert_demo_database($dbName);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fail("Cannot connect to `{$dbName}`: " . $e->getMessage());
}

// SHOW TABLES returns one column whose name depends on the database, so read it positionally.
$existing = [];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
    $existing[] = (string) $row[0];
}

$required = ['users', 'roles', 'student_groups', 'subscriptions', 'students',
    'payments', 'payment_methods', 'attendance_records', 'salary_rules',
    'salary_payouts', 'expenses', 'expense_categories', 'trips'];

if ($opts['list'] === true) {
    echo "Database: {$dbName}\n";
    echo 'Tables: ' . count($existing) . "\n\n";
    foreach ($existing as $table) {
        $cols = columns($pdo, $table);
        $mark = in_array($table, SEED_TABLES, true) ? 'seed' : '    ';
        echo "[{$mark}] {$table} (" . count($cols) . " columns)\n";
        echo '         ' . implode(', ', $cols) . "\n";
    }
    $missing = array_values(array_diff($required, $existing));
    echo "\nMissing tables: " . ($missing === [] ? 'none' : implode(', ', $missing)) . "\n";
    exit(0);
}

$missing = array_values(array_diff($required, $existing));
if ($missing !== []) {
    fail(
        "This database is missing tables: " . implode(', ', $missing) . "\n" .
        "Create the demo database first:\n" .
        "  mysql -u root -e \"CREATE DATABASE limodelia_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\"\n" .
        "  mysql -u root limodelia_demo < /tmp/limodelia_demo_schema.sql  (see PUBLISH.md)"
    );
}

if ($opts['reset'] === true) {
    if ($opts['yes'] !== true) {
        fail('--reset deletes every row in the demo tables. Confirm with --yes.');
    }
    reset_tables($pdo);
    echo "Demo tables truncated.\n";
}

$studentCount = max(1, min(200, (int) ($opts['students'] ?? DEFAULT_STUDENTS)));
$weeks        = max(1, min(26, (int) ($opts['weeks'] ?? DEFAULT_WEEKS)));

mt_srand(20261121);

$today     = new DateTimeImmutable('today');
$monday    = $today->modify('monday this week');
$counts    = [];
$password  = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    // ------------------------------------------------------------------ roles
    $roleIds = [];
    foreach ([
        'administrator' => ['Administrator', 'Full access to CRM modules, settings and finances.'],
        'teacher'       => ['Teacher', 'Access to own groups, attendance and own salary summaries.'],
        'finance'       => ['Finance', 'Access to payments, expenses and financial reports.'],
        'marketing'     => ['Marketing', 'Access to campaigns, leads and marketing reports.'],
    ] as $slug => [$roleName, $description]) {
        $roleIds[$slug] = ensure_row($pdo, 'roles', 'slug', $slug, [
            'name'        => $roleName,
            'slug'        => $slug,
            'description' => $description,
            'status'      => 'active',
        ]);
    }

    // ------------------------------------------------------------------ users
    $adminId = insert_row($pdo, 'users', [
        'name'          => 'Demo Administrator',
        'email'         => 'admin@example.com',
        'password_hash' => $password,
        'phone'         => fake_phone(),
        'status'        => 'active',
    ]);
    attach_role($pdo, $adminId, $roleIds['administrator'], $adminId);
    $counts['users'] = ($counts['users'] ?? 0) + 1;

    $teachers = [
        ['Anna', 'Kowalska', 75.00],
        ['Marek', 'Wiśniewski', 80.00],
        ['Julia', 'Zielińska', 70.00],
    ];
    $teacherIds = [];
    foreach ($teachers as $i => [$first, $last, $rate]) {
        $teacherId = insert_row($pdo, 'users', [
            'name'          => $first . ' ' . $last,
            'email'         => sprintf('teacher%d@example.com', $i + 1),
            'password_hash' => $password,
            'phone'         => fake_phone(),
            'status'        => 'active',
        ]);
        attach_role($pdo, $teacherId, $roleIds['teacher'], $adminId);
        $teacherIds[] = $teacherId;
        $counts['users'] = ($counts['users'] ?? 0) + 1;
    }

    $accountantId = insert_row($pdo, 'users', [
        'name'          => 'Demo Księgowa',
        'email'         => 'accountant@example.com',
        'password_hash' => $password,
        'phone'         => fake_phone(),
        'status'        => 'active',
    ]);
    attach_role($pdo, $accountantId, $roleIds['finance'], $adminId);
    $counts['users'] = ($counts['users'] ?? 0) + 1;

    $marketingId = insert_row($pdo, 'users', [
        'name'          => 'Demo Marketing',
        'email'         => 'marketing@example.com',
        'password_hash' => $password,
        'phone'         => fake_phone(),
        'status'        => 'active',
    ]);
    attach_role($pdo, $marketingId, $roleIds['marketing'], $adminId);
    $counts['users'] = ($counts['users'] ?? 0) + 1;

    // --------------------------------------------------------------- settings
    foreach ([
        ['app.name', 'Limodelia CRM', 'general'],
        ['app.locale', 'pl_PL', 'general'],
        ['app.currency', 'PLN', 'finance'],
    ] as [$key, $value, $group]) {
        ensure_row($pdo, 'settings', 'setting_key', $key, [
            'setting_key'   => $key,
            'setting_value' => $value,
            'setting_group' => $group,
            'value_type'    => 'string',
            'is_private'    => 0,
            'updated_by'    => $adminId,
        ]);
    }

    // --------------------------------------------------------- student groups
    // [name, slug, colour, max students, age from, age to, weekdays (ISO), tariff slug]
    $groups = [
        ['Dzieci 6–9',               'dzieci-6-9',            '#f4a261', 12, 6,  9,  [2, 4], 'karnet-8'],
        ['Dzieci 10–13',             'dzieci-10-13',          '#e76f51', 12, 10, 13, [2, 4], 'karnet-8'],
        ['Młodzież 14–17',           'mlodziez-14-17',        '#2a9d8f', 12, 14, 17, [1, 3], 'karnet-8'],
        ['Dorośli — podstawy',       'dorosli-podstawy',      '#457b9d', 10, 18, 45, [1, 3], 'karnet-12'],
        ['Dorośli — wybieg',         'dorosli-wybieg',        '#1d3557', 10, 18, 45, [2],   'karnet-12'],
        ['Przygotowanie do pokazów', 'przygotowanie-pokazy',  '#6d597a', 8,  12, 30, [6],   'karnet-12'],
    ];

    $groupIds = [];
    foreach ($groups as $i => [$name, $slug, $color, $max, $from, $to, , ]) {
        $groupIds[] = ensure_row($pdo, 'student_groups', 'slug', $slug, [
            'name'         => $name,
            'slug'         => $slug,
            'description'  => sprintf('Grupa demo: wiek %d–%d lat.', $from, $to),
            'status'       => 'active',
            'max_students' => $max,
            'color'        => $color,
            'is_visible'   => 1,
            'sort_order'   => $i + 1,
        ]);
        $counts['student_groups'] = ($counts['student_groups'] ?? 0) + 1;
    }

    // ---------------------------------------------------------- subscriptions
    $subscriptions = [
        ['Karnet 4 zajęcia',  'karnet-4',        4,  220.00, 'monthly', 'Cztery zajęcia w miesiącu.'],
        ['Karnet 8 zajęć',    'karnet-8',        8,  300.00, 'monthly', 'Osiem zajęć w miesiącu.'],
        ['Karnet 12 zajęć',   'karnet-12',      12,  420.00, 'monthly', 'Dwanaście zajęć w miesiącu.'],
        ['Zajęcia próbne',    'zajecia-probne',  1,   60.00, 'once',    'Jednorazowe zajęcia próbne.'],
    ];
    $subscriptionIds = [];
    foreach ($subscriptions as $i => [$name, $slug, $lessons, $price, $period, $description]) {
        $subscriptionIds[$slug] = ensure_row($pdo, 'subscriptions', 'slug', $slug, [
            'name'           => $name,
            'slug'           => $slug,
            'lessons_count'  => $lessons,
            'price'          => $price,
            'currency'       => 'PLN',
            'billing_period' => $period,
            'status'         => 'active',
            'sort_order'     => $i + 1,
            'description'    => $description,
        ]);
        $counts['subscriptions'] = ($counts['subscriptions'] ?? 0) + 1;
    }

    // -------------------------------------------------------- payment methods
    $methodIds = [];
    foreach ([['Przelew bankowy', 'przelew'], ['Gotówka', 'gotowka'], ['BLIK', 'blik']] as $i => [$name, $slug]) {
        $methodIds[$slug] = ensure_row($pdo, 'payment_methods', 'slug', $slug, [
            'name'       => $name,
            'slug'       => $slug,
            'status'     => 'active',
            'sort_order' => $i + 1,
        ]);
        $counts['payment_methods'] = ($counts['payment_methods'] ?? 0) + 1;
    }

    // --------------------------------------------------------------- students
    $studentsByGroup = array_fill(0, count($groupIds), []);
    $studentIds      = [];
    $studentTariff   = [];

    for ($i = 0; $i < $studentCount; $i++) {
        $gi     = $i % count($groups);
        $group  = $groups[$gi];
        $person = fake_person((int) $group[4], (int) $group[5]);

        $enrolled = $today->modify('-' . mt_rand(30, 540) . ' days');
        $data = [
            'student_group_id'   => $groupIds[$gi],
            'first_name'         => $person['first'],
            'last_name'          => $person['last'],
            'birth_date'         => $person['birth_date'],
            'phone'              => fake_phone(),
            'telegram_username'  => sprintf('demo_uczen_%02d', $i + 1),
            'enrollment_date'    => $enrolled->format('Y-m-d'),
            'status'             => 'active',
            'notes'              => 'Rekord wygenerowany przez seed_demo.php. Dane fikcyjne.',
            'created_by'         => $adminId,
        ];

        if ($person['age'] < 18) {
            $guardian = fake_person(30, 50);
            $data['guardian_name']  = $guardian['first'] . ' ' . $guardian['last'];
            $data['guardian_phone'] = fake_phone();
            $data['guardian_telegram_username'] = sprintf('demo_rodzic_%02d', $i + 1);
        }

        $studentId = insert_row($pdo, 'students', $data);
        $studentIds[] = $studentId;
        $studentsByGroup[$gi][] = $studentId;
        $studentTariff[$studentId] = $group[7];

        insert_row($pdo, 'student_subscriptions', [
            'student_id'      => $studentId,
            'subscription_id' => $subscriptionIds[$group[7]],
            'start_date'      => $enrolled->format('Y-m-d'),
            'end_date'        => null,
            'status'          => 'active',
            'created_by'      => $adminId,
        ]);

        $counts['students'] = ($counts['students'] ?? 0) + 1;
        $counts['student_subscriptions'] = ($counts['student_subscriptions'] ?? 0) + 1;
    }

    // --------------------------------------------------------------- payments
    $paymentByStudentMonth = [];
    $paymentSeq = 1;
    $subscriptionPrice = [];
    foreach ($subscriptions as [$name, $slug, $lessons, $price]) {
        $subscriptionPrice[$slug] = $price;
    }

    for ($m = MONTHS_BACK; $m >= 0; $m--) {
        $monthStart = $today->modify('first day of this month')->modify("-{$m} months");
        $monthEnd   = $monthStart->modify('last day of this month');
        $ym         = $monthStart->format('Y-m');

        foreach ($studentIds as $index => $studentId) {
            $tariff = $studentTariff[$studentId];
            $amount = $subscriptionPrice[$tariff];

            // The current month is still being collected: every seventh student is pending.
            $isPending = $m === 0 && $index % 7 === 0;

            $paymentId = insert_row($pdo, 'payments', [
                'payment_number'    => sprintf('DEMO/%s/%04d', $monthStart->format('Y'), $paymentSeq++),
                'is_manual'         => 1,
                'student_id'        => $studentId,
                'subscription_id'   => $subscriptionIds[$tariff],
                'payment_method_id' => $methodIds[['przelew', 'blik', 'gotowka'][$index % 3]],
                'amount'            => $amount,
                'currency'          => 'PLN',
                'payment_date'      => $isPending
                    ? $today->format('Y-m-d')
                    : $monthStart->modify('+' . ($index % 9) . ' days')->format('Y-m-d'),
                'period_start'      => $monthStart->format('Y-m-d'),
                'period_end'        => $monthEnd->format('Y-m-d'),
                'category'          => 'subscription',
                'status'            => $isPending ? 'pending' : 'completed',
                'comment'           => 'Czesne demo za ' . $monthStart->format('m/Y'),
                'created_by'        => $adminId,
            ]);

            $paymentByStudentMonth[$studentId][$ym] = $paymentId;
            $counts['payments'] = ($counts['payments'] ?? 0) + 1;
        }
    }

    // ------------------------------------------------------------- attendance
    $sessionDates = [];
    foreach ($groups as $gi => $groupRow) {
        $weekdays = $groupRow[6];

        for ($w = $weeks; $w >= 1; $w--) {
            $weekStart = $monday->modify('-' . ($w - 1) . ' weeks');

            foreach ($weekdays as $weekday) {
                $date = $weekStart->modify('+' . ($weekday - 1) . ' days');
                if ($date > $today) {
                    continue;
                }
                $sessionDates[$gi][] = $date->format('Y-m-d');

                foreach ($studentsByGroup[$gi] as $studentId) {
                    $roll = mt_rand(1, 100);
                    if ($roll <= 88) {
                        $status = 'present';
                        $notice = null;
                        $used   = 1;
                    } elseif ($roll <= 94) {
                        $status = 'absent_notice';
                        $notice = 1;
                        $used   = 1;
                    } else {
                        $status = 'absent_no_notice';
                        $notice = null;
                        $used   = 1;
                    }

                    insert_row($pdo, 'attendance_records', [
                        'student_id'           => $studentId,
                        'payment_id'           => $paymentByStudentMonth[$studentId][$date->format('Y-m')] ?? null,
                        'lesson_date'          => $date->format('Y-m-d'),
                        'status'               => $status,
                        'counts_as_used_lesson' => $used,
                        'notice_number'        => $notice,
                        'recorded_at'          => $date->format('Y-m-d') . ' 19:30:00',
                    ]);
                    $counts['attendance_records'] = ($counts['attendance_records'] ?? 0) + 1;
                }
            }
        }
    }

    // ----------------------------------------------------------- salary rules
    // These rules use exactly the types App\Services\SalaryService prices:
    // fixed amounts for the accountant and marketing, a share of the remaining
    // profit for the teacher. Types the calculator does not price yet
    // (per_lesson, bonus, deduction) are deliberately not seeded.
    $salaryRuleIds = [
        'accountant' => ensure_row($pdo, 'salary_rules', 'name', 'Księgowość — stała kwota', [
            'name'           => 'Księgowość — stała kwota',
            'user_id'        => $accountantId,
            'role_scope'     => 'accountant',
            'rule_type'      => 'fixed',
            'amount'         => SALARY_ACCOUNTANT_FEE,
            'currency'       => 'PLN',
            'effective_from' => $today->modify('-1 year')->format('Y-m-d'),
            'status'         => 'active',
        ]),
        'marketing' => ensure_row($pdo, 'salary_rules', 'name', 'Marketing — stała kwota', [
            'name'           => 'Marketing — stała kwota',
            'user_id'        => $marketingId,
            'role_scope'     => 'marketing',
            'rule_type'      => 'fixed',
            'amount'         => SALARY_MARKETING_FEE,
            'currency'       => 'PLN',
            'effective_from' => $today->modify('-1 year')->format('Y-m-d'),
            'status'         => 'active',
        ]),
        'teacher' => ensure_row($pdo, 'salary_rules', 'name', 'Nauczycielka — procent pozostałego zysku', [
            'name'           => 'Nauczycielka — procent pozostałego zysku',
            'user_id'        => $teacherIds[0],
            'role_scope'     => 'teacher',
            'rule_type'      => 'percentage_net_profit',
            'percentage'     => SALARY_TEACHER_SHARE,
            'currency'       => 'PLN',
            'effective_from' => $today->modify('-1 year')->format('Y-m-d'),
            'status'         => 'active',
        ]),
    ];
    $counts['salary_rules'] = ($counts['salary_rules'] ?? 0) + count($salaryRuleIds);

    // --------------------------------------------------------------- expenses
    $categoryIds = [];
    foreach ([
        'czynsz'    => 'Czynsz',
        'media'     => 'Media',
        'marketing' => 'Marketing',
        'materialy' => 'Materiały',
        'uslugi'    => 'Usługi',
        'it'        => 'IT',
        'ksiegowosc' => 'Księgowość',
    ] as $slug => $name) {
        $categoryIds[$slug] = ensure_row($pdo, 'expense_categories', 'slug', $slug, [
            'name'   => $name,
            'slug'   => $slug,
            'status' => 'active',
        ]);
        $counts['expense_categories'] = ($counts['expense_categories'] ?? 0) + 1;
    }

    $expenses = [
        ['Czynsz sali',                    'czynsz',    1500.00],
        ['Prąd i ogrzewanie',              'media',      420.00],
        ['Reklama w social media',         'marketing',  300.00],
        ['Sprzątanie sali',                'uslugi',     250.00],
        ['Materiały do zajęć',             'materialy',  180.00],
        ['Domena i hosting',               'it',          90.00],
    ];
    foreach ($expenses as [$description, $slug, $amount]) {
        insert_row($pdo, 'expenses', [
            'expense_category_id' => $categoryIds[$slug],
            'payment_method_id'   => $methodIds['przelew'],
            'amount'              => $amount,
            'currency'            => 'PLN',
            'expense_date'        => $today->modify('-1 month')->format('Y-m-03'),
            'description'         => $description . ' — ' . $today->modify('-1 month')->format('m/Y'),
            'status'              => 'paid',
            'created_by'          => $adminId,
            'approved_by'         => $adminId,
            'approved_at'         => $today->modify('-1 month')->format('Y-m-04 09:00:00'),
        ]);
        $counts['expenses'] = ($counts['expenses'] ?? 0) + 1;
    }

    // --------------------------------------------------------- salary payouts
    // Amounts mirror SalaryService::preview() for the rules above, and every paid
    // payout records the linked salary expense the same way
    // SalaryService::createPayout() does — so salaries really show up in expenses.
    $revenueStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?"
    );
    $nonSalaryStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM expenses
         WHERE status NOT IN ('cancelled', 'rejected') AND is_salary = 0 AND expense_date BETWEEN ? AND ?"
    );

    for ($m = MONTHS_BACK; $m >= 1; $m--) {
        $monthStart = $today->modify('first day of this month')->modify("-{$m} months");
        $monthEnd   = $monthStart->modify('last day of this month');
        $from       = $monthStart->format('Y-m-d');
        $to         = $monthEnd->format('Y-m-d');

        $revenueStmt->execute([$from, $to]);
        $nonSalaryStmt->execute([$from, $to]);
        $profitBase  = max(0.0, (float) $revenueStmt->fetchColumn() - (float) $nonSalaryStmt->fetchColumn());
        $priorityObligations = min($profitBase, SALARY_ACCOUNTANT_FEE + SALARY_MARKETING_FEE);
        $teacherBase = max(0.0, $profitBase - SALARY_ACCOUNTANT_FEE - SALARY_MARKETING_FEE);

        $payouts = [
            ['accountant', $accountantId, 'Accountant', SALARY_ACCOUNTANT_FEE, max(0.0, $profitBase - $priorityObligations)],
            ['marketing',  $marketingId,  'Marketing specialist', SALARY_MARKETING_FEE, $profitBase],
            ['teacher',    $teacherIds[0], 'Teacher', round($teacherBase * SALARY_TEACHER_SHARE / 100, 2), $teacherBase],
        ];

        foreach ($payouts as [$role, $userId, $label, $amount, $profitBasis]) {
            if ($amount <= 0) {
                continue;
            }

            $paidAt   = $monthEnd->modify('-1 day')->format('Y-m-d') . ' 10:00:00';
            $payoutId = insert_row($pdo, 'salary_payouts', [
                'user_id'          => $userId,
                'salary_rule_id'   => $salaryRuleIds[$role],
                'period_start'     => $from,
                'period_end'       => $to,
                'base_amount'      => $amount,
                'bonus_amount'     => 0.00,
                'deduction_amount' => 0.00,
                'net_profit_base'  => round($profitBasis, 2),
                'total_amount'     => $amount,
                'currency'         => 'PLN',
                'status'           => 'paid',
                'calculated_at'    => $monthEnd->format('Y-m-d') . ' 20:00:00',
                'approved_by'      => $adminId,
                'approved_at'      => $monthEnd->format('Y-m-d') . ' 20:05:00',
                'paid_at'          => $paidAt,
                'notes'            => 'Wypłata demo (' . $role . ') za ' . $monthStart->format('m/Y') . '.',
            ]);
            $counts['salary_payouts'] = ($counts['salary_payouts'] ?? 0) + 1;

            insert_row($pdo, 'expenses', [
                'expense_category_id' => $categoryIds['ksiegowosc'],
                'payment_method_id'   => $methodIds['przelew'],
                'salary_payout_id'    => $payoutId,
                'is_salary'           => 1,
                'amount'              => $amount,
                'currency'            => 'PLN',
                'expense_date'        => substr($paidAt, 0, 10),
                'description'         => 'Wynagrodzenie - ' . $label . ' - ' . $monthStart->format('Y-m'),
                'status'              => 'paid',
                'created_by'          => $adminId,
                'approved_by'         => $adminId,
                'approved_at'         => $paidAt,
            ]);
            $counts['expenses'] = ($counts['expenses'] ?? 0) + 1;
        }
    }

    // ------------------------------------------------------ marketing & trips
    foreach ([
        ['Instagram — nabór na wrzesień', 'instagram', 600.00, 42, 7],
        ['Polecenia uczniów',             'referral',    0.00, 18, 5],
    ] as [$name, $source, $budget, $leads, $newStudents]) {
        insert_row($pdo, 'marketing_campaigns', [
            'name'              => $name,
            'source'            => $source,
            'status'            => 'completed',
            'budget'            => $budget,
            'leads_count'       => $leads,
            'new_students_count' => $newStudents,
            'currency'          => 'PLN',
            'start_date'        => $today->modify('-4 months')->format('Y-m-d'),
            'end_date'          => $today->modify('-1 month')->format('Y-m-d'),
            'description'       => 'Kampania demo.',
            'created_by'        => $adminId,
        ]);
        $counts['marketing_campaigns'] = ($counts['marketing_campaigns'] ?? 0) + 1;
    }

    $tripIds = [];
    foreach ([
        ['Wycieczka do Gdańska', 'Gdańsk',  $today->modify('+1 month'), $today->modify('+1 month +2 days'), 20, 450.00, 320.00, 'planned'],
        ['Pokaz mody w galerii', 'Bydgoszcz', $today->modify('-2 months'), $today->modify('-2 months'),     30,   0.00, 180.00, 'completed'],
    ] as [$name, $location, $start, $end, $capacity, $price, $cost, $status]) {
        $tripIds[] = insert_row($pdo, 'trips', [
            'name'          => $name,
            'location'      => $location,
            'start_date'    => $start->format('Y-m-d'),
            'end_date'      => $end->format('Y-m-d'),
            'capacity'      => $capacity,
            'price'         => $price,
            'internal_cost' => $cost,
            'currency'      => 'PLN',
            'status'        => $status,
            'description'   => 'Wydarzenie demo.',
            'created_by'    => $adminId,
        ]);
        $counts['trips'] = ($counts['trips'] ?? 0) + 1;
    }

    foreach (array_slice($studentIds, 0, 8) as $i => $studentId) {
        insert_row($pdo, 'trip_participants', [
            'trip_id'    => $tripIds[0],
            'student_id' => $studentId,
            'status'     => $i % 4 === 3 ? 'planned' : 'confirmed',
            'notes'      => null,
        ]);
        $counts['trip_participants'] = ($counts['trip_participants'] ?? 0) + 1;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('Seeding failed, nothing was saved: ' . $e->getMessage());
}

echo "Seeded database `{$dbName}`:\n";
foreach ($counts as $name => $n) {
    echo '  ' . str_pad($name, 24) . $n . "\n";
}
echo "\nDemo staff login: admin@example.com / " . DEMO_PASSWORD . "\n";
echo "Teacher logins: teacher1..3@example.com / " . DEMO_PASSWORD . "\n";
echo "All people, contacts and amounts are fictional.\n";
echo "Re-running needs --reset --yes; otherwise unique keys such as e-mail and payment number will clash.\n";

// ---------------------------------------------------------------------------

function parse_args(array $argv): array
{
    $opts = ['list' => false, 'reset' => false, 'yes' => false, 'students' => null, 'weeks' => null];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--list') {
            $opts['list'] = true;
        } elseif ($arg === '--reset') {
            $opts['reset'] = true;
        } elseif ($arg === '--yes') {
            $opts['yes'] = true;
        } elseif (preg_match('/^--students=(\d+)$/', $arg, $m)) {
            $opts['students'] = (int) $m[1];
        } elseif (preg_match('/^--weeks=(\d+)$/', $arg, $m)) {
            $opts['weeks'] = (int) $m[1];
        } else {
            fail("Unknown argument: {$arg}");
        }
    }

    return $opts;
}

function dsn_database(string $dsn): string
{
    if (preg_match('/(?:^|;)dbname=([^;]+)/', $dsn, $m)) {
        return $m[1];
    }

    fail('DSN has no dbname=; cannot verify the target database.');
}

function assert_demo_database(string $name): void
{
    if (in_array(strtolower($name), BLOCKED_DATABASES, true)) {
        fail("Refusing to seed `{$name}`: this looks like a production database.");
    }

    if (!preg_match('/(demo|dev|test|seed|sandbox)/i', $name)) {
        fail(
            "Refusing to run against `{$name}`.\n" .
            "The database name must contain demo, dev, test, seed or sandbox.\n" .
            "This seeder inserts and can truncate rows; it is not meant for production."
        );
    }
}

/** @var array<string, list<string>> */
function columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = array_column(
            $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(),
            'Field'
        );
    }

    return $cache[$table];
}

/**
 * Inserts only the fields that exist as columns, so the seeder survives small
 * schema drift without editing SQL by hand.
 */
function insert_row(PDO $pdo, string $table, array $data): int
{
    $cols = columns($pdo, $table);
    $data = array_intersect_key($data, array_flip($cols));

    if ($data === []) {
        fail("Table `{$table}` has none of the expected columns. Run --list and adjust the seeder.");
    }

    $names = array_keys($data);
    $sql   = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $names) . '`) VALUES ('
        . rtrim(str_repeat('?,', count($names)), ',') . ')';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
    } catch (PDOException $e) {
        fail("Insert into `{$table}` failed: " . $e->getMessage() . "\nSQL: {$sql}");
    }

    return (int) $pdo->lastInsertId();
}

/** Returns the id of an existing row found by a unique column, or inserts a new one. */
function ensure_row(PDO $pdo, string $table, string $uniqueColumn, string $value, array $data): int
{
    $stmt = $pdo->prepare("SELECT `id` FROM `{$table}` WHERE `{$uniqueColumn}` = ? LIMIT 1");
    $stmt->execute([$value]);
    $id = $stmt->fetchColumn();

    return $id === false ? insert_row($pdo, $table, $data) : (int) $id;
}

function attach_role(PDO $pdo, int $userId, int $roleId, ?int $assignedBy): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `assigned_by`) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, $roleId, $assignedBy]);
}

function reset_tables(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (SEED_TABLES as $table) {
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function fake_phone(): string
{
    return sprintf('+48 %03d %03d %03d', mt_rand(500, 579), mt_rand(100, 999), mt_rand(100, 999));
}

/** @return array{first: string, last: string, age: int, birth_date: string} */
function fake_person(int $minAge, int $maxAge): array
{
    $femaleFirst = ['Anna', 'Katarzyna', 'Maria', 'Zofia', 'Julia', 'Oliwia', 'Maja', 'Hanna', 'Iryna', 'Olena'];
    $maleFirst   = ['Piotr', 'Michał', 'Jakub', 'Szymon', 'Antoni', 'Filip', 'Jan', 'Dmytro', 'Tomasz', 'Adam'];
    $lastFemale  = ['Nowak', 'Kowalska', 'Wiśniewska', 'Wójcik', 'Kamińska', 'Lewandowska', 'Zielińska', 'Szymańska', 'Shevchenko'];
    $lastMale    = ['Nowak', 'Kowalski', 'Wiśniewski', 'Wójcik', 'Kamiński', 'Lewandowski', 'Zieliński', 'Szymański', 'Shevchenko'];

    $isFemale = mt_rand(0, 1) === 1;
    $first    = $isFemale
        ? $femaleFirst[array_rand($femaleFirst)]
        : $maleFirst[array_rand($maleFirst)];
    $last     = $isFemale
        ? $lastFemale[array_rand($lastFemale)]
        : $lastMale[array_rand($lastMale)];

    $age = mt_rand($minAge, $maxAge);
    $birthDate = (new DateTimeImmutable('today'))
        ->modify('-' . ($age * 12 + mt_rand(0, 11)) . ' months')
        ->format('Y-m-d');

    return ['first' => $first, 'last' => $last, 'age' => $age, 'birth_date' => $birthDate];
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
