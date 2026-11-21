<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\SalaryRepository;
use App\Services\SalaryService;
use App\Validators\SalaryValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$salaryService = null;

try {
    $salaryService = new SalaryService(new SalaryRepository(db()), new SalaryValidator());

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $errors = $salaryService->createPayout($_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/salaries.php', ['saved' => 'created']);
            }
        } elseif ($action === 'cancel') {
            $errors = $salaryService->cancel((int) ($_POST['payout_id'] ?? 0));
            if ($errors === []) {
                ResponseHelper::redirect('pages/salaries.php', ['saved' => 'cancelled']);
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd połączenia z bazą danych: ' . $exception->getMessage();
}

render_page('Wynagrodzenia', 'salaries', function () use ($salaryService, $errors, $notice): void {
    $filters = [
        'month' => date('Y-m'),
        'employee' => '',
        'status' => '',
        'page' => 1,
        'per_page' => 10,
    ];
    $payouts = [];
    $summary = [];
    $rules = [];
    $preview = [
        'teacher' => 0,
        'accountant' => 0,
        'marketing' => 0,
        'marketing_rate' => 45,
        'marketing_new_students' => 0,
        'total' => 0,
        'profit_base' => 0,
        'revenue' => 0,
        'non_salary_expenses' => 0,
        'teacher_percent' => 0,
        'accountant_label' => 'Księgowa',
        'teacher_label' => 'Nauczycielka',
        'marketing_label' => 'Marketing',
    ];
    $total = 0;
    $pageCount = 1;

    if ($salaryService instanceof SalaryService) {
        try {
            $filters = $salaryService->filters($_GET);
            $pagination = $salaryService->paginated($filters);
            $payouts = $pagination['payouts'];
            $summary = $pagination['summary'];
            $total = $pagination['total'];
            $pageCount = $pagination['page_count'];
            $filters = $pagination['filters'];
            $preview = $salaryService->preview($filters['month']);
            $rules = $salaryService->activeRules();
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się pobrać wynagrodzeń: ' . $exception->getMessage();
        }
    }

    $showAddModal = ($_GET['modal'] ?? '') === 'add' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create' && $errors !== []);
    ?>
    <section class="module-hero">
        <div>
            <h1>Wynagrodzenia</h1>
            <p>Zarządzaj wynagrodzeniami, planuj wypłaty i przeglądaj historię.</p>
        </div>
        <div class="module-actions">
            <button class="btn btn-secondary" type="button" disabled aria-disabled="true">Eksport CSV</button>
            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/salaries.php?modal=add&month=' . $filters['month'])) ?>">
                <?= IconHelper::svg('add') ?>
                Dodaj wypłatę
            </a>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Zmiany zostały zapisane.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="payments-layout">
        <section class="payments-main-panel">
            <section class="card module-panel">
                <form class="filter-bar payments-filter-bar" action="<?= FormatHelper::e(url('pages/salaries.php')) ?>" method="get" aria-label="Filtry wynagrodzeń">
                    <label class="form-field">
                        <span class="form-label">Miesiąc</span>
                        <input class="input" type="month" name="month" value="<?= FormatHelper::e($filters['month']) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Pracownik</span>
                        <select class="select" name="employee">
                            <option value="">Wszyscy pracownicy</option>
                            <?php foreach (SalaryService::EMPLOYEES as $value => $label): ?>
                                <option value="<?= FormatHelper::e($value) ?>" <?= $filters['employee'] === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <option value="">Wszystkie statusy</option>
                            <?php foreach (['calculated', 'paid', 'cancelled'] as $status): ?>
                                <option value="<?= FormatHelper::e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= FormatHelper::e(FormatHelper::salaryStatusLabel($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="filter-actions">
                        <button class="btn btn-secondary" type="submit">Filtruj</button>
                        <a class="btn btn-ghost" href="<?= FormatHelper::e(url('pages/salaries.php')) ?>">Reset filtrów</a>
                    </div>
                </form>
            </section>

            <section class="kpi-grid">
                <article class="card kpi-card">
                    <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('wallet') ?></span>
                    <div><span class="kpi-label">Łączne wynagrodzenia</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($preview['total'])) ?></strong></div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('calendar') ?></span>
                    <div><span class="kpi-label">Nauczycielka</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($preview['teacher'])) ?></strong></div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('profit') ?></span>
                    <div><span class="kpi-label">Księgowa</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($preview['accountant'])) ?></strong></div>
                </article>
                <article class="card kpi-card">
                    <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('users') ?></span>
                    <div><span class="kpi-label">Marketing</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($preview['marketing'])) ?></strong></div>
                </article>
            </section>

            <section class="card module-panel">
                <div class="card-header">
                    <h2 class="card-title">Wynagrodzenia w <?= FormatHelper::e(FormatHelper::monthName($filters['month'] . '-01')) ?></h2>
                </div>
                <?php if ($payouts === []): ?>
                    <section class="empty-state module-empty">
                        <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('wallet') ?></div>
                        <h2 class="empty-state-title">Brak wypłat</h2>
                        <p class="empty-state-text">Dodaj pierwszą wypłatę dla wybranego miesiąca.</p>
                        <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/salaries.php?modal=add&month=' . $filters['month'])) ?>">Dodaj wypłatę</a>
                    </section>
                <?php else: ?>
                    <div class="table-wrap payments-table-wrap">
                        <table class="table payments-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Pracownik</th>
                                    <th>Typ wynagrodzenia</th>
                                    <th>Kwota</th>
                                    <th>Status</th>
                                    <th>Notatki</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payouts as $payout): ?>
                                    <tr>
                                        <td><?= FormatHelper::e(FormatHelper::date($payout['paid_at'] ?: $payout['period_end'])) ?></td>
                                        <td>
                                            <div class="student-name">
                                                <strong><?= FormatHelper::e(SalaryService::EMPLOYEES[$payout['role_scope']] ?? (string) $payout['role_scope']) ?></strong>
                                                <span><?= FormatHelper::e($payout['employee_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= FormatHelper::e(match ((string) $payout['rule_type']) {
                                                'fixed' => 'Stała kwota',
                                                'per_student' => 'Za nową uczennicę',
                                                'remaining_profit' => 'Pozostały zysk',
                                                default => 'Procent od zysku netto',
                                            }) ?></strong>
                                            <span><?= match ((string) $payout['rule_type']) {
                                                'fixed' => FormatHelper::e(FormatHelper::money($payout['rule_amount'])) . ' / mc',
                                                'per_student' => FormatHelper::e(FormatHelper::money($payout['rule_amount'])) . ' / uczennica',
                                                'remaining_profit' => 'Cały pozostały zysk',
                                                default => FormatHelper::e((string) $payout['rule_percentage']) . '%',
                                            } ?></span>
                                        </td>
                                        <td><strong><?= FormatHelper::e(FormatHelper::money($payout['total_amount'], $payout['currency'])) ?></strong></td>
                                        <td>
                                            <span class="badge badge-dot <?= FormatHelper::e(FormatHelper::salaryStatusBadge($payout['status'])) ?>">
                                                <?= FormatHelper::e(FormatHelper::salaryStatusLabel($payout['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= FormatHelper::e($payout['notes'] ?: '-') ?></td>
                                        <td>
                                            <form method="post">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="payout_id" value="<?= FormatHelper::e((string) $payout['id']) ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit">Anuluj</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <footer class="table-footer">
                        <p>Wyświetlanie <?= FormatHelper::e((string) (count($payouts) === 0 ? 0 : (($filters['page'] - 1) * $filters['per_page'] + 1))) ?>-<?= FormatHelper::e((string) min($total, ($filters['page'] - 1) * $filters['per_page'] + count($payouts))) ?> z <?= FormatHelper::e((string) $total) ?> wypłat</p>
                    </footer>
                <?php endif; ?>
            </section>

            <section class="card module-panel">
                <h2 class="card-title">Jak działają wynagrodzenia?</h2>
                <div class="learning-grid">
                    <div><span>Baza</span><strong>Przychód minus koszty</strong><em>Bez kosztów wynagrodzeń</em></div>
                    <div><span>Nauczycielka</span><strong>Pozostały zysk</strong><em>Po pozostałych wynagrodzeniach</em></div>
                    <div><span>Księgowa</span><strong><?= FormatHelper::e((string) $preview['accountant_label']) ?></strong><em>Reguła edytowana w ustawieniach</em></div>
                    <div><span>Marketing</span><strong><?= FormatHelper::e((string) $preview['marketing_label']) ?></strong><em>Za nowe opłacone uczennice</em></div>
                    <div><span>Wypłata</span><strong>Tworzy koszt</strong><em>Dashboard aktualizuje się automatycznie</em></div>
                </div>
            </section>
        </section>

        <aside class="payments-side">
            <section class="card payments-summary-card">
                <h2 class="card-title">Podsumowanie miesiąca</h2>
                <div class="summary-row"><span>Łączne wynagrodzenia</span><strong><?= FormatHelper::e(FormatHelper::money($preview['total'])) ?></strong></div>
                <div class="summary-row"><span>Wypłacone</span><strong><?= FormatHelper::e(FormatHelper::money($summary['paid_amount'] ?? 0)) ?></strong></div>
                <div class="summary-row"><span>Do wypłaty</span><strong><?= FormatHelper::e(FormatHelper::money(max(0, $preview['total'] - (float) ($summary['paid_amount'] ?? 0)))) ?></strong></div>
                <div class="summary-row"><span>Baza zysku</span><strong><?= FormatHelper::e(FormatHelper::money($preview['profit_base'])) ?></strong></div>
                <div class="summary-row"><span>Nowe uczennice z reklam</span><strong><?= FormatHelper::e((string) $preview['marketing_new_students']) ?></strong></div>
            </section>
            <section class="card payments-summary-card">
                <h2 class="card-title">Najbliższe wypłaty</h2>
                <div class="summary-row"><span>Teacher</span><strong><?= FormatHelper::e(FormatHelper::money($preview['teacher'])) ?></strong></div>
                <div class="summary-row"><span>Accountant</span><strong><?= FormatHelper::e(FormatHelper::money($preview['accountant'])) ?></strong></div>
                <div class="summary-row"><span>Marketing specialist</span><strong><?= FormatHelper::e(FormatHelper::money($preview['marketing'])) ?></strong></div>
                <a class="btn btn-secondary btn-block" href="<?= FormatHelper::e(url('pages/salaries.php?modal=add&month=' . $filters['month'])) ?>">Dodaj wypłatę</a>
            </section>
            <section class="card payments-summary-card">
                <h2 class="card-title">Reguły wynagrodzeń</h2>
                <?php foreach ($rules as $rule): ?>
                    <div class="summary-row">
                        <span><?= FormatHelper::e(SalaryService::EMPLOYEES[$rule['role_scope']] ?? (string) $rule['role_scope']) ?></span>
                        <strong><?= match ((string) $rule['rule_type']) {
                            'fixed' => FormatHelper::e(FormatHelper::money($rule['amount'])),
                            'per_student' => FormatHelper::e(FormatHelper::money($rule['amount'])) . ' / uczennica',
                            'remaining_profit' => 'Pozostały zysk',
                            default => FormatHelper::e((string) $rule['percentage']) . '%',
                        } ?></strong>
                    </div>
                <?php endforeach; ?>
            </section>
        </aside>
    </section>

    <?php
    $renderSalaryModal = static function (array $values, array $filters, array $preview): void {
        ?>
        <div class="modal-backdrop is-open">
            <section class="modal payment-modal">
                <div class="card-header">
                    <h2 class="card-title">Dodaj wypłatę</h2>
                    <a class="btn btn-ghost btn-icon" href="<?= FormatHelper::e(url('pages/salaries.php')) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
                </div>
                <form class="payment-form-grid" method="post" data-salary-form>
                    <input type="hidden" name="action" value="create">
                    <label class="form-field">
                        <span class="form-label">Pracownik</span>
                        <select class="select" name="employee" required data-salary-employee>
                            <?php foreach (SalaryService::EMPLOYEES as $value => $label): ?>
                                <option value="<?= FormatHelper::e($value) ?>" data-amount="<?= FormatHelper::e((string) $preview[$value]) ?>" <?= ($values['employee'] ?? 'teacher') === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Miesiąc</span>
                        <input class="input" type="month" name="month" value="<?= FormatHelper::e((string) ($values['month'] ?? $_GET['month'] ?? $filters['month'])) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data wypłaty</span>
                        <input class="input" type="date" name="paid_at" value="<?= FormatHelper::e((string) ($values['paid_at'] ?? date('Y-m-d'))) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <option value="paid" <?= ($values['status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>Wypłacona</option>
                            <option value="calculated" <?= ($values['status'] ?? '') === 'calculated' ? 'selected' : '' ?>>Do wypłaty</option>
                        </select>
                    </label>
                    <label class="form-field payment-form-wide">
                        <span class="form-label">Kwota wyliczona</span>
                        <input class="input" value="" data-salary-amount-preview disabled>
                    </label>
                    <label class="form-field payment-form-wide">
                        <span class="form-label">Notatki</span>
                        <textarea class="textarea" name="notes" placeholder="Opcjonalne notatki"><?= FormatHelper::e($values['notes'] ?? '') ?></textarea>
                    </label>
                    <div class="payment-form-actions">
                        <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/salaries.php')) ?>">Anuluj</a>
                        <button class="btn btn-primary" type="submit">Zapisz wypłatę</button>
                    </div>
                </form>
            </section>
        </div>
        <?php
    };

    if ($showAddModal) {
        $renderSalaryModal($_POST, $filters, $preview);
    }
});
