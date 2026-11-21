<?php

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Validators\PaymentValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$paymentService = null;
$methodRepository = null;
$subscriptionRepository = null;
$studentRepository = null;
$paymentSettings = ['show_cancelled' => false];

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));
    $paymentSettings = $settingsService->payments();
    $paymentService = new PaymentService(new PaymentRepository($pdo), new PaymentValidator(), (bool) $paymentSettings['show_cancelled']);
    $methodRepository = new PaymentMethodRepository($pdo);
    $subscriptionRepository = new SubscriptionRepository($pdo);
    $studentRepository = new StudentRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $errors[] = 'Przesłany plik jest zbyt duży. Wybierz zdjęcie do 2 MB i spróbuj ponownie.';
        }

        if ($action === 'create') {
            $errors = $paymentService->create($_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/payments.php', ['saved' => 'created']);
            }
        } elseif ($action === 'update') {
            $errors = $paymentService->update((int) ($_POST['payment_id'] ?? 0), $_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/payments.php', ['saved' => 'updated']);
            }
        } elseif ($action === 'cancel') {
            $errors = $paymentService->cancel((int) ($_POST['payment_id'] ?? 0));
            if ($errors === []) {
                ResponseHelper::redirect('pages/payments.php', ['saved' => 'cancelled']);
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd połączenia z bazą danych: ' . $exception->getMessage();
}

render_page('Płatności', 'payments', function () use ($paymentService, $methodRepository, $subscriptionRepository, $studentRepository, $paymentSettings, $errors, $notice): void {
    $payments = [];
    $methods = [];
    $subscriptions = [];
    $students = [];
    $summary = [];
    $total = 0;
    $pageCount = 1;
    $editPayment = null;
    $addPeriodMonth = date('Y-m');
    $filters = [
        'search' => '',
        'period' => 'month',
        'month' => date('Y-m'),
        'method' => 0,
        'status' => '',
        'subscription' => 0,
        'student_id' => 0,
        'page' => 1,
        'per_page' => 10,
        'show_cancelled' => false,
    ];

    if ($paymentService instanceof PaymentService && $methodRepository instanceof PaymentMethodRepository && $subscriptionRepository instanceof SubscriptionRepository && $studentRepository instanceof StudentRepository) {
        try {
            $methods = $methodRepository->active();
            $subscriptions = $subscriptionRepository->active();
            $filters = $paymentService->filters($_GET);
            $addPeriodMonth = (string) ($_POST['period_month'] ?? $_GET['period_month'] ?? $filters['month']);
            $addPeriodMonth = preg_match('/^\d{4}-\d{2}$/', $addPeriodMonth) ? $addPeriodMonth : $filters['month'];
            $students = $studentRepository->activeForPaymentSelect($addPeriodMonth);
            $pagination = $paymentService->paginated($filters);
            $payments = $pagination['payments'];
            $summary = $pagination['summary'];
            $total = $pagination['total'];
            $pageCount = $pagination['page_count'];
            $filters = $pagination['filters'];

            if (($_GET['modal'] ?? '') === 'edit' && isset($_GET['id'])) {
                $editPayment = $paymentService->find((int) $_GET['id']);
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
                $editPayment = $paymentService->find((int) ($_POST['payment_id'] ?? 0));
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się pobrać płatności: ' . $exception->getMessage();
        }
    }

    $modal = (string) ($_GET['modal'] ?? '');
    $showEditModal = ($modal === 'edit' && $editPayment) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $errors !== []);
    $formPayment = $showEditModal ? ($editPayment ?: []) : $_POST;
    if ($showEditModal && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
        foreach (['amount', 'payment_date', 'payment_method_id', 'status', 'comment'] as $field) {
            if (array_key_exists($field, $_POST)) {
                $formPayment[$field] = $_POST[$field];
            }
        }
        $formPayment['payment_id'] = (int) ($_POST['payment_id'] ?? 0);
    }
    $selectedStudentId = (int) ($_GET['student_id'] ?? ($_POST['student_id'] ?? 0));
    $filterStatusOptions = ['completed', 'pending', 'partial', 'refunded'];
    if (!empty($paymentSettings['show_cancelled'])) {
        $filterStatusOptions[] = 'cancelled';
    }
    $summaryTitle = $filters['period'] === 'all' ? 'Cały okres' : FormatHelper::monthName($filters['month'] . '-01');
    ?>
    <section class="module-hero">
        <div>
            <h1>Płatności</h1>
            <p>Zarządzaj płatnościami uczennic.</p>
        </div>
        <div class="module-actions">
            <button class="btn btn-secondary" type="button" disabled aria-disabled="true">
                <?= IconHelper::svg('download') ?>
                Eksport CSV
            </button>
            <a class="btn btn-primary" href="#payment-add-card">
                <?= IconHelper::svg('add') ?>
                Dodaj płatność
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
        <section class="card module-panel payments-main-panel">
            <form class="filter-bar payments-filter-bar" action="<?= FormatHelper::e(url('pages/payments.php')) ?>" method="get" aria-label="Filtry płatności" data-auto-filter>
                <?php if ($filters['student_id'] > 0): ?>
                    <input type="hidden" name="student_id" value="<?= FormatHelper::e((string) $filters['student_id']) ?>">
                <?php endif; ?>
                <label class="form-field filter-search">
                    <span class="form-label">Szukaj</span>
                    <span class="search-field">
                        <input type="search" name="search" value="<?= FormatHelper::e($filters['search']) ?>" placeholder="Szukaj płatności..." aria-label="Szukaj płatności">
                    </span>
                </label>
                <label class="form-field">
                    <span class="form-label">Okres</span>
                    <select class="select" name="period">
                        <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>Wybrany miesiąc</option>
                        <option value="all" <?= $filters['period'] === 'all' ? 'selected' : '' ?>>Cały okres</option>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Miesiąc</span>
                    <input class="input" type="month" name="month" value="<?= FormatHelper::e($filters['month']) ?>">
                </label>
                <label class="form-field">
                    <span class="form-label">Metoda płatności</span>
                    <select class="select" name="method">
                        <option value="">Wszystkie</option>
                        <?php foreach ($methods as $method): ?>
                            <option value="<?= FormatHelper::e((string) $method['id']) ?>" <?= $filters['method'] === (int) $method['id'] ? 'selected' : '' ?>><?= FormatHelper::e($method['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Status</span>
                    <select class="select" name="status">
                        <option value="">Wszystkie</option>
                        <?php foreach ($filterStatusOptions as $status): ?>
                            <option value="<?= FormatHelper::e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= FormatHelper::e(FormatHelper::paymentStatusLabel($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Abonament</span>
                    <select class="select" name="subscription">
                        <option value="">Wszystkie</option>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <option value="<?= FormatHelper::e((string) $subscription['id']) ?>" <?= $filters['subscription'] === (int) $subscription['id'] ? 'selected' : '' ?>><?= FormatHelper::e($subscription['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="filter-actions payments-filter-reset">
                    <a class="btn btn-secondary btn-icon" href="<?= FormatHelper::e(url('pages/payments.php')) ?>" aria-label="Reset filtrów" title="Reset filtrów">
                        <?= IconHelper::svg('close') ?>
                    </a>
                </div>
            </form>

            <?php if ($payments === []): ?>
                <section class="empty-state module-empty">
                    <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('money') ?></div>
                    <h2 class="empty-state-title">Brak płatności</h2>
                    <p class="empty-state-text">Dodaj pierwszą płatność, aby rozpocząć rozliczenia.</p>
                    <a class="btn btn-primary" href="#payment-add-card">Dodaj płatność</a>
                </section>
            <?php else: ?>
                <div class="table-wrap payments-table-wrap">
                    <table class="table payments-table">
                        <thead>
                            <tr>
                                <th>Data płatności</th>
                                <th>Uczennica</th>
                                <th>Grupa</th>
                                <th>Abonament</th>
                                <th>Miesiąc</th>
                                <th>Kwota</th>
                                <th>Metoda płatności</th>
                                <th>Status</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $studentName = trim((string) $payment['first_name'] . ' ' . (string) $payment['last_name']);
                                $methodIcon = match ((string) $payment['payment_method_name']) {
                                    'Karta' => 'card',
                                    'Przelew' => 'transfer',
                                    'Gotówka' => 'money',
                                    'BLIK' => 'card',
                                    default => 'card',
                                };
                                ?>
                                <tr>
                                    <td><?= FormatHelper::e(FormatHelper::date($payment['payment_date'])) ?></td>
                                    <td>
                                        <div class="payment-student-cell">
                                            <?php if (!empty($payment['photo_path'])): ?>
                                                <img class="avatar avatar-photo avatar-sm" src="<?= FormatHelper::e(url((string) $payment['photo_path'])) ?>" alt="">
                                            <?php else: ?>
                                                <span class="avatar avatar-sm"><?= FormatHelper::e(FormatHelper::initials((string) $payment['first_name'], (string) $payment['last_name'])) ?></span>
                                            <?php endif; ?>
                                            <strong><?= FormatHelper::e($studentName) ?></strong>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-soft"><?= FormatHelper::e($payment['group_name'] ?: 'Brak grupy') ?></span></td>
                                    <td><?= FormatHelper::e($payment['subscription_name'] ?: '-') ?></td>
                                    <td><?= FormatHelper::e(FormatHelper::monthName((string) $payment['period_start'])) ?></td>
                                    <td><strong><?= FormatHelper::e(FormatHelper::money($payment['amount'], $payment['currency'])) ?></strong></td>
                                    <td>
                                        <div class="payment-method-cell">
                                            <?= IconHelper::svg($methodIcon) ?>
                                            <span><?= FormatHelper::e($payment['payment_method_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-dot <?= FormatHelper::e(FormatHelper::paymentStatusBadge($payment['status'])) ?>">
                                            <?= FormatHelper::e(FormatHelper::paymentStatusLabel($payment['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <?php if (!empty($payment['receipt_path'])): ?>
                                                <a class="btn btn-secondary btn-sm" href="<?= FormatHelper::e(url('pages/payment_receipt.php?id=' . $payment['id'])) ?>" target="_blank" rel="noopener">Zdjęcie</a>
                                            <?php endif; ?>
                                            <a class="btn btn-secondary btn-icon btn-sm" href="<?= FormatHelper::e(url('pages/payments.php?modal=edit&id=' . $payment['id'])) ?>" aria-label="Edytuj płatność"><?= IconHelper::svg('edit') ?></a>
                                            <form method="post">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="payment_id" value="<?= FormatHelper::e((string) $payment['id']) ?>">
                                                <button class="btn btn-secondary btn-icon btn-sm" type="submit" aria-label="Anuluj płatność"><?= IconHelper::svg('cancel') ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <footer class="table-footer">
                    <p>Wyświetlanie <?= FormatHelper::e((string) (count($payments) === 0 ? 0 : (($filters['page'] - 1) * $filters['per_page'] + 1))) ?>-<?= FormatHelper::e((string) min($total, ($filters['page'] - 1) * $filters['per_page'] + count($payments))) ?> z <?= FormatHelper::e((string) $total) ?> płatności</p>
                    <nav class="pagination" aria-label="Paginacja płatności">
                        <?php
                        $baseParams = array_filter([
                            'search' => $filters['search'],
                            'period' => $filters['period'] !== 'month' ? $filters['period'] : null,
                            'month' => $filters['month'],
                            'method' => $filters['method'] ?: null,
                            'status' => $filters['status'] ?: null,
                            'subscription' => $filters['subscription'] ?: null,
                            'student_id' => $filters['student_id'] ?: null,
                            'per_page' => $filters['per_page'],
                        ], static fn ($value) => $value !== null && $value !== '');
                        ?>
                        <a class="page-link" href="<?= FormatHelper::e(url('pages/payments.php?' . http_build_query($baseParams + ['page' => max(1, $filters['page'] - 1)]))) ?>"><?= IconHelper::svg('chevron-left') ?></a>
                        <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                            <a class="page-link <?= $page === $filters['page'] ? 'is-active' : '' ?>" href="<?= FormatHelper::e(url('pages/payments.php?' . http_build_query($baseParams + ['page' => $page]))) ?>"><?= $page ?></a>
                        <?php endfor; ?>
                        <a class="page-link" href="<?= FormatHelper::e(url('pages/payments.php?' . http_build_query($baseParams + ['page' => min($pageCount, $filters['page'] + 1)]))) ?>"><?= IconHelper::svg('chevron-right') ?></a>
                    </nav>
                </footer>
            <?php endif; ?>
        </section>

        <aside class="payments-side">
            <section class="card payment-quick-card" id="payment-add-card">
                <div class="payment-quick-title">
                    <span class="expense-icon"><?= IconHelper::svg('card') ?></span>
                    <h2 class="card-title">Dodaj płatność</h2>
                </div>
                <form class="payment-quick-form" method="post" enctype="multipart/form-data" data-payment-form>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="status" value="completed">
                    <label class="form-field">
                        <span class="form-label">Uczennica <strong>*</strong></span>
                        <select class="select" name="student_id" required data-payment-student>
                            <option value="">Wybierz uczennicę</option>
                            <?php foreach ($students as $student): ?>
                                <?php $isSelected = $selectedStudentId === (int) $student['id']; ?>
                                <option
                                    value="<?= FormatHelper::e((string) $student['id']) ?>"
                                    data-group-name="<?= FormatHelper::e((string) ($student['group_name'] ?? '-')) ?>"
                                    data-subscription-id="<?= FormatHelper::e((string) ($student['subscription_id'] ?? '')) ?>"
                                    data-subscription-name="<?= FormatHelper::e($student['subscription_name'] ?? '-') ?>"
                                    data-price="<?= FormatHelper::e((string) ($student['subscription_price'] ?? '')) ?>"
                                    <?= $isSelected ? 'selected' : '' ?>
                                >
                                    <?= FormatHelper::e(trim((string) $student['first_name'] . ' ' . (string) $student['last_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="payment-plan-field">
                        <legend class="form-label">Abonament <strong>*</strong></legend>
                        <div class="payment-plan-grid" data-payment-plans>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <label class="payment-plan-option">
                                    <input
                                        class="payment-plan-input"
                                        type="radio"
                                        name="subscription_id"
                                        value="<?= FormatHelper::e((string) $subscription['id']) ?>"
                                        data-payment-plan
                                        data-price="<?= FormatHelper::e((string) $subscription['price']) ?>"
                                        data-lessons="<?= FormatHelper::e((string) $subscription['lessons_count']) ?>"
                                        data-billing-period="<?= FormatHelper::e((string) ($subscription['billing_period'] ?? 'monthly')) ?>"
                                        required
                                    >
                                    <span class="payment-plan-card">
                                        <strong><?= FormatHelper::e($subscription['name']) ?></strong>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="payment-quick-preview" data-payment-preview>
                        <span><small>Grupa</small><strong data-payment-preview-group>-</strong></span>
                        <span><small>Abonament</small><strong data-payment-preview-subscription>-</strong></span>
                        <span><small>Kwota</small><strong data-payment-preview-amount>- zł</strong></span>
                    </div>
                    <input type="hidden" name="period_month" value="<?= FormatHelper::e($addPeriodMonth) ?>" data-payment-period-month>
                    <input type="hidden" name="amount" value="" data-payment-amount>
                    <label class="form-field">
                        <span class="form-label">Data płatności <strong>*</strong></span>
                        <input class="input" type="date" name="payment_date" value="<?= FormatHelper::e(date('Y-m-d')) ?>" required data-payment-date>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Metoda płatności <strong>*</strong></span>
                        <select class="select" name="payment_method_id" required>
                            <option value="">Wybierz metodę</option>
                            <?php foreach ($methods as $method): ?>
                                <option value="<?= FormatHelper::e((string) $method['id']) ?>"><?= FormatHelper::e($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Komentarz</span>
                        <input class="input" type="text" name="comment" placeholder="Dodaj komentarz (opcjonalnie)">
                    </label>
                    <button class="btn btn-primary btn-block" type="submit">Zapisz płatność</button>
                </form>
            </section>
        </aside>
    </section>

    <?php
    $renderPaymentEditModal = static function (array $values, array $methods): void {
        ?>
        <div class="modal-backdrop is-open">
            <section class="modal payment-modal">
                <div class="card-header">
                    <h2 class="card-title">Edytuj płatność</h2>
                    <a class="btn btn-ghost btn-icon" href="<?= FormatHelper::e(url('pages/payments.php')) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
                </div>
                <form class="payment-form-grid" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="payment_id" value="<?= FormatHelper::e((string) ($values['id'] ?? $values['payment_id'] ?? 0)) ?>">
                    <label class="form-field">
                        <span class="form-label">Uczennica</span>
                        <input class="input" type="text" value="<?= FormatHelper::e(trim((string) ($values['first_name'] ?? '') . ' ' . (string) ($values['last_name'] ?? ''))) ?>" disabled>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Abonament</span>
                        <input class="input" type="text" value="<?= FormatHelper::e((string) ($values['subscription_name'] ?? '-')) ?>" disabled>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Kwota</span>
                        <input class="input" type="number" step="0.01" min="0.01" name="amount" value="<?= FormatHelper::e((string) ($values['amount'] ?? '')) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data płatności</span>
                        <input class="input" type="date" name="payment_date" value="<?= FormatHelper::e((string) ($values['payment_date'] ?? '')) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Metoda płatności</span>
                        <select class="select" name="payment_method_id" required>
                            <?php foreach ($methods as $method): ?>
                                <option value="<?= FormatHelper::e((string) $method['id']) ?>" <?= (int) ($values['payment_method_id'] ?? 0) === (int) $method['id'] ? 'selected' : '' ?>><?= FormatHelper::e($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <?php foreach (['completed', 'pending', 'partial', 'refunded', 'cancelled'] as $status): ?>
                                <option value="<?= FormatHelper::e($status) ?>" <?= (string) ($values['status'] ?? 'completed') === $status ? 'selected' : '' ?>><?= FormatHelper::e(FormatHelper::paymentStatusLabel($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field form-field-wide">
                        <span class="form-label">Komentarz</span>
                        <textarea class="textarea" name="comment"><?= FormatHelper::e((string) ($values['comment'] ?? '')) ?></textarea>
                    </label>
                    <div class="modal-actions">
                        <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/payments.php')) ?>">Anuluj</a>
                        <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                    </div>
                </form>
            </section>
        </div>
        <?php
    };

    if ($showEditModal) {
        $renderPaymentEditModal($formPayment, $methods);
    }
});
