<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\ExpenseCategoryRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\MarketingRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\SettingsRepository;
use App\Services\ExpenseService;
use App\Services\SettingsService;
use App\Validators\ExpenseValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$expenseService = null;
$categoryRepository = null;
$methodRepository = null;
$expenseSettings = [
    'show_cancelled' => false,
    'rental_hourly_rate' => 32.00,
    'individual_rental_duration_minutes' => 60,
];
$lessonSchedule = [];

function expense_category_icon_class(?string $slug): string
{
    return match ($slug) {
        'wynajem', 'wynajem-indywidualny' => 'expense-icon-rent',
        'marketing' => 'expense-icon-marketing',
        'ksiegowosc' => 'expense-icon-accounting',
        'wyjazdy' => 'expense-icon-trip',
        'sprzet' => 'expense-icon-equipment',
        'podatki' => 'expense-icon-tax',
        default => 'expense-icon-other',
    };
}

function expense_method_icon_class(?string $slug): string
{
    return match ($slug) {
        'gotowka' => 'expense-icon-cash',
        'karta' => 'expense-icon-card',
        'przelew' => 'expense-icon-transfer',
        'blik' => 'expense-icon-blik',
        default => 'expense-icon-other',
    };
}

function expense_svg_icon(string $icon): string
{
    return IconHelper::svg($icon, '');
}

function expense_category_svg(?string $slug): string
{
    return expense_svg_icon(match ($slug) {
        'wynajem', 'wynajem-indywidualny' => 'rent',
        'marketing' => 'marketing',
        'ksiegowosc' => 'accounting',
        'wyjazdy' => 'trip',
        'sprzet' => 'equipment',
        'podatki' => 'tax',
        default => 'other',
    });
}

function expense_method_svg(?string $slug): string
{
    return expense_svg_icon(match ($slug) {
        'gotowka' => 'cash',
        'karta' => 'card',
        'przelew' => 'transfer',
        'blik' => 'blik',
        default => 'other',
    });
}

function expense_category_color(string $slug, int $index): string
{
    $colorsBySlug = [
        'wynajem' => '#D79A22',
        'wynajem-indywidualny' => '#4F7DD9',
        'marketing' => '#E15B7C',
        'ksiegowosc' => '#2F80ED',
        'wyjazdy' => '#8B5CF6',
        'sprzet' => '#00A88B',
        'podatki' => '#F97316',
        'inne' => '#6B7280',
    ];

    if (isset($colorsBySlug[$slug])) {
        return $colorsBySlug[$slug];
    }

    $colors = ['#D79A22', '#E15B7C', '#2F80ED', '#8B5CF6', '#00A88B', '#F97316', '#6B7280'];

    return $colors[$index % count($colors)];
}

function expense_month_title(string $month): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01') ?: new DateTimeImmutable();
    $months = [
        1 => 'Styczeń',
        2 => 'Luty',
        3 => 'Marzec',
        4 => 'Kwiecień',
        5 => 'Maj',
        6 => 'Czerwiec',
        7 => 'Lipiec',
        8 => 'Sierpień',
        9 => 'Wrzesień',
        10 => 'Październik',
        11 => 'Listopad',
        12 => 'Grudzień',
    ];

    return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
}

function expense_weekday_label(int $weekday): string
{
    return [
        1 => 'Poniedziałek',
        2 => 'Wtorek',
        3 => 'Środa',
        4 => 'Czwartek',
        5 => 'Piątek',
        6 => 'Sobota',
        7 => 'Niedziela',
    ][$weekday] ?? '';
}

function expense_rental_options(array $schedule, ?string $selectedDate = null, array $reservedDates = [], int $monthsAhead = 3): array
{
    $reservedLookup = array_fill_keys($reservedDates, true);
    $scheduleByWeekday = [];
    foreach ($schedule as $slot) {
        $weekday = (int) ($slot['weekday'] ?? 0);
        $start = (string) ($slot['start'] ?? '');
        $end = (string) ($slot['end'] ?? '');
        if ($weekday < 1 || $weekday > 7 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            continue;
        }

        $scheduleByWeekday[$weekday] = [
            'label' => (string) ($slot['label'] ?? expense_weekday_label($weekday)),
            'start' => $start,
            'end' => $end,
        ];
    }

    $today = new DateTimeImmutable('today');
    $endDate = $today->modify('+' . $monthsAhead . ' months');
    $options = [];

    for ($date = $today; $date <= $endDate; $date = $date->modify('+1 day')) {
        $weekday = (int) $date->format('N');
        $slot = $scheduleByWeekday[$weekday] ?? [
            'label' => expense_weekday_label($weekday),
            'start' => '17:00',
            'end' => '18:00',
        ];
        $value = $date->format('Y-m-d');
        if (isset($reservedLookup[$value]) && $value !== $selectedDate) {
            continue;
        }

        $options[$value] = [
            'date' => $value,
            'label_date' => $date->format('d.m.Y'),
            'label_day' => expense_weekday_label($weekday),
            'label_time' => $slot['start'] . '-' . $slot['end'],
            'label' => $date->format('d.m.Y') . ' · ' . $slot['label'] . ' · ' . $slot['start'] . '-' . $slot['end'],
            'start' => $slot['start'],
            'end' => $slot['end'],
        ];
    }

    if ($selectedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) && !isset($options[$selectedDate])) {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate);
        if ($date instanceof DateTimeImmutable) {
            $weekday = (int) $date->format('N');
            $slot = $scheduleByWeekday[$weekday] ?? [
                'label' => expense_weekday_label($weekday),
                'start' => '17:00',
                'end' => '18:00',
            ];
            $options[$selectedDate] = [
                'date' => $selectedDate,
                'label_date' => $date->format('d.m.Y'),
                'label_day' => expense_weekday_label($weekday),
                'label_time' => $slot['start'] . '-' . $slot['end'],
                'label' => $date->format('d.m.Y') . ' · ' . $slot['label'] . ' · ' . $slot['start'] . '-' . $slot['end'],
                'start' => $slot['start'],
                'end' => $slot['end'],
            ];
        }
    }

    ksort($options);

    return array_values($options);
}

function expense_rental_date_from_description(?string $description): ?string
{
    if ($description && preg_match('/Rezerwacja sali(?: indywidualna)?:\s*(\d{4}-\d{2}-\d{2})/u', $description, $match)) {
        return $match[1];
    }

    return null;
}

function expense_individual_rental_time_from_description(?string $description): array
{
    if ($description && preg_match('/Rezerwacja sali indywidualna:\s*\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2})(?:-\d{2}:\d{2})?/u', $description, $match)) {
        return ['start' => $match[1]];
    }

    return ['start' => ''];
}

function expense_comment_from_description(?string $description): string
{
    if ($description && preg_match('/(?:^|\R)Komentarz:\s*(.+)$/mu', $description, $match)) {
        return trim($match[1]);
    }

    return '';
}

function expense_manual_description(?string $description): string
{
    $lines = preg_split('/\R/u', trim((string) $description)) ?: [];
    $manualLines = array_filter($lines, static function (string $line): bool {
        return !preg_match('/^(Komentarz|Rezerwacja sali|Rezerwacja sali indywidualna|Liczba godzin|Stawka godzinowa|Instagram):/u', trim($line));
    });

    return trim(implode("\n", $manualLines));
}

function expense_redirect_filter_params(array $input, ?string $fallbackMonth = null): array
{
    $month = (string) ($input['filter_month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = $fallbackMonth ?? date('Y-m');
    }

    $params = [
        'month' => $month,
    ];

    $search = trim((string) ($input['filter_search'] ?? ''));
    if ($search !== '') {
        $params['search'] = $search;
    }

    $category = (int) ($input['filter_category'] ?? 0);
    if ($category > 0) {
        $params['category'] = $category;
    }

    $method = (int) ($input['filter_method'] ?? 0);
    if ($method > 0) {
        $params['method'] = $method;
    }

    $perPage = (int) ($input['filter_per_page'] ?? 10);
    if (in_array($perPage, [10, 25, 50], true)) {
        $params['per_page'] = $perPage;
    }

    return $params;
}

function expense_filter_hidden_fields(array $filters): void
{
    ?>
    <input type="hidden" name="filter_search" value="<?= FormatHelper::e((string) ($filters['search'] ?? '')) ?>">
    <input type="hidden" name="filter_month" value="<?= FormatHelper::e((string) ($filters['month'] ?? date('Y-m'))) ?>">
    <input type="hidden" name="filter_category" value="<?= FormatHelper::e((string) ($filters['category'] ?? 0)) ?>">
    <input type="hidden" name="filter_method" value="<?= FormatHelper::e((string) ($filters['method'] ?? 0)) ?>">
    <input type="hidden" name="filter_per_page" value="<?= FormatHelper::e((string) ($filters['per_page'] ?? 10)) ?>">
    <?php
}

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));
    $expenseSettings = $settingsService->expenses();
    $lessonSchedule = $settingsService->lessonSchedule();
    $expenseService = new ExpenseService(
        new ExpenseRepository($pdo),
        new ExpenseValidator(),
        (bool) $expenseSettings['show_cancelled'],
        new MarketingRepository($pdo),
        $lessonSchedule,
        (float) $expenseSettings['rental_hourly_rate'],
        (int) $expenseSettings['individual_rental_duration_minutes']
    );
    $categoryRepository = new ExpenseCategoryRepository($pdo);
    $methodRepository = new PaymentMethodRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $errors[] = 'Przesłany plik jest zbyt duży. Wybierz zdjęcie do 2 MB.';
        }

        if ($action === 'create') {
            $errors = $expenseService->create($_POST, $_FILES);
            if ($errors === []) {
                ResponseHelper::redirect('pages/expenses.php', ['saved' => 'created'] + expense_redirect_filter_params($_POST, substr((string) ($_POST['expense_date'] ?? date('Y-m')), 0, 7)));
            }
        } elseif ($action === 'update') {
            $errors = $expenseService->update((int) ($_POST['expense_id'] ?? 0), $_POST, $_FILES);
            if ($errors === []) {
                ResponseHelper::redirect('pages/expenses.php', ['saved' => 'updated'] + expense_redirect_filter_params($_POST, substr((string) ($_POST['expense_date'] ?? date('Y-m')), 0, 7)));
            }
        } elseif ($action === 'cancel') {
            $errors = $expenseService->cancel((int) ($_POST['expense_id'] ?? 0));
            if ($errors === []) {
                ResponseHelper::redirect('pages/expenses.php', ['saved' => 'cancelled'] + expense_redirect_filter_params($_POST));
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd połączenia z bazą danych: ' . $exception->getMessage();
}

render_page('Wydatki', 'expenses', function () use ($expenseService, $categoryRepository, $methodRepository, $errors, $notice, $lessonSchedule, $expenseSettings): void {
    $expenses = [];
    $categories = [];
    $methods = [];
    $summary = [];
    $categorySummary = [];
    $total = 0;
    $pageCount = 1;
    $editExpense = null;
    $filters = [
        'search' => '',
        'month' => date('Y-m'),
        'category' => 0,
        'method' => 0,
        'page' => 1,
        'per_page' => 10,
        'show_cancelled' => false,
    ];

    if ($expenseService instanceof ExpenseService && $categoryRepository instanceof ExpenseCategoryRepository && $methodRepository instanceof PaymentMethodRepository) {
        try {
            $categories = $categoryRepository->active();
            $methods = $methodRepository->active();
            $filters = $expenseService->filters($_GET);
            $pagination = $expenseService->paginated($filters);
            $expenses = $pagination['expenses'];
            $summary = $pagination['summary'];
            $total = $pagination['total'];
            $pageCount = $pagination['page_count'];
            $filters = $pagination['filters'];
            $categorySummary = $expenseService->byCategory($filters['month']);

            if (($_GET['modal'] ?? '') === 'edit' && isset($_GET['id'])) {
                $editExpense = $expenseService->find((int) $_GET['id']);
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się pobrać wydatków: ' . $exception->getMessage();
        }
    }

    $modal = (string) ($_GET['modal'] ?? '');
    $showAddModal = $modal === 'add' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create' && $errors !== []);
    $showEditModal = ($modal === 'edit' && $editExpense) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $errors !== []);
    $formExpense = $showEditModal ? ($editExpense ?: $_POST) : $_POST;
    $yearTotal = $expenseService instanceof ExpenseService ? $expenseService->yearTotal((int) substr($filters['month'], 0, 4)) : 0.0;
    $monthTotal = (float) ($summary['total_amount'] ?? 0);
    $highlightExpenseId = (int) ($_GET['highlight_expense'] ?? 0);
    $prefillRentalDate = (string) ($_GET['rental_date'] ?? '');
    $prefillRentalDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillRentalDate) ? $prefillRentalDate : '';
    $rentalCategoryId = 0;
    $categoryAmounts = [];
    foreach ($categorySummary as $categoryRow) {
        $categoryAmounts[(int) $categoryRow['category_id']] = (float) $categoryRow['amount'];
    }
    $categoryTotal = array_sum($categoryAmounts);
    $categoryRows = [];
    foreach ($categories as $index => $category) {
        if ((string) ($category['slug'] ?? '') === 'wynajem') {
            $rentalCategoryId = (int) $category['id'];
        }
        $amount = $categoryAmounts[(int) $category['id']] ?? 0.0;
        $categoryRows[] = [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'slug' => (string) $category['slug'],
            'amount' => $amount,
            'percent' => $categoryTotal > 0 ? ($amount / $categoryTotal) * 100 : 0,
            'color' => expense_category_color((string) $category['slug'], $index),
        ];
    }
    $gradientParts = [];
    $cursor = 0.0;
    foreach ($categoryRows as $category) {
        if ($category['amount'] <= 0 || $categoryTotal <= 0) {
            continue;
        }
        $next = $cursor + $category['percent'];
        $gradientParts[] = $category['color'] . ' ' . $cursor . '% ' . $next . '%';
        $cursor = $next;
    }
    $donutGradient = $gradientParts !== [] ? 'conic-gradient(' . implode(', ', $gradientParts) . ')' : 'conic-gradient(#EFE8DF 0 100%)';
    $filterParams = array_filter([
        'search' => $filters['search'],
        'month' => $filters['month'],
        'category' => $filters['category'] ?: null,
        'method' => $filters['method'] ?: null,
        'per_page' => $filters['per_page'],
        'highlight_expense' => $filters['highlight_expense'] ?: null,
    ], static fn ($value) => $value !== null && $value !== '');
    ?>
    <div class="expenses-page">
    <section class="module-hero expenses-hero">
        <div>
            <h1>Wydatki</h1>
            <p>Zarządzaj kosztami szkoły i kontroluj zysk netto.</p>
        </div>
        <div class="module-actions">
            <button class="btn btn-secondary" type="button" disabled aria-disabled="true">Eksport CSV</button>
            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/expenses.php?' . http_build_query($filterParams + ['modal' => 'add']))) ?>">
                <?= IconHelper::svg('add') ?>
                Dodaj wydatek
            </a>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Zmiany zostały zapisane.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="kpi-grid expenses-kpi-grid">
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('wallet') ?></span>
            <div><span class="kpi-label">Wydatki w tym miesiącu</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($monthTotal)) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('calendar') ?></span>
            <div><span class="kpi-label">Wydatki w tym roku</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($yearTotal)) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('profit') ?></span>
            <div><span class="kpi-label">Średnio miesięcznie</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($yearTotal / max(1, (int) date('n')))) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('hash') ?></span>
            <div><span class="kpi-label">Liczba wydatków</span><strong class="kpi-value"><?= FormatHelper::e((string) ($summary['total_count'] ?? 0)) ?></strong></div>
        </article>
    </section>

    <section class="payments-layout expenses-layout">
        <section class="card module-panel payments-main-panel expenses-main-panel">
            <form class="filter-bar payments-filter-bar expenses-filter-bar" action="<?= FormatHelper::e(url('pages/expenses.php')) ?>" method="get" aria-label="Filtry wydatków" data-expenses-direct-filter data-filter-target="#expenses-results">
                <label class="form-field filter-search">
                    <span class="form-label">Szukaj</span>
                    <span class="search-field">
                        <input type="search" name="search" value="<?= FormatHelper::e($filters['search']) ?>" placeholder="Szukaj wydatku..." aria-label="Szukaj wydatku">
                    </span>
                </label>
                <label class="form-field">
                    <span class="form-label">Miesiąc</span>
                    <input class="input" type="month" name="month" value="<?= FormatHelper::e($filters['month']) ?>">
                </label>
                <label class="form-field">
                    <span class="form-label">Kategoria</span>
                    <select class="select" name="category">
                        <option value="">Wszystkie</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= FormatHelper::e((string) $category['id']) ?>" <?= $filters['category'] === (int) $category['id'] ? 'selected' : '' ?>><?= FormatHelper::e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Metoda</span>
                    <select class="select" name="method">
                        <option value="">Wszystkie</option>
                        <?php foreach ($methods as $method): ?>
                            <option value="<?= FormatHelper::e((string) $method['id']) ?>" <?= $filters['method'] === (int) $method['id'] ? 'selected' : '' ?>><?= FormatHelper::e($method['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="filter-actions payments-filter-reset expenses-filter-reset">
                    <a class="btn btn-secondary btn-icon" href="<?= FormatHelper::e(url('pages/expenses.php')) ?>" aria-label="Reset filtrów" title="Reset filtrów">
                        <?= IconHelper::svg('close') ?>
                    </a>
                </div>
            </form>

            <div id="expenses-results">
            <?php if ($expenses === []): ?>
                <section class="empty-state module-empty">
                    <div class="empty-illustration" aria-hidden="true">+</div>
                    <h2 class="empty-state-title">Brak wydatków</h2>
                    <p class="empty-state-text">Dodaj pierwszy wydatek, aby rozpocząć kontrolę kosztów.</p>
                    <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/expenses.php?' . http_build_query($filterParams + ['modal' => 'add']))) ?>">Dodaj wydatek</a>
                </section>
            <?php else: ?>
                <div class="table-wrap payments-table-wrap expenses-table-wrap">
                    <table class="table payments-table expenses-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Kategoria</th>
                                <th>Opis</th>
                                <th>Kwota</th>
                                <th>Metoda</th>
                                <th>Status</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <?php $isHighlightedExpense = $highlightExpenseId === (int) $expense['id']; ?>
                                <tr id="expense-<?= FormatHelper::e((string) $expense['id']) ?>" class="<?= $isHighlightedExpense ? 'is-highlighted' : '' ?>">
                                    <td><?= FormatHelper::e(FormatHelper::date($expense['expense_date'])) ?></td>
                                    <td>
                                        <span class="expense-icon-label">
                                            <span class="expense-mini-icon <?= FormatHelper::e(expense_category_icon_class($expense['category_slug'] ?? null)) ?>" aria-hidden="true"><?= expense_category_svg($expense['category_slug'] ?? null) ?></span>
                                            <span><?= FormatHelper::e($expense['category_name']) ?></span>
                                        </span>
                                    </td>
                                    <td class="expense-description-cell"><strong><?= FormatHelper::e($expense['description'] ?: 'Brak opisu') ?></strong></td>
                                    <td class="expense-amount-cell"><strong><?= FormatHelper::e(FormatHelper::money($expense['amount'], $expense['currency'])) ?></strong></td>
                                    <td>
                                        <span class="expense-icon-label">
                                            <span class="expense-mini-icon <?= FormatHelper::e(expense_method_icon_class($expense['payment_method_slug'] ?? null)) ?>" aria-hidden="true"><?= expense_method_svg($expense['payment_method_slug'] ?? null) ?></span>
                                            <span><?= FormatHelper::e($expense['payment_method_name'] ?: '-') ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-dot <?= FormatHelper::e(FormatHelper::expenseStatusBadge($expense['status'])) ?>">
                                            <?= FormatHelper::e(FormatHelper::expenseStatusLabel($expense['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <?php if (!empty($expense['receipt_path'])): ?>
                                                <a class="btn btn-secondary btn-icon btn-sm action-icon" href="<?= FormatHelper::e(url('pages/expense_receipt.php?id=' . $expense['id'])) ?>" target="_blank" rel="noopener" aria-label="Zobacz zdjęcie wydatku" title="Zobacz zdjęcie wydatku"><?= expense_svg_icon('image') ?></a>
                                            <?php endif; ?>
                                            <a class="btn btn-secondary btn-icon btn-sm action-icon action-icon-edit" href="<?= FormatHelper::e(url('pages/expenses.php?' . http_build_query($filterParams + ['modal' => 'edit', 'id' => $expense['id']]))) ?>" aria-label="Edytuj wydatek"><?= expense_svg_icon('edit') ?></a>
                                            <form method="post">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="expense_id" value="<?= FormatHelper::e((string) $expense['id']) ?>">
                                                <?php expense_filter_hidden_fields($filters); ?>
                                                <button class="btn btn-secondary btn-icon btn-sm action-icon action-icon-cancel" type="submit" aria-label="Anuluj wydatek"><?= expense_svg_icon('cancel') ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <footer class="table-footer">
                    <p>Wyświetlanie <?= FormatHelper::e((string) (count($expenses) === 0 ? 0 : (($filters['page'] - 1) * $filters['per_page'] + 1))) ?>-<?= FormatHelper::e((string) min($total, ($filters['page'] - 1) * $filters['per_page'] + count($expenses))) ?> z <?= FormatHelper::e((string) $total) ?> wydatków</p>
                    <nav class="pagination" aria-label="Paginacja wydatków">
                        <?php
                        $baseParams = $filterParams;
                        ?>
                        <a class="page-link" href="<?= FormatHelper::e(url('pages/expenses.php?' . http_build_query($baseParams + ['page' => max(1, $filters['page'] - 1)]))) ?>"><?= IconHelper::svg('chevron-left') ?></a>
                        <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                            <a class="page-link <?= $page === $filters['page'] ? 'is-active' : '' ?>" href="<?= FormatHelper::e(url('pages/expenses.php?' . http_build_query($baseParams + ['page' => $page]))) ?>"><?= $page ?></a>
                        <?php endfor; ?>
                        <a class="page-link" href="<?= FormatHelper::e(url('pages/expenses.php?' . http_build_query($baseParams + ['page' => min($pageCount, $filters['page'] + 1)]))) ?>"><?= IconHelper::svg('chevron-right') ?></a>
                    </nav>
                </footer>
            <?php endif; ?>
            </div>
        </section>

        <aside class="payments-side expenses-side">
            <section class="card payments-summary-card expenses-chart-card">
                <h2 class="card-title">Wydatki w <?= FormatHelper::e(expense_month_title($filters['month'])) ?></h2>
                <div class="expenses-chart-body">
                <div class="donut-chart expenses-donut" style="background: <?= FormatHelper::e($donutGradient) ?>">
                    <span><strong><?= FormatHelper::e(FormatHelper::money($categoryTotal)) ?></strong><small>Łącznie</small></span>
                </div>
                <div class="expenses-legend">
                    <?php foreach ($categoryRows as $category): ?>
                        <?php if ($category['amount'] <= 0): ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <div class="expenses-legend-row">
                            <span class="legend-dot" style="background: <?= FormatHelper::e($category['color']) ?>"></span>
                            <span><?= FormatHelper::e($category['name']) ?></span>
                            <strong><?= FormatHelper::e(number_format($category['percent'], 1, ',', ' ')) ?>%</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>
            </section>
            <section class="card payments-summary-card expenses-category-card">
                <h2 class="card-title">Wydatki wg kategorii</h2>
                <?php if ($categorySummary === []): ?>
                    <p class="profile-note">Brak danych dla wybranego miesiąca.</p>
                <?php endif; ?>
                <?php foreach ($categoryRows as $category): ?>
                    <div class="summary-row expenses-category-row">
                        <span class="expense-icon-label">
                            <span class="expense-mini-icon <?= FormatHelper::e(expense_category_icon_class($category['slug'])) ?>" aria-hidden="true"><?= expense_category_svg($category['slug']) ?></span>
                            <span><?= FormatHelper::e($category['name']) ?></span>
                        </span>
                        <strong><?= FormatHelper::e(FormatHelper::money((string) $category['amount'])) ?></strong>
                    </div>
                <?php endforeach; ?>
            </section>
        </aside>
    </section>
    </div>

    <?php
    $renderExpenseModal = static function (string $mode, array $values, array $categories, array $methods, array $lessonSchedule, array $reservedRentalDates, array $filters, array $filterParams, array $expenseSettings): void {
        $isEdit = $mode === 'edit';
        $action = $isEdit ? 'update' : 'create';
        $closeUrl = url('pages/expenses.php' . ($filterParams !== [] ? '?' . http_build_query($filterParams) : ''));
        $statusOptions = ['paid', 'draft', 'submitted', 'approved', 'rejected', 'cancelled'];
        $selectedStatus = (string) ($values['status'] ?? ($isEdit ? 'draft' : 'paid'));
        $selectedCategoryId = (int) ($values['expense_category_id'] ?? 0);
        $selectedCategorySlug = '';
        foreach ($categories as $category) {
            if ((int) $category['id'] === $selectedCategoryId) {
                $selectedCategorySlug = (string) $category['slug'];
                break;
            }
        }
        $isAutoPricedRental = in_array($selectedCategorySlug, ['wynajem', 'wynajem-indywidualny'], true);
        $selectedRentalDate = (string) ($values['rental_date'] ?? '');
        if ($selectedRentalDate === '') {
            $selectedRentalDate = (string) (expense_rental_date_from_description((string) ($values['description'] ?? '')) ?? '');
        }
        $selectedIndividualRentalDate = (string) ($values['individual_rental_date'] ?? '');
        if ($selectedIndividualRentalDate === '') {
            $selectedIndividualRentalDate = (string) (expense_rental_date_from_description((string) ($values['description'] ?? '')) ?? '');
        }
        $individualRentalTime = expense_individual_rental_time_from_description((string) ($values['description'] ?? ''));
        $manualDescription = expense_manual_description((string) ($values['description'] ?? ''));
        $comment = array_key_exists('comment', $values)
            ? trim((string) $values['comment'])
            : expense_comment_from_description((string) ($values['description'] ?? ''));
        $rentalOptions = expense_rental_options($lessonSchedule, $selectedRentalDate, $reservedRentalDates);
        $individualRentalOptions = expense_rental_options($lessonSchedule, $selectedIndividualRentalDate, []);
        if ($isEdit && !in_array($selectedStatus, $statusOptions, true)) {
            $statusOptions[] = $selectedStatus;
        }
        ?>
        <div class="modal-backdrop is-open">
            <section class="modal payment-modal expense-editor-modal">
                <div class="expense-modal-hero">
                    <div class="expense-modal-heading">
                        <span class="expense-modal-icon"><?= IconHelper::svg('expense') ?></span>
                        <div>
                            <h2 class="expense-modal-title"><?= $isEdit ? 'Edytuj wydatek' : 'Dodaj wydatek' ?></h2>
                            <p class="expense-modal-subtitle"><?= $isEdit ? 'Zaktualizuj dane kosztu bez zmiany układu finansów.' : 'Dodaj koszt szkoły i zachowaj porządek w rozliczeniach.' ?></p>
                        </div>
                    </div>
                    <a class="expense-modal-close" href="<?= FormatHelper::e($closeUrl) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
                </div>
                <form class="payment-form-grid expense-modal-form" method="post" enctype="multipart/form-data" data-expense-form>
                    <input type="hidden" name="action" value="<?= FormatHelper::e($action) ?>">
                    <?php expense_filter_hidden_fields($filters); ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="expense_id" value="<?= FormatHelper::e((string) ($values['id'] ?? $values['expense_id'] ?? '')) ?>">
                    <?php endif; ?>
                    <label class="form-field">
                        <span class="form-label">Data</span>
                        <input class="input" type="date" name="expense_date" value="<?= FormatHelper::e((string) ($values['expense_date'] ?? date('Y-m-d'))) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Kategoria</span>
                        <select class="select" name="expense_category_id" required data-expense-category-select>
                            <option value="">Wybierz kategorię</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= FormatHelper::e((string) $category['id']) ?>"
                                    data-slug="<?= FormatHelper::e($category['slug']) ?>"
                                    <?= (int) ($values['expense_category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>
                                >
                                    <?= FormatHelper::e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field" data-expense-amount-field>
                        <span class="form-label">Kwota</span>
                        <input class="input" type="number" min="0.01" step="0.01" name="amount" value="<?= FormatHelper::e((string) ($values['amount'] ?? '')) ?>" required data-expense-amount>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Metoda płatności</span>
                        <select class="select" name="payment_method_id" required>
                            <option value="">Wybierz metodę</option>
                            <?php foreach ($methods as $method): ?>
                                <option value="<?= FormatHelper::e((string) $method['id']) ?>" <?= (int) ($values['payment_method_id'] ?? 0) === (int) $method['id'] ? 'selected' : '' ?>><?= FormatHelper::e($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="form-field expense-status-field">
                        <span class="form-label">Status</span>
                        <div class="expense-status-options">
                            <?php foreach ($statusOptions as $status): ?>
                                <label class="expense-status-option expense-status-option-<?= FormatHelper::e($status) ?>">
                                    <input
                                        type="radio"
                                        name="status"
                                        value="<?= FormatHelper::e($status) ?>"
                                        <?= $selectedStatus === $status ? 'checked' : '' ?>
                                    >
                                    <span><?= FormatHelper::e(FormatHelper::expenseStatusLabel($status)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <section class="expense-category-panel expense-modal-section payment-form-wide" data-expense-category-panel="wynajem" hidden>
                        <h3 class="form-section-title">Rezerwacja sali</h3>
                        <div class="payment-form-grid">
                            <div class="form-field payment-form-wide">
                                <span class="form-label">Termin zajęć</span>
                                <?php if ($rentalOptions === []): ?>
                                    <p class="profile-note">Brak wolnych terminów w najbliższych 3 miesiącach.</p>
                                <?php else: ?>
                                    <div class="rental-term-list" data-rental-date-select>
                                        <?php foreach ($rentalOptions as $option): ?>
                                            <label class="rental-term-option">
                                                <input
                                                    type="radio"
                                                    name="rental_date"
                                                    value="<?= FormatHelper::e($option['date']) ?>"
                                                    data-start="<?= FormatHelper::e($option['start']) ?>"
                                                    data-end="<?= FormatHelper::e($option['end']) ?>"
                                                    <?= $selectedRentalDate === $option['date'] ? 'checked' : '' ?>
                                                >
                                                <span>
                                                    <strong><?= FormatHelper::e($option['label_date'] ?? $option['date']) ?></strong>
                                                    <em><?= FormatHelper::e(($option['label_day'] ?? '') . ' - ' . ($option['label_time'] ?? '')) ?></em>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label class="form-field">
                                <span class="form-label">Godzina</span>
                                <input class="input" type="text" value="" readonly data-rental-time-preview>
                            </label>
                            <input type="hidden" name="rental_hours" value="<?= FormatHelper::e((string) ($values['rental_hours'] ?? '1')) ?>" data-rental-hours>
                            <input type="hidden" name="rental_rate" value="<?= FormatHelper::e(number_format((float) ($expenseSettings['rental_hourly_rate'] ?? 32.00), 2, '.', '')) ?>" data-rental-rate>
                            <input type="hidden" name="individual_rental_duration_minutes" value="<?= FormatHelper::e((string) (int) ($expenseSettings['individual_rental_duration_minutes'] ?? 60)) ?>" data-individual-rental-duration>
                        </div>
                    </section>
                    <section class="expense-category-panel expense-modal-section payment-form-wide" data-expense-category-panel="wynajem-indywidualny" hidden>
                        <h3 class="form-section-title">Rezerwacja sali na zajęcia indywidualne</h3>
                        <div class="payment-form-grid">
                            <div class="form-field payment-form-wide">
                                <span class="form-label">Termin zajęć</span>
                                <?php if ($individualRentalOptions === []): ?>
                                    <p class="profile-note">Brak terminów w najbliższych 3 miesiącach.</p>
                                <?php else: ?>
                                    <div class="rental-term-list" data-individual-rental-date-list>
                                        <?php foreach ($individualRentalOptions as $option): ?>
                                            <label class="rental-term-option">
                                                <input
                                                    type="radio"
                                                    name="individual_rental_date"
                                                    value="<?= FormatHelper::e($option['date']) ?>"
                                                    <?= $selectedIndividualRentalDate === $option['date'] ? 'checked' : '' ?>
                                                >
                                                <span>
                                                    <strong><?= FormatHelper::e($option['label_date'] ?? $option['date']) ?></strong>
                                                    <em><?= FormatHelper::e($option['label_day'] ?? '') ?></em>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label class="form-field">
                                <span class="form-label">Godzina rozpoczęcia</span>
                                <input class="input" type="time" name="individual_rental_start" value="<?= FormatHelper::e((string) ($values['individual_rental_start'] ?? $individualRentalTime['start'])) ?>" data-individual-rental-start>
                            </label>
                        </div>
                    </section>
                    <section class="expense-category-panel expense-modal-section payment-form-wide" data-expense-category-panel="marketing" hidden>
                        <h3 class="form-section-title">Reklama Instagram</h3>
                        <label class="form-field">
                            <span class="form-label">Kampania / opis reklamy</span>
                            <input class="input" name="instagram_campaign" value="<?= FormatHelper::e($values['instagram_campaign'] ?? '') ?>" placeholder="Np. Promowanie postów - maj">
                        </label>
                    </section>
                    <label class="form-field payment-form-wide">
                        <span class="form-label">Opis</span>
                        <input class="input" name="description" value="<?= FormatHelper::e($manualDescription) ?>" placeholder="Np. Wynajem sali - maj">
                    </label>
                    <label class="form-field payment-form-wide">
                        <span class="form-label">Komentarz</span>
                        <textarea class="textarea" name="comment" placeholder="Komentarz opcjonalny"><?= FormatHelper::e($comment) ?></textarea>
                    </label>
                    <div class="form-field payment-form-wide">
                        <label class="form-label" for="expense-receipt">Zdjęcie wydatku (opcjonalnie)</label>
                        <?php if (!empty($values['receipt_path'])): ?>
                            <a href="<?= FormatHelper::e(url('pages/expense_receipt.php?id=' . $values['id'])) ?>" target="_blank" rel="noopener">Otwórz zdjęcie wydatku</a>
                        <?php endif; ?>
                        <input class="input" id="expense-receipt" type="file" name="expense_receipt" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG lub WebP, maks. 2 MB. Bez nowego pliku obecne zdjęcie pozostaje.</small>
                    </div>
                    <div class="payment-form-actions expense-modal-actions">
                        <a class="btn btn-secondary" href="<?= FormatHelper::e($closeUrl) ?>">Anuluj</a>
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Zapisz zmiany' : 'Zapisz wydatek' ?></button>
                    </div>
                </form>
            </section>
        </div>
        <?php
    };

    if ($showAddModal) {
        $reservedRentalDates = $expenseService->reservedRentalDates();
        $addValues = $_POST;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $prefillRentalDate !== '') {
            $addValues = [
                'expense_date' => date('Y-m-d'),
                'expense_category_id' => $rentalCategoryId,
                'rental_date' => $prefillRentalDate,
                'amount' => '',
                'status' => 'paid',
            ];
        }
        $renderExpenseModal('add', $addValues, $categories, $methods, $lessonSchedule, $reservedRentalDates, $filters, $filterParams, $expenseSettings);
    }

    if ($showEditModal) {
        $editExpenseId = (int) ($formExpense['id'] ?? 0);
        $reservedRentalDates = $expenseService->reservedRentalDates($editExpenseId > 0 ? $editExpenseId : null);
        $renderExpenseModal('edit', $formExpense, $categories, $methods, $lessonSchedule, $reservedRentalDates, $filters, $filterParams, $expenseSettings);
    }
});
