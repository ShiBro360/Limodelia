<?php

require_once __DIR__ . '/../includes/page.php';

$dashboardData = null;
$dashboardError = null;
$attendanceErrors = [];
$attendanceStudents = [];
$attendanceDate = (isset($_GET['attendance_modal'], $_GET['attendance_date']) && (string) $_GET['attendance_modal'] === '1')
    ? (string) $_GET['attendance_date']
    : '';

try {
    $pdo = db();
    $salaryService = new \App\Services\SalaryService(
        new \App\Repositories\SalaryRepository($pdo),
        new \App\Validators\SalaryValidator()
    );
    $dashboardService = new \App\Services\DashboardService(
        new \App\Repositories\DashboardRepository($pdo),
        $salaryService
    );
    $attendanceService = new \App\Services\AttendanceService(new \App\Repositories\AttendanceRepository($pdo));

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_attendance') {
        $attendanceDate = (string) ($_POST['lesson_date'] ?? '');
        if (!csrf_token_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
            $attendanceErrors = ['Sesja wygasła. Odśwież stronę i spróbuj ponownie.'];
        } else {
            $attendanceErrors = ($_POST['cancel_lesson'] ?? '') === '1'
                ? $attendanceService->cancelLesson($attendanceDate)
                : $attendanceService->save($attendanceDate, $_POST);
        }
        if ($attendanceErrors === []) {
            header('Location: dashboard.php?' . http_build_query([
                'month' => substr($attendanceDate, 0, 7),
                'attendance_saved' => 1,
            ]));
            exit;
        }
    }

    $selectedMonth = dashboard_valid_month((string) ($_GET['month'] ?? '')) ?: date('Y-m');
    $selectedDate = new DateTimeImmutable($selectedMonth . '-01');
    $chartYear = max(2020, min(2100, (int) ($_GET['chart_year'] ?? $selectedDate->format('Y'))));
    $dashboardData = $dashboardService->data($selectedDate, $chartYear);
    $dashboardData['school_cancelled_dates'] = $attendanceService->schoolCancelledDates(
        $selectedDate->format('Y-m-d'),
        $selectedDate->modify('last day of this month')->format('Y-m-d')
    );

    if ($attendanceDate !== '') {
        $attendanceStudents = $attendanceService->studentsForLesson($attendanceDate);
    }
} catch (Throwable $exception) {
    $dashboardError = $exception->getMessage();
    $selectedMonth = date('Y-m');
    $selectedDate = new DateTimeImmutable($selectedMonth . '-01');
    $chartYear = (int) date('Y');
    $dashboardData = dashboard_empty_data($selectedDate);
}

render_page('Panel główny', 'dashboard', function () use ($dashboardData, $dashboardError, $attendanceErrors, $attendanceStudents, $attendanceDate, $selectedMonth, $chartYear): void {
    $period = $dashboardData['period'];
    $kpis = $dashboardData['kpis'];
    $limit = $dashboardData['activity_limit'];
    $salaries = $dashboardData['salaries'];
    $periodLabel = dashboard_period_label((string) $period['from']);
    $monthName = dashboard_month_name((int) date('n', strtotime((string) $period['from'])));
    $monthYear = $monthName . ' ' . date('Y', strtotime((string) $period['from']));
    $calendarDate = new DateTimeImmutable($selectedMonth . '-01');
    $previousCalendarMonth = $calendarDate->modify('-1 month');
    $nextCalendarMonth = $calendarDate->modify('+1 month');
    ?>
    <div class="dashboard-compact">
        <section class="module-hero dashboard-hero">
            <div>
                <h1>Witaj, Limodelia! 👑</h1>
                <p>Oto podsumowanie Twojej szkoły modelek.</p>
            </div>
            <form class="date-select" method="get">
                <span aria-hidden="true"><?= \App\Helpers\IconHelper::svg('calendar') ?></span>
                <input type="month" name="month" value="<?= \App\Helpers\FormatHelper::e($selectedMonth) ?>" onchange="this.form.submit()">
                <input type="hidden" name="chart_year" value="<?= \App\Helpers\FormatHelper::e((string) $chartYear) ?>">
                <strong><?= \App\Helpers\FormatHelper::e($periodLabel) ?></strong>
            </form>
        </section>

        <?php if ($dashboardError !== null): ?>
            <div class="alert alert-warning">Nie udało się pobrać danych Dashboardu. <?= \App\Helpers\FormatHelper::e($dashboardError) ?></div>
        <?php endif; ?>

        <section class="kpi-grid">
            <?= dashboard_kpi('money', 'Przychód', \App\Helpers\FormatHelper::money((float) $kpis['revenue']), 'Bieżący miesiąc') ?>
            <?= dashboard_kpi('expense', 'Wydatki', \App\Helpers\FormatHelper::money((float) $kpis['expenses']), 'Bieżący miesiąc') ?>
            <?= dashboard_kpi('profit', 'Zysk netto', \App\Helpers\FormatHelper::money((float) $kpis['profit']), 'Przychód - wydatki') ?>
            <?= dashboard_kpi('users', 'Aktywne uczennice', (string) $kpis['active_students'], 'Status aktywna') ?>
        </section>

        <section class="dashboard-grid">
            <article class="card dashboard-card-sm chart-card dashboard-chart-sm">
                    <div class="card-header">
                        <h2 class="card-title">Zysk netto szkoły w czasie</h2>
                        <form method="get" class="select-inline">
                            <input type="hidden" name="month" value="<?= \App\Helpers\FormatHelper::e($selectedMonth) ?>">
                            <select class="select" name="chart_year" onchange="this.form.submit()">
                                <?php for ($year = (int) date('Y') - 2; $year <= (int) date('Y') + 1; $year++): ?>
                                    <option value="<?= $year ?>" <?= $chartYear === $year ? 'selected' : '' ?>><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </form>
                    </div>
                    <?= dashboard_revenue_chart($dashboardData['monthly_net_profit']) ?>
            </article>

            <article class="card dashboard-card-sm group-card dashboard-chart-sm">
                        <h2 class="card-title">Przychód według grup</h2>
                        <?php $groupTotal = (float) $dashboardData['revenue_by_group']['total']; ?>
                        <div class="donut-layout">
                            <div class="donut-chart" style="background: <?= \App\Helpers\FormatHelper::e(dashboard_group_gradient($dashboardData['revenue_by_group']['items'])) ?>">
                                <div class="donut-center">
                                    <strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money($groupTotal)) ?></strong>
                                    <span>Łącznie</span>
                                </div>
                            </div>
                            <div class="legend-list">
                                <?php if ($groupTotal <= 0): ?>
                                    <p class="muted">Brak przychodu w tym miesiącu.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['revenue_by_group']['items'] as $index => $item): ?>
                                        <div class="legend-item">
                                            <i style="background: <?= \App\Helpers\FormatHelper::e(dashboard_group_color($index)) ?>"></i>
                                            <span><?= \App\Helpers\FormatHelper::e((string) $item['name']) ?><small><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $item['revenue'])) ?></small></span>
                                            <strong><?= \App\Helpers\FormatHelper::e(number_format((float) $item['percent'], 1, ',', ' ')) ?>%</strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
            </article>

            <article class="card dashboard-card-sm payments-card">
                        <div class="card-header">
                            <h2 class="card-title">Ostatnie płatności</h2>
                            <a href="payments.php">Zobacz wszystkie</a>
                        </div>
                        <div class="activity-list dashboard-list-compact">
                            <?php if ($dashboardData['recent_payments'] === []): ?>
                                <div class="empty-state compact">Brak płatności.</div>
                            <?php else: ?>
                                <?php foreach ($dashboardData['recent_payments'] as $payment): ?>
                                    <?php
                                    $studentName = trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? ''));
                                    $photoPath = (string) ($payment['photo_path'] ?? '');
                                    ?>
                                    <div class="activity-item payment-activity-item">
                                        <?php if ($photoPath !== ''): ?>
                                            <img class="avatar avatar-sm avatar-photo" src="<?= \App\Helpers\FormatHelper::e(url($photoPath)) ?>" alt="">
                                        <?php else: ?>
                                            <span class="avatar avatar-sm"><?= \App\Helpers\FormatHelper::e(dashboard_initials($payment['first_name'] ?? '', $payment['last_name'] ?? '')) ?></span>
                                        <?php endif; ?>
                                        <div class="activity-primary">
                                            <strong><?= \App\Helpers\FormatHelper::e($studentName) ?></strong>
                                            <span><?= \App\Helpers\FormatHelper::e(($payment['subscription_name'] ?? '-') . ' · ' . ($payment['group_name'] ?? '-')) ?></span>
                                        </div>
                                        <div class="activity-secondary">
                                            <span class="activity-date"><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::date((string) $payment['payment_date'])) ?></span>
                                            <div class="activity-amount">
                                                <strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $payment['amount'])) ?></strong>
                                                <span><?= \App\Helpers\FormatHelper::e((string) ($payment['method_name'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-outline btn-block" href="payments.php">Zobacz wszystkie płatności</a>
            </article>

            <article class="card dashboard-card-sm expenses-card">
                        <div class="card-header">
                            <h2 class="card-title">Ostatnie wydatki</h2>
                            <a href="expenses.php">Zobacz wszystkie</a>
                        </div>
                        <div class="activity-list dashboard-list-compact">
                            <?php if ($dashboardData['recent_expenses'] === []): ?>
                                <div class="empty-state compact">Brak wydatków.</div>
                            <?php else: ?>
                                <?php foreach ($dashboardData['recent_expenses'] as $expense): ?>
                                    <div class="activity-item expense-activity-item">
                                        <span class="expense-icon" aria-hidden="true"><?= \App\Helpers\IconHelper::svg(dashboard_expense_icon((string) ($expense['category_slug'] ?? ''))) ?></span>
                                        <div class="activity-primary">
                                            <strong><?= \App\Helpers\FormatHelper::e((string) $expense['description']) ?></strong>
                                        </div>
                                        <div class="activity-secondary">
                                            <span class="activity-date"><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::date((string) $expense['expense_date'])) ?></span>
                                            <div class="activity-amount">
                                                <strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $expense['amount'])) ?></strong>
                                                <span><?= \App\Helpers\FormatHelper::e((string) ($expense['method_name'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-outline btn-block" href="expenses.php">Zobacz wszystkie wydatki</a>
            </article>

            <aside class="dashboard-side">
                <article class="card dashboard-card-sm limit-card">
                    <h2 class="card-title">Limit działalności nierejestrowanej</h2>
                    <div class="progress-bar" role="progressbar" aria-label="Wykorzystanie limitu" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= \App\Helpers\FormatHelper::e((string) round((float) $limit['percent'], 1)) ?>">
                        <span class="progress-fill" style="width: <?= \App\Helpers\FormatHelper::e((string) $limit['percent']) ?>%"></span>
                    </div>
                    <div class="limit-values">
                        <span>Przychód w tym roku<strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $limit['used'])) ?></strong></span>
                        <span>Limit<strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $limit['limit'])) ?></strong></span>
                    </div>
                    <p class="limit-note">Pozostało do limitu: <?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $limit['remaining'])) ?></p>
                </article>

                <article class="card dashboard-card-sm payroll-card">
                    <h2 class="card-title">Wynagrodzenia</h2>
                    <div class="summary-row"><span><?= \App\Helpers\FormatHelper::e((string) $salaries['accountant_label']) ?></span><strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $salaries['accountant'])) ?></strong></div>
                    <div class="summary-row"><span><?= \App\Helpers\FormatHelper::e((string) $salaries['teacher_label']) ?></span><strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $salaries['teacher'])) ?></strong></div>
                    <div class="summary-row"><span><?= \App\Helpers\FormatHelper::e((string) $salaries['marketing_label']) ?></span><strong><?= \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money((float) $salaries['marketing'])) ?></strong></div>
                </article>

                <article class="card dashboard-card-sm lessons-calendar-card">
                    <div class="card-header">
                        <h2 class="card-title">Kalendarz zajęć</h2>
                        <div class="lesson-calendar-nav" aria-label="Nawigacja miesięcy kalendarza">
                            <a class="lesson-calendar-nav-btn" href="<?= \App\Helpers\FormatHelper::e(dashboard_month_url($previousCalendarMonth)) ?>" aria-label="Poprzedni miesiąc">
                                <?= \App\Helpers\IconHelper::svg('chevron-left') ?>
                            </a>
                            <span class="badge badge-neutral"><?= \App\Helpers\FormatHelper::e($monthYear) ?></span>
                            <a class="lesson-calendar-nav-btn" href="<?= \App\Helpers\FormatHelper::e(dashboard_month_url($nextCalendarMonth)) ?>" aria-label="Następny miesiąc">
                                <?= \App\Helpers\IconHelper::svg('chevron-right') ?>
                            </a>
                        </div>
                    </div>
                    <?= dashboard_lesson_calendar((string) $period['from'], $dashboardData['rental_expenses'], $dashboardData['school_cancelled_dates'] ?? []) ?>
                </article>
            </aside>
        </section>
    </div>

    <?php if ($attendanceDate !== ''): ?>
        <?= dashboard_attendance_modal($attendanceDate, $attendanceStudents, $attendanceErrors) ?>
    <?php endif; ?>
    <?php
});

function dashboard_kpi(string $icon, string $label, string $value, string $trend): string
{
    return '<article class="card kpi-card"><span class="kpi-icon" aria-hidden="true">' . \App\Helpers\IconHelper::svg($icon) . '</span><div><span class="kpi-label">' . \App\Helpers\FormatHelper::e($label) . '</span><strong class="kpi-value">' . \App\Helpers\FormatHelper::e($value) . '</strong><span class="kpi-trend is-positive">' . \App\Helpers\FormatHelper::e($trend) . '</span></div></article>';
}

function dashboard_month_url(DateTimeImmutable $month): string
{
    return 'dashboard.php?' . http_build_query([
        'month' => $month->format('Y-m'),
        'chart_year' => $month->format('Y'),
    ]);
}

function dashboard_revenue_chart(array $values): string
{
    // A month without data is null, not zero: it must not be drawn as a month
    // with no profit.
    $known = array_filter($values, static fn ($value): bool => $value !== null);
    if ($known === []) {
        return '<div class="empty-state compact">Brak danych do wykresu w tym roku.</div>';
    }

    $minimumValue = min(0, (float) min($known));
    $maximumValue = max(0, (float) max($known));
    $hasNegativeValues = $minimumValue < 0;

    if ($hasNegativeValues) {
        $magnitude = max(1, dashboard_chart_ceiling(max(abs($minimumValue), abs($maximumValue))));
        $scaleMinimum = -$magnitude;
        $scaleMaximum = $magnitude;
    } else {
        $scaleMinimum = 0.0;
        $scaleMaximum = max(1, dashboard_chart_ceiling($maximumValue));
    }

    $scaleRange = $scaleMaximum - $scaleMinimum;
    $points = [];
    $segments = [];
    $segment = [];
    $count = count($values);
    $i = 0;
    foreach ($values as $value) {
        if ($value === null) {
            if ($segment !== []) {
                $segments[] = $segment;
                $segment = [];
            }
            $i++;
            continue;
        }

        $x = $count > 1 ? ($i / ($count - 1)) * 100 : 0;
        $y = (($scaleMaximum - (float) $value) / $scaleRange) * 100;
        $point = ['x' => $x, 'y' => $y, 'value' => (float) $value];
        $points[] = $point;
        $segment[] = $point;
        $i++;
    }
    if ($segment !== []) {
        $segments[] = $segment;
    }

    // One polyline per run of consecutive months that have data, so a gap in the
    // year is not bridged by a misleading straight line.
    $polylines = array_map(
        static fn (array $run): string => implode(' ', array_map(
            static fn (array $p): string => number_format($p['x'], 3, '.', '') . ',' . number_format($p['y'], 3, '.', ''),
            $run
        )),
        $segments
    );
    $html = '<div class="line-chart"><div class="chart-scale">';
    for ($step = 5; $step >= 0; $step--) {
        $labelValue = $scaleMinimum + (($scaleRange / 5) * $step);
        $html .= '<span>' . \App\Helpers\FormatHelper::e(dashboard_short_money($labelValue)) . '</span>';
    }
    $html .= '</div><div class="chart-canvas"><svg class="line-chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none">';
    for ($line = 0; $line <= 5; $line++) {
        $y = $line * 20;
        $html .= '<line class="chart-svg-gridline" x1="0" y1="' . $y . '" x2="100" y2="' . $y . '"></line>';
    }
    foreach ($polylines as $polyline) {
        $html .= '<polyline fill="none" stroke="var(--color-primary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" points="' . \App\Helpers\FormatHelper::e($polyline) . '"></polyline>';
    }
    $html .= '</svg>';
    foreach ($points as $point) {
        $html .= '<span class="chart-point" style="left:' . \App\Helpers\FormatHelper::e((string) $point['x']) . '%; top:' . \App\Helpers\FormatHelper::e((string) $point['y']) . '%" title="' . \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::money($point['value'])) . '"></span>';
    }
    $html .= '</div><div class="chart-months">';
    for ($m = 1; $m <= 12; $m++) {
        $monthX = $count > 1 ? (($m - 1) / ($count - 1)) * 100 : 0;
        $html .= '<span style="left:' . \App\Helpers\FormatHelper::e((string) $monthX) . '%">' . \App\Helpers\FormatHelper::e(dashboard_month_short($m)) . '</span>';
    }
    $html .= '</div></div>';

    return $html;
}

function dashboard_lesson_calendar(string $monthStart, array $rentalExpenses, array $schoolCancelledDates = []): string
{
    $first = new DateTimeImmutable($monthStart);
    $days = (int) $first->format('t');
    $offset = (int) $first->format('N') - 1;
    $html = '<div class="lesson-calendar"><div class="lesson-calendar-weekdays"><span>Pn</span><span>Wt</span><span>Śr</span><span>Cz</span><span>Pt</span><span>Sb</span><span>Nd</span></div><div class="lesson-calendar-grid">';
    for ($i = 0; $i < $offset; $i++) {
        $html .= '<span class="lesson-day is-empty"></span>';
    }
    for ($day = 1; $day <= $days; $day++) {
        $date = $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->format('Y-m-d');
        $record = $rentalExpenses[$date] ?? null;
        $status = $record['status'] ?? 'available';
        $categorySlug = (string) ($record['category_slug'] ?? '');
        $hasGroupPaid = (bool) ($record['has_group_paid'] ?? false);
        $hasIndividualPaid = (bool) ($record['has_individual_paid'] ?? false);
        $hasComment = (bool) ($record['has_comment'] ?? false);
        $comments = array_values(array_filter(
            is_array($record['comments'] ?? null) ? $record['comments'] : [],
            static fn ($comment): bool => trim((string) $comment) !== ''
        ));
        $class = match (true) {
            $hasGroupPaid && $hasIndividualPaid => 'is-paid is-individual is-split-group-individual',
            $hasIndividualPaid || ($categorySlug === 'wynajem-indywidualny' && !in_array($status, ['cancelled', 'rejected'], true)) => 'is-individual',
            $status === 'paid' => 'is-paid',
            in_array($status, ['cancelled', 'rejected'], true) => 'is-cancelled',
            in_array($status, ['draft', 'pending'], true) => 'is-unpaid',
            default => 'is-available',
        };
        $schoolCancelled = in_array($date, $schoolCancelledDates, true);
        if ($schoolCancelled) {
            $class .= ' is-school-cancelled';
        }
        $groupRecord = is_array($record['group'] ?? null) ? $record['group'] : null;
        $href = match (true) {
            $hasGroupPaid || ($status === 'paid' && $categorySlug === 'wynajem') => 'dashboard.php?month=' . rawurlencode($first->format('Y-m')) . '&attendance_modal=1&attendance_date=' . rawurlencode($date) . '#attendance-modal',
            $groupRecord !== null && !empty($groupRecord['id']) => 'expenses.php?month=' . rawurlencode($first->format('Y-m')) . '&highlight_expense=' . rawurlencode((string) $groupRecord['id']) . '#expense-' . rawurlencode((string) $groupRecord['id']),
            $record !== null && !empty($record['id']) => 'expenses.php?month=' . rawurlencode($first->format('Y-m')) . '&highlight_expense=' . rawurlencode((string) $record['id']) . '#expense-' . rawurlencode((string) $record['id']),
            default => 'expenses.php?modal=add&rental_date=' . rawurlencode($date),
        };
        $dayContent = '<span class="lesson-day-content"><span>' . $day . '</span>';
        if ($hasComment && $comments !== []) {
            $tooltip = '<span class="lesson-day-comment-tooltip" role="tooltip">';
            foreach ($comments as $comment) {
                $tooltip .= '<span>' . \App\Helpers\FormatHelper::e((string) $comment) . '</span>';
            }
            $tooltip .= '</span>';
            $dayContent .= '<span class="lesson-day-comment" aria-hidden="true">!' . $tooltip . '</span><span class="sr-only">Dzień zawiera komentarz: ' . \App\Helpers\FormatHelper::e(implode('; ', $comments)) . '</span>';
        }
        $dayContent .= '</span>';
        if ($schoolCancelled) {
            $dayContent .= '<span class="lesson-day-cancel-cross" aria-hidden="true"></span><span class="sr-only">Zajęcia odwołane przez szkołę</span>';
        }
        $html .= '<a class="lesson-day ' . $class . '" href="' . \App\Helpers\FormatHelper::e($href) . '">' . $dayContent . '</a>';
    }
    $html .= '</div><div class="lesson-calendar-legend"><span><i class="is-paid"></i>Opłacone</span><span><i class="is-individual"></i>Indywidualne</span><span><i class="is-unpaid"></i>Zarezerwowane / robocze</span><span><i class="is-cancelled"></i>Anulowane</span></div></div>';

    return $html;
}

function dashboard_attendance_modal(string $date, array $students, array $errors): string
{
    $html = '<div class="modal-backdrop is-open" id="attendance-modal"><form class="modal attendance-modal" method="post"><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="lesson_date" value="' . \App\Helpers\FormatHelper::e($date) . '">';
    $html .= '<input type="hidden" name="csrf_token" value="' . \App\Helpers\FormatHelper::e(csrf_token()) . '">';
    $html .= '<div class="attendance-modal-header"><div class="attendance-modal-heading"><span class="attendance-modal-calendar">' . \App\Helpers\IconHelper::svg('calendar') . '</span><div><h2 class="attendance-modal-title">Obecność na zajęciach</h2><div class="attendance-modal-date">' . \App\Helpers\FormatHelper::e(\App\Helpers\FormatHelper::date($date)) . '</div></div></div><a class="attendance-modal-close" href="dashboard.php" data-attendance-close aria-label="Zamknij">' . \App\Helpers\IconHelper::svg('close') . '</a></div>';
    foreach ($errors as $error) {
        $html .= '<div class="alert alert-danger">' . \App\Helpers\FormatHelper::e((string) $error) . '</div>';
    }
    $html .= '<div class="attendance-head"><span>Uczennica</span><span>Status</span><span>Notatka</span><span>Nieobecności</span></div><div class="attendance-list">';
    foreach ($students as $student) {
        $id = (int) $student['id'];
        $name = trim((string) $student['first_name'] . ' ' . (string) $student['last_name']);
        $photo = (string) ($student['photo_path'] ?? '');
        $avatar = $photo !== '' ? '<img src="' . \App\Helpers\FormatHelper::e(url($photo)) . '" alt="">' : \App\Helpers\FormatHelper::e(dashboard_initials($student['first_name'] ?? '', $student['last_name'] ?? ''));
        $html .= '<div class="attendance-row"><div class="attendance-student"><span class="avatar">' . $avatar . '</span><div><strong>' . \App\Helpers\FormatHelper::e($name) . '</strong><span>' . \App\Helpers\FormatHelper::e((string) ($student['group_name'] ?? '')) . '</span></div></div><label class="attendance-status-field"><span class="sr-only">Status</span><select class="select" name="attendance[' . $id . '][status]">';
        foreach (['present' => 'Obecna', 'absent_notice' => 'Nieobecna z uprzedzeniem', 'absent_no_notice' => 'Nieobecna bez uprzedzenia', 'school_cancelled' => 'Odwołane przez szkołę'] as $value => $label) {
            $html .= '<option value="' . $value . '"' . (($student['attendance_status'] ?? 'present') === $value ? ' selected' : '') . '>' . \App\Helpers\FormatHelper::e($label) . '</option>';
        }
        $absenceCount = (int) ($student['absence_count'] ?? 0);
        $html .= '</select></label><label class="attendance-notes"><span class="sr-only">Notatka</span><input class="input" type="text" name="attendance[' . $id . '][notes]" value="' . \App\Helpers\FormatHelper::e((string) ($student['attendance_notes'] ?? '')) . '" placeholder="Opcjonalnie"></label><span class="attendance-absence-count" title="Liczba nieobecności w bieżącym abonamencie"><span>' . $absenceCount . '</span></span></div>';
    }
    if ($students === []) {
        $html .= '<div class="empty-state compact">Brak aktywnych uczennic.</div>';
    }
    $html .= '</div><div class="attendance-form-actions"><button class="btn btn-outline attendance-mark-all" type="button" data-attendance-mark-all>' . \App\Helpers\IconHelper::svg('check') . ' Zaznacz wszystkie obecne</button><button class="btn attendance-cancel-lesson" type="submit" name="cancel_lesson" value="1" formnovalidate>Odwołaj zajęcia</button><span class="attendance-action-spacer"></span><a class="btn btn-secondary" href="dashboard.php" data-attendance-close>Anuluj</a><button class="btn btn-primary attendance-save" type="submit">' . \App\Helpers\IconHelper::svg('check') . ' Zapisz obecność</button></div></form></div>';

    return $html;
}

function dashboard_group_gradient(array $items): string
{
    if ($items === []) {
        return 'conic-gradient(var(--color-donut-empty) 0 100%)';
    }

    $start = 0.0;
    $parts = [];
    foreach (array_values($items) as $index => $item) {
        $end = $start + (float) $item['percent'];
        $parts[] = dashboard_group_color($index) . ' ' . $start . '% ' . $end . '%';
        $start = $end;
    }

    return 'conic-gradient(' . implode(', ', $parts) . ')';
}

function dashboard_group_color(int $index): string
{
    return ['#C89B3C', '#E7C7A7', '#87A77F', '#CF78AF', '#6D8DC8'][$index % 5];
}

function dashboard_expense_icon(string $slug): string
{
    return match ($slug) {
        'wynajem' => 'rent',
        'marketing' => 'instagram',
        'ksiegowosc' => 'accounting',
        'wyjazdy' => 'trip',
        'sprzet' => 'equipment',
        'podatki' => 'percent',
        default => 'expense',
    };
}

function dashboard_chart_ceiling(float $value): float
{
    if ($value <= 0) {
        return 1;
    }
    $power = 10 ** floor(log10($value));
    return ceil($value / $power) * $power;
}

function dashboard_short_money(float $value): string
{
    if (abs($value) >= 1000) {
        return number_format($value / 1000, 1, ',', ' ') . 'k zł';
    }
    return number_format($value, 0, ',', ' ') . ' zł';
}

function dashboard_period_label(string $from): string
{
    $date = new DateTimeImmutable($from);
    return '1 - ' . $date->format('t') . ' ' . dashboard_month_name((int) $date->format('n')) . ' ' . $date->format('Y');
}

function dashboard_month_name(int $month): string
{
    return [1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień', 5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień', 9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień'][$month] ?? '';
}

function dashboard_month_short(int $month): string
{
    return [1 => 'Sty', 2 => 'Lut', 3 => 'Mar', 4 => 'Kwi', 5 => 'Maj', 6 => 'Cze', 7 => 'Lip', 8 => 'Sie', 9 => 'Wrz', 10 => 'Paź', 11 => 'Lis', 12 => 'Gru'][$month] ?? '';
}

function dashboard_initials(string $first, string $last): string
{
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

function dashboard_valid_month(string $month): ?string
{
    return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;
}

function dashboard_empty_data(DateTimeImmutable $date): array
{
    return [
        'period' => ['from' => $date->format('Y-m-01'), 'to' => $date->format('Y-m-t')],
        'kpis' => ['revenue' => 0, 'expenses' => 0, 'profit' => 0, 'active_students' => 0],
        'monthly_net_profit' => array_fill(1, 12, null),
        'revenue_by_group' => ['total' => 0, 'items' => []],
        'recent_payments' => [],
        'recent_expenses' => [],
        'activity_limit' => ['used' => 0, 'limit' => 75000, 'remaining' => 75000, 'percent' => 0],
        'salaries' => [
            'accountant' => 0,
            'teacher' => 0,
            'marketing' => 0,
            'marketing_new_students' => 0,
            'accountant_label' => 'Księgowa',
            'teacher_label' => 'Nauczycielka',
            'marketing_label' => 'Marketing',
        ],
        'rental_expenses' => [],
    ];
}
