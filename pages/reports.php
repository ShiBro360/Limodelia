<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\DashboardRepository;
use App\Repositories\ExpenseCategoryRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ReportRepository;
use App\Repositories\SalaryRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudentRepository;
use App\Repositories\TripPhotoRepository;
use App\Repositories\TripRepository;
use App\Services\CloudinaryService;
use App\Services\DashboardService;
use App\Services\ExpenseService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\SalaryService;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\StudentService;
use App\Services\TripService;
use App\Validators\ExpenseValidator;
use App\Validators\PaymentValidator;
use App\Validators\ReportValidator;
use App\Validators\SalaryValidator;
use App\Validators\StudentValidator;
use App\Validators\TripValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$reportService = null;

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));
    $storageService = new StorageService($settingsService);
    $salaryService = new SalaryService(new SalaryRepository($pdo), new SalaryValidator());
    $reportService = new ReportService(
        new ReportRepository($pdo),
        new ReportValidator(),
        new DashboardService(new DashboardRepository($pdo), $salaryService),
        new StudentService(new StudentRepository($pdo), new StudentValidator(), $storageService),
        new PaymentService(new PaymentRepository($pdo), new PaymentValidator()),
        new ExpenseService(new ExpenseRepository($pdo), new ExpenseValidator()),
        $salaryService,
        new TripService(
            new TripRepository($pdo),
            new TripPhotoRepository($pdo),
            new TripValidator(),
            new CloudinaryService($settingsService),
            $storageService,
            new ExpenseService(new ExpenseRepository($pdo), new ExpenseValidator()),
            new ExpenseCategoryRepository($pdo)
        ),
        $storageService
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_report') {
        $filters = $reportService->filters($_POST);
        $result = $reportService->generate($filters, true);
        if (($result['errors'] ?? []) === []) {
            ResponseHelper::redirect('pages/reports.php', $filters + ['saved' => 'generated']);
        }
        $errors = $result['errors'];
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd modułu raportów: ' . $exception->getMessage();
}

render_page('Raporty', 'reports', function () use ($reportService, $errors, $notice): void {
    $filters = [
        'month' => date('Y-m'),
        'from' => date('Y-m-01'),
        'to' => date('Y-m-t'),
        'type' => 'financial_summary',
    ];
    $labels = [];
    $report = [
        'financial' => [
            'income' => 0,
            'expenses' => 0,
            'net_profit' => 0,
            'payments_count' => 0,
            'active_students' => 0,
            'salary_costs' => 0,
            'trip_costs' => 0,
            'marketing_costs' => 0,
        ],
        'monthly_chart' => [],
        'detail' => [],
    ];

    if ($reportService instanceof ReportService) {
        try {
            $filters = $reportService->filters($_GET);
            $labels = $reportService->typeLabels();
            $report = $reportService->generate($filters);
            $errors = array_merge($errors, $report['errors'] ?? []);
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się wygenerować raportu: ' . $exception->getMessage();
        }
    }

    $financial = $report['financial'];
    $maxChart = 1.0;
    foreach ($report['monthly_chart'] as $monthData) {
        $maxChart = max($maxChart, (float) $monthData['income'], (float) $monthData['expenses']);
    }
    $selectedMonthName = report_month_full((int) substr($filters['month'], 5, 2)) . ' ' . substr($filters['month'], 0, 4);
    $profitBeforeSalaries = (float) $financial['income'] - max(0, (float) $financial['expenses'] - (float) $financial['salary_costs']);
    $schoolNetProfit = (float) $financial['net_profit'];
    $incomeValues = [];
    $expenseValues = [];
    foreach ($report['monthly_chart'] as $monthData) {
        $incomeValues[] = (float) $monthData['income'];
        $expenseValues[] = (float) $monthData['expenses'];
    }
    $chartMax = max(1.0, $maxChart);
    $incomePoints = report_chart_points($incomeValues, $chartMax);
    $expensePoints = report_chart_points($expenseValues, $chartMax);
    $incomeArea = report_chart_area_points($incomeValues, $chartMax);
    $expenseArea = report_chart_area_points($expenseValues, $chartMax);
    $reportCards = [
        ['icon' => 'money', 'title' => 'Raport przychodów', 'text' => 'Szczegółowe zestawienie wszystkich przychodów.', 'value' => FormatHelper::money($financial['income']), 'type' => 'payments_report'],
        ['icon' => 'arrow-down', 'title' => 'Raport wydatków', 'text' => 'Szczegółowe zestawienie wszystkich wydatków.', 'value' => FormatHelper::money($financial['expenses']), 'type' => 'expenses_report'],
        ['icon' => 'profit', 'title' => 'Raport zysków', 'text' => 'Zestawienie zysków przed i po wynagrodzeniach.', 'value' => FormatHelper::money($profitBeforeSalaries), 'type' => 'financial_summary'],
        ['icon' => 'users', 'title' => 'Raport wynagrodzeń', 'text' => 'Szczegóły dotyczące wypłat i prowizji.', 'value' => FormatHelper::money($financial['salary_costs']), 'type' => 'salaries_report'],
        ['icon' => 'instagram', 'title' => 'Raport marketingu', 'text' => 'Skuteczność kampanii i pozyskanie uczennic.', 'value' => FormatHelper::money($financial['marketing_costs']), 'type' => 'financial_summary'],
        ['icon' => 'trip', 'title' => 'Raport wyjazdów', 'text' => 'Przychody i koszty związane z wyjazdami.', 'value' => FormatHelper::money($financial['trip_costs']), 'type' => 'trips_report'],
    ];
    ?>
    <section class="module-hero reports-hero reports-page">
        <div>
            <h1>Raporty</h1>
            <p>Analizuj finanse szkoły i generuj szczegółowe raporty.</p>
        </div>
        <form class="reports-toolbar" method="get">
            <label class="report-month-control">
                <span aria-hidden="true"><?= IconHelper::svg('calendar') ?></span>
                <select class="report-month-select" name="month" aria-label="Miesiąc raportu" onchange="this.form.submit()">
                    <?php for ($monthIndex = 1; $monthIndex <= 12; $monthIndex++): ?>
                        <?php $monthValue = substr($filters['month'], 0, 4) . '-' . str_pad((string) $monthIndex, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= FormatHelper::e($monthValue) ?>" <?= $monthValue === $filters['month'] ? 'selected' : '' ?>>
                            <?= FormatHelper::e(report_month_full($monthIndex) . ' ' . substr($filters['month'], 0, 4)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>
            <input type="hidden" name="from" value="<?= FormatHelper::e($filters['from']) ?>">
            <input type="hidden" name="to" value="<?= FormatHelper::e($filters['to']) ?>">
            <input type="hidden" name="type" value="<?= FormatHelper::e($filters['type']) ?>">
            <button class="btn btn-secondary" type="button" disabled><?= IconHelper::svg('document') ?> Eksportuj PDF</button>
            <button class="btn btn-secondary" type="button" disabled><?= IconHelper::svg('download') ?> Eksportuj Excel</button>
        </form>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Raport został wygenerowany i zapisany w metadanych.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="kpi-grid reports-kpi-grid">
        <article class="card kpi-card"><span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('profit') ?></span><div><span class="kpi-label">Łączny przychód</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($financial['income'])) ?></strong><small class="kpi-trend kpi-trend-up">↑ bieżący okres</small></div></article>
        <article class="card kpi-card"><span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('arrow-down') ?></span><div><span class="kpi-label">Łączne wydatki</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($financial['expenses'])) ?></strong><small class="kpi-trend kpi-trend-down">↓ kontrola kosztów</small></div></article>
        <article class="card kpi-card"><span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('wallet') ?></span><div><span class="kpi-label">Zysk przed wynagrodzeniami</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($profitBeforeSalaries)) ?></strong><small class="kpi-trend kpi-trend-up">↑ przychód minus koszty</small></div></article>
        <article class="card kpi-card"><span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('user') ?></span><div><span class="kpi-label">Aktywne uczennice</span><strong class="kpi-value"><?= FormatHelper::e((string) $financial['active_students']) ?></strong><small class="kpi-trend kpi-trend-up">status aktywna</small></div></article>
    </section>

    <section class="reports-main-grid">
        <article class="card report-chart-card reports-line-card">
            <div class="card-header">
                <h2 class="card-title">Przychody i wydatki</h2>
                <button class="btn btn-secondary reports-chart-mode" type="button" disabled>Miesięcznie <?= IconHelper::svg('chevron-down') ?></button>
            </div>
            <span class="reports-chart-legend"><i class="legend-income"></i> Przychody <i class="legend-expense"></i> Wydatki</span>
            <div class="reports-line-chart" tabindex="0" aria-label="Przewijany wykres miesięczny">
                <svg class="reports-chart-svg" viewBox="0 0 1000 286" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Przychody i wydatki według miesięcy">
                    <defs>
                        <linearGradient id="reportsIncomeArea" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-primary)" stop-opacity=".22"/>
                            <stop offset="100%" stop-color="var(--color-primary)" stop-opacity=".02"/>
                        </linearGradient>
                        <linearGradient id="reportsExpenseArea" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-accent)" stop-opacity=".20"/>
                            <stop offset="100%" stop-color="var(--color-accent)" stop-opacity=".02"/>
                        </linearGradient>
                    </defs>
                    <?php for ($i = 0; $i <= 5; $i++): ?>
                        <?php $y = 34 + ($i * 36.8); ?>
                        <line class="chart-grid-line" x1="70" y1="<?= number_format($y, 2, '.', '') ?>" x2="960" y2="<?= number_format($y, 2, '.', '') ?>"/>
                        <text class="chart-axis-label" x="2" y="<?= $y + 4 ?>"><?= FormatHelper::e(report_axis_label($chartMax, 5 - $i)) ?></text>
                    <?php endfor; ?>
                    <?php foreach (array_values($report['monthly_chart']) as $index => $monthData): ?>
                        <?php $x = report_chart_x($index, count($report['monthly_chart'])); ?>
                        <line class="chart-month-line" x1="<?= number_format($x, 2, '.', '') ?>" y1="34" x2="<?= number_format($x, 2, '.', '') ?>" y2="218"/>
                        <text class="chart-month-label" x="<?= number_format($x, 2, '.', '') ?>" y="254"><?= FormatHelper::e(report_month_short((int) ($monthData['month'] ?? ($index + 1)))) ?> <?= FormatHelper::e(substr($filters['month'], 0, 4)) ?></text>
                    <?php endforeach; ?>
                    <polygon class="chart-area-income" points="<?= FormatHelper::e($incomeArea) ?>"/>
                    <polygon class="chart-area-expense" points="<?= FormatHelper::e($expenseArea) ?>"/>
                    <polyline class="chart-line-income" points="<?= FormatHelper::e($incomePoints) ?>"/>
                    <polyline class="chart-line-expense" points="<?= FormatHelper::e($expensePoints) ?>"/>
                    <?php foreach (array_values($report['monthly_chart']) as $index => $monthData): ?>
                        <?php
                        $monthCount = count($report['monthly_chart']);
                        $x = report_chart_x($index, $monthCount);
                        $income = (float) ($monthData['income'] ?? 0);
                        $expenses = (float) ($monthData['expenses'] ?? 0);
                        $safeMax = max(1, $chartMax) * 1.12;
                        $incomeY = 218 - (($income / $safeMax) * 184);
                        $expenseY = 218 - (($expenses / $safeMax) * 184);
                        ?>
                        <circle class="reports-chart-point reports-chart-point-income" cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($incomeY, 2, '.', '') ?>" r="4.8"/>
                        <circle class="reports-chart-point reports-chart-point-expense" cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($expenseY, 2, '.', '') ?>" r="4.8"/>
                    <?php endforeach; ?>
                    <?php foreach (array_values($report['monthly_chart']) as $index => $monthData): ?>
                        <?php
                        $monthCount = count($report['monthly_chart']);
                        $x = report_chart_x($index, $monthCount);
                        $zoneWidth = max(58, 890 / max(1, $monthCount));
                        $zoneX = max(70, min(960 - $zoneWidth, $x - ($zoneWidth / 2)));
                        $income = (float) ($monthData['income'] ?? 0);
                        $expenses = (float) ($monthData['expenses'] ?? 0);
                        $safeMax = max(1, $chartMax) * 1.12;
                        $incomeY = 218 - (($income / $safeMax) * 184);
                        $expenseY = 218 - (($expenses / $safeMax) * 184);
                        $tooltipWidth = 184;
                        $tooltipHeight = 82;
                        $tooltipX = max(72, min(960 - $tooltipWidth, $x - ($tooltipWidth / 2)));
                        $tooltipY = max(8, min($incomeY, $expenseY) - $tooltipHeight - 12);
                        $monthTitle = report_month_full((int) ($monthData['month'] ?? ($index + 1))) . ' ' . substr($filters['month'], 0, 4);
                        ?>
                        <g class="chart-hover-month chart-tooltip-layer" tabindex="0" role="button" aria-label="<?= FormatHelper::e($monthTitle) ?>: przychody <?= FormatHelper::e(FormatHelper::money($income)) ?>, wydatki <?= FormatHelper::e(FormatHelper::money($expenses)) ?>">
                            <rect class="chart-hover-zone" x="<?= number_format($zoneX, 2, '.', '') ?>" y="30" width="<?= number_format($zoneWidth, 2, '.', '') ?>" height="214"/>
                            <circle class="reports-chart-point reports-chart-point-income reports-chart-point-hover" cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($incomeY, 2, '.', '') ?>" r="5.8"/>
                            <circle class="reports-chart-point reports-chart-point-expense reports-chart-point-hover" cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($expenseY, 2, '.', '') ?>" r="5.8"/>
                            <g class="chart-tooltip-position"
                               data-desktop-x="<?= number_format($tooltipX, 2, '.', '') ?>"
                               data-center-x="<?= number_format($x, 2, '.', '') ?>"
                               data-tooltip-y="<?= number_format($tooltipY, 2, '.', '') ?>"
                               transform="translate(<?= number_format($tooltipX, 2, '.', '') ?> <?= number_format($tooltipY, 2, '.', '') ?>)">
                                <g class="chart-tooltip-svg">
                                    <rect class="chart-tooltip-bg" width="184" height="82" rx="8"/>
                                    <text class="chart-tooltip-title" x="14" y="22"><?= FormatHelper::e($monthTitle) ?></text>
                                    <circle class="chart-tooltip-dot chart-tooltip-dot-income" cx="17" cy="45" r="4"/>
                                    <text class="chart-tooltip-text" x="30" y="49">Przychody: <?= FormatHelper::e(FormatHelper::money($income)) ?></text>
                                    <circle class="chart-tooltip-dot chart-tooltip-dot-expense" cx="17" cy="65" r="4"/>
                                    <text class="chart-tooltip-text" x="30" y="69">Wydatki: <?= FormatHelper::e(FormatHelper::money($expenses)) ?></text>
                                </g>
                            </g>
                        </g>
                    <?php endforeach; ?>
                </svg>
            </div>
        </article>

        <aside class="card reports-summary-card">
            <h2 class="card-title">Podsumowanie - <?= FormatHelper::e($selectedMonthName) ?></h2>
            <div class="summary-row"><span>Łączny przychód</span><strong class="text-gold"><?= FormatHelper::e(FormatHelper::money($financial['income'])) ?></strong></div>
            <div class="summary-row"><span>Łączne wydatki</span><strong class="text-rose"><?= FormatHelper::e(FormatHelper::money($financial['expenses'])) ?></strong></div>
            <div class="summary-separator"></div>
            <div class="summary-row"><span>Zysk (przed wynagrodzeniami)</span><strong><?= FormatHelper::e(FormatHelper::money($profitBeforeSalaries)) ?></strong></div>
            <div class="summary-row"><span>Wynagrodzenia</span><strong><?= FormatHelper::e(FormatHelper::money($financial['salary_costs'])) ?></strong></div>
            <div class="summary-row"><span>Koszty wyjazdów</span><strong><?= FormatHelper::e(FormatHelper::money($financial['trip_costs'])) ?></strong></div>
            <div class="summary-row"><span>Marketing</span><strong><?= FormatHelper::e(FormatHelper::money($financial['marketing_costs'])) ?></strong></div>
            <div class="summary-separator"></div>
            <div class="summary-row report-total"><span>Zysk netto szkoły</span><strong class="<?= $schoolNetProfit < 0 ? 'text-rose' : 'text-gold' ?>"><?= FormatHelper::e(FormatHelper::money($schoolNetProfit)) ?></strong></div>
        </aside>
    </section>

    <section class="card report-detail-card reports-cards-panel">
        <h2 class="card-title">Szczegółowe raporty</h2>
        <div class="reports-type-grid">
            <?php foreach ($reportCards as $card): ?>
                <article class="report-type-card">
                    <span class="report-type-icon" aria-hidden="true"><?= IconHelper::svg($card['icon']) ?></span>
                    <h3><?= FormatHelper::e($card['title']) ?></h3>
                    <p><?= FormatHelper::e($card['text']) ?></p>
                    <strong><?= FormatHelper::e($card['value']) ?></strong>
                    <a class="btn btn-soft" href="<?= FormatHelper::e(url('pages/reports.php?type=' . urlencode($card['type']) . '&month=' . urlencode($filters['month']))) ?>">Zobacz raport</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="report-note">
        <span aria-hidden="true"><?= IconHelper::svg('notes') ?></span>
        <p>Raporty są generowane na podstawie danych z wybranego okresu. Eksport PDF i Excel zostanie podłączony w kolejnym etapie.</p>
    </div>
    <?php
});

function report_chart_points(array $values, float $max): string
{
    return implode(' ', array_map(
        static fn(array $point): string => $point[0] . ',' . $point[1],
        report_chart_point_pairs($values, $max)
    ));
}

function report_chart_area_points(array $values, float $max): string
{
    $points = report_chart_point_pairs($values, $max);
    if ($points === []) {
        return '70,218 960,218';
    }

    $first = $points[0][0] . ',218';
    $lastPoint = $points[count($points) - 1];
    $last = $lastPoint[0] . ',218';

    return $first . ' ' . report_chart_points($values, $max) . ' ' . $last;
}

function report_chart_point_pairs(array $values, float $max): array
{
    $count = count($values);
    if ($count === 0) {
        return [];
    }

    $points = [];
    foreach (array_values($values) as $index => $value) {
        $x = report_chart_x($index, $count);
        $safeMax = max(1, $max) * 1.12;
        $y = 218 - (($value / $safeMax) * 184);
        $points[] = [number_format($x, 2, '.', ''), number_format($y, 2, '.', '')];
    }

    return $points;
}

function report_chart_x(int $index, int $count): float
{
    return 70 + ($index * (890 / max(1, $count - 1)));
}

function report_axis_label(float $max, int $step): string
{
    $value = ($max / 5) * $step;
    if ($value >= 1000) {
        return number_format($value / 1000, 0, ',', ' ') . 'k zł';
    }

    return number_format($value, 0, ',', ' ') . ' zł';
}

function report_month_full(int $month): string
{
    return ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'][$month - 1] ?? '';
}

function render_report_detail(string $type, array $detail): void
{
    if ($type === 'payments_report') {
        render_group_cards('Płatności według metody', $detail['by_method'] ?? []);
        render_payments_table($detail['details'] ?? []);
        return;
    }

    if ($type === 'expenses_report') {
        render_group_cards('Wydatki według kategorii', $detail['by_category'] ?? []);
        render_expenses_table($detail['details'] ?? []);
        return;
    }

    if ($type === 'salaries_report') {
        render_salaries_table($detail['payouts'] ?? []);
        return;
    }

    if ($type === 'trips_report') {
        render_trip_summary($detail['summary'] ?? []);
        render_trips_table($detail['details'] ?? []);
        return;
    }

    if ($type === 'students_report') {
        render_student_summary($detail);
        return;
    }

    render_group_cards('Przychody według metody', $detail['payments']['by_method'] ?? []);
    render_group_cards('Wydatki według źródła', $detail['expenses']['by_source'] ?? []);
}

function render_group_cards(string $title, array $rows): void
{
    echo '<h3 class="form-section-title">' . FormatHelper::e($title) . '</h3>';
    if ($rows === []) {
        echo '<section class="empty-state module-empty"><p class="empty-state-text">Brak danych dla wybranego okresu.</p></section>';
        return;
    }
    echo '<div class="report-mini-grid">';
    foreach (array_slice($rows, 0, 6) as $row) {
        echo '<article class="highlight-metric"><span>' . FormatHelper::e((string) $row['label']) . '</span><strong>' . FormatHelper::e(FormatHelper::money($row['amount'] ?? 0)) . '</strong><small>' . FormatHelper::e((string) ($row['count'] ?? 0)) . ' rekordów</small></article>';
    }
    echo '</div>';
}

function render_payments_table(array $rows): void
{
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Numer</th><th>Data</th><th>Uczennica</th><th>Abonament</th><th>Kwota</th><th>Metoda</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . FormatHelper::e($row['payment_number']) . '</td><td>' . FormatHelper::e(FormatHelper::date($row['payment_date'])) . '</td><td>' . FormatHelper::e($row['student_name']) . '</td><td>' . FormatHelper::e($row['subscription_name'] ?? '-') . '</td><td><strong>' . FormatHelper::e(FormatHelper::money($row['amount'], $row['currency'])) . '</strong></td><td>' . FormatHelper::e($row['method_name']) . '</td><td><span class="badge ' . FormatHelper::e(FormatHelper::paymentStatusBadge($row['status'])) . '">' . FormatHelper::e(FormatHelper::paymentStatusLabel($row['status'])) . '</span></td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_expenses_table(array $rows): void
{
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Data</th><th>Kategoria</th><th>Opis</th><th>Kwota</th><th>Metoda</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . FormatHelper::e(FormatHelper::date($row['expense_date'])) . '</td><td>' . FormatHelper::e($row['category_name']) . '</td><td>' . FormatHelper::e($row['description'] ?: '-') . '</td><td><strong>' . FormatHelper::e(FormatHelper::money($row['amount'], $row['currency'])) . '</strong></td><td>' . FormatHelper::e($row['method_name'] ?: '-') . '</td><td><span class="badge ' . FormatHelper::e(FormatHelper::expenseStatusBadge($row['status'])) . '">' . FormatHelper::e(FormatHelper::expenseStatusLabel($row['status'])) . '</span></td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_salaries_table(array $rows): void
{
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Pracownik</th><th>Reguła</th><th>Okres</th><th>Kwota</th><th>Status</th><th>Koszt</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . FormatHelper::e($row['employee_name']) . '</td><td>' . FormatHelper::e($row['rule_name'] ?? '-') . '</td><td>' . FormatHelper::e(FormatHelper::date($row['period_start'])) . ' - ' . FormatHelper::e(FormatHelper::date($row['period_end'])) . '</td><td><strong>' . FormatHelper::e(FormatHelper::money($row['total_amount'], $row['currency'])) . '</strong></td><td><span class="badge ' . FormatHelper::e(FormatHelper::salaryStatusBadge($row['status'])) . '">' . FormatHelper::e(FormatHelper::salaryStatusLabel($row['status'])) . '</span></td><td>' . FormatHelper::e($row['linked_expense_id'] ? 'Powiązany' : 'Brak') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_trip_summary(array $summary): void
{
    echo '<div class="report-mini-grid">';
    foreach (['trips_count' => 'Liczba wyjazdów', 'participants_count' => 'Uczestniczki', 'photos_count' => 'Zdjęcia'] as $key => $label) {
        echo '<article class="highlight-metric"><span>' . FormatHelper::e($label) . '</span><strong>' . FormatHelper::e((string) ($summary[$key] ?? 0)) . '</strong></article>';
    }
    echo '<article class="highlight-metric"><span>Koszty wyjazdów</span><strong>' . FormatHelper::e(FormatHelper::money($summary['trip_expenses'] ?? 0)) . '</strong></article>';
    echo '</div>';
}

function render_trips_table(array $rows): void
{
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Data</th><th>Nazwa</th><th>Lokalizacja</th><th>Uczestniczki</th><th>Zdjęcia</th><th>Koszt</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . FormatHelper::e(FormatHelper::date($row['start_date'])) . '</td><td>' . FormatHelper::e($row['name']) . '</td><td>' . FormatHelper::e($row['location'] ?: '-') . '</td><td>' . FormatHelper::e((string) $row['participants_count']) . '</td><td>' . FormatHelper::e((string) $row['photos_count']) . '</td><td><strong>' . FormatHelper::e(FormatHelper::money($row['expenses_amount'], $row['currency'])) . '</strong></td><td>' . FormatHelper::e($row['status']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function render_student_summary(array $detail): void
{
    echo '<div class="report-mini-grid">';
    echo '<article class="highlight-metric"><span>Aktywne uczennice</span><strong>' . FormatHelper::e((string) ($detail['active'] ?? 0)) . '</strong></article>';
    echo '<article class="highlight-metric"><span>Zarchiwizowane</span><strong>' . FormatHelper::e((string) ($detail['archived'] ?? 0)) . '</strong></article>';
    echo '</div>';
    render_count_cards('Uczennice według grup', $detail['by_group'] ?? []);
    render_count_cards('Uczennice według abonamentu', $detail['by_subscription'] ?? []);
}

function render_count_cards(string $title, array $rows): void
{
    echo '<h3 class="form-section-title">' . FormatHelper::e($title) . '</h3><div class="report-mini-grid">';
    foreach ($rows as $row) {
        echo '<article class="highlight-metric"><span>' . FormatHelper::e($row['label']) . '</span><strong>' . FormatHelper::e((string) $row['count']) . '</strong></article>';
    }
    echo '</div>';
}

function report_month_short(int $month): string
{
    return ['Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'][$month - 1] ?? '';
}

function report_percent(float $value, float $max): string
{
    return number_format(min(100, max(2, ($value / max(1, $max)) * 100)), 2, '.', '');
}
