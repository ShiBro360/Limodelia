<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\GroupRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\StudentService;
use App\Validators\StudentValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$studentService = null;
$groupRepository = null;
$subscriptionRepository = null;

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));
    $studentService = new StudentService(
        new StudentRepository($pdo),
        new StudentValidator(),
        new StorageService($settingsService),
        $settingsService->lessonSchedule()
    );
    $groupRepository = new GroupRepository($pdo);
    $subscriptionRepository = new SubscriptionRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $errors = $studentService->save($_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/students.php', ['saved' => 'created']);
            }
        } elseif ($action === 'update') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $errors = $studentId > 0 ? $studentService->save($_POST, $studentId) : ['Brak ID uczennicy.'];
            if ($errors === []) {
                ResponseHelper::redirect('pages/students.php', ['saved' => 'updated']);
            }
        } elseif ($action === 'delete') {
            $errors = $studentService->softDelete((int) ($_POST['student_id'] ?? 0));
            if ($errors === []) {
                ResponseHelper::redirect('pages/students.php', ['saved' => 'deleted']);
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd połączenia z bazą danych: ' . $exception->getMessage();
}
render_page('Uczennice', 'students', function () use ($studentService, $groupRepository, $subscriptionRepository, $errors, $notice): void {
    $groups = [];
    $subscriptions = [];
    $students = [];
    $total = 0;
    $editStudent = null;
    $filters = [
        'search' => '',
        'group' => 0,
        'subscription' => 0,
        'status' => '',
        'sort' => 'newest',
        'page' => 1,
        'per_page' => 10,
    ];

    if ($studentService instanceof StudentService && $groupRepository instanceof GroupRepository && $subscriptionRepository instanceof SubscriptionRepository) {
        try {
            $groups = $groupRepository->active();
            $subscriptions = $subscriptionRepository->active();
            $filters = $studentService->filters($_GET);
            $pagination = $studentService->paginated($filters);
            $students = $pagination['students'];
            $total = $pagination['total'];
            $pageCount = $pagination['page_count'];
            $filters = $pagination['filters'];

            if (($_GET['modal'] ?? '') === 'edit' && isset($_GET['id'])) {
                $editStudent = $studentService->findForEdit((int) $_GET['id']);
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się pobrać danych uczennic: ' . $exception->getMessage();
        }
    }

    $pageCount ??= 1;
    $modal = (string) ($_GET['modal'] ?? '');
    $showAddModal = $modal === 'add' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create' && $errors !== []);
    $showEditModal = ($modal === 'edit' && $editStudent) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $errors !== []);
    $formStudent = $showEditModal ? ($editStudent ?: $_POST) : $_POST;    ?>
    <section class="module-hero">
        <div>
            <h1>Uczennice</h1>
            <p>Zarządzaj wszystkimi uczennicami szkoły.</p>
        </div>
        <div class="module-actions">
            <button class="btn btn-secondary" type="button" disabled aria-disabled="true">
                Eksportuj CSV
            </button>
            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/students.php?modal=add')) ?>">
                <?= IconHelper::svg('add') ?>
                Dodaj uczennicę
            </a>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Zmiany zostały zapisane.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="card module-panel">
        <form class="filter-bar" action="<?= FormatHelper::e(url('pages/students.php')) ?>" method="get" aria-label="Filtry uczennic" data-live-filter data-filter-target="#students-results">
            <label class="form-field filter-search">
                <span class="form-label">Szukaj</span>
                <span class="search-field">
                    <input type="search" name="search" value="<?= FormatHelper::e($filters['search']) ?>" placeholder="Szukaj uczennicy..." aria-label="Szukaj uczennicy">
                </span>
            </label>
            <label class="form-field">
                <span class="form-label">Grupa</span>
                <select class="select" name="group">
                    <option value="">Wszystkie</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= FormatHelper::e((string) $group['id']) ?>" <?= $filters['group'] === (int) $group['id'] ? 'selected' : '' ?>><?= FormatHelper::e($group['name']) ?></option>
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
            <label class="form-field">
                <span class="form-label">Status</span>
                <select class="select" name="status">
                    <option value="">Wszystkie</option>
                    <?php foreach (['active', 'paused', 'inactive', 'archived'] as $status): ?>
                        <option value="<?= FormatHelper::e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= FormatHelper::e(FormatHelper::statusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field">
                <span class="form-label">Sortowanie</span>
                <select class="select" name="sort">
                    <?php foreach (['newest' => 'Najnowsze', 'name' => 'Imię i nazwisko', 'end_date' => 'Data końca', 'status' => 'Status'] as $value => $label): ?>
                        <option value="<?= FormatHelper::e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions">
                <button class="btn btn-secondary" type="submit">
                    Filtruj
                </button>
                <a class="btn btn-ghost" href="<?= FormatHelper::e(url('pages/students.php')) ?>">Reset filtrów</a>
            </div>
        </form>

        <div id="students-results" data-live-results>
        <?php if ($students === []): ?>
            <section class="empty-state module-empty">
                <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('users') ?></div>
                <h2 class="empty-state-title">Brak uczennic</h2>
                <p class="empty-state-text">Dodaj pierwszą uczennicę, aby rozpocząć pracę z modułem.</p>
                <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/students.php?modal=add')) ?>">Dodaj pierwszą uczennicę</a>
            </section>
        <?php else: ?>
            <div class="table-wrap students-table-wrap">
                <table class="table students-table">
                    <thead>
                        <tr>
                            <th>Uczennica</th>
                            <th>Kontakt</th>
                            <th>Wiek</th>
                            <th>Grupa</th>
                            <th>Abonament</th>
                            <th>Status</th>
                            <th>Data rozpoczęcia</th>
                            <th>Ostatnia płatność</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $profileUrl = url('pages/student_profile.php?id=' . $student['id']);
                            $fullName = trim($student['first_name'] . ' ' . $student['last_name']);
                            ?>
                            <tr class="clickable-row" data-row-link="<?= FormatHelper::e($profileUrl) ?>" tabindex="0">
                                <td>
                                    <div class="student-person">
                                        <?php if (!empty($student['photo_path'])): ?>
                                            <img class="student-photo avatar avatar-photo" src="<?= FormatHelper::e(url($student['photo_path'])) ?>" alt="">
                                        <?php else: ?>
                                            <span class="student-photo avatar avatar-photo" aria-hidden="true">
                                                <?= FormatHelper::e(FormatHelper::initials($student['first_name'], $student['last_name'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="student-name">
                                            <strong><?= FormatHelper::e($fullName) ?></strong>
                                            <span><?= FormatHelper::e($student['age'] !== null ? (string) $student['age'] . ' lat' : 'Brak daty urodzenia') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-cell">
                                        <span aria-label="Telefon">
                                            <span class="contact-icon" aria-hidden="true"><?= IconHelper::svg('phone') ?></span>
                                            <?= FormatHelper::e(FormatHelper::phone($student['phone'])) ?>
                                        </span>
                                        <span aria-label="Telegram">
                                            <span class="contact-icon contact-icon-telegram" aria-hidden="true"><?= IconHelper::svg('telegram') ?></span>
                                            <?= $student['telegram_username'] ? '@' . FormatHelper::e($student['telegram_username']) : '-' ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= $student['age'] !== null ? FormatHelper::e((string) $student['age']) : '-' ?></td>
                                <td>
                                    <span class="badge <?= FormatHelper::e(FormatHelper::groupBadge($student['group_name'])) ?>">
                                        <?= FormatHelper::e($student['group_name'] ?: 'Brak grupy') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="subscription-cell">
                                        <strong><?= FormatHelper::e($student['subscription_name'] ?: 'Brak') ?></strong>
                                        <span><?= $student['subscription_price'] !== null ? FormatHelper::e(FormatHelper::money((float) $student['subscription_price'])) : '-' ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-dot <?= FormatHelper::e(FormatHelper::statusBadge($student['status'])) ?>">
                                        <?= FormatHelper::e(FormatHelper::statusLabel($student['status'])) ?>
                                    </span>
                                </td>
                                <td><?= !empty($student['enrollment_date']) ? FormatHelper::e(FormatHelper::date((string) $student['enrollment_date'])) : '-' ?></td>
                                <td>
                                    <div class="last-payment-cell">
                                        <strong><?= !empty($student['last_payment_date']) ? FormatHelper::e(FormatHelper::date((string) $student['last_payment_date'])) : '-' ?></strong>
                                        <span><?= $student['last_payment_amount'] !== null ? FormatHelper::e(FormatHelper::money((float) $student['last_payment_amount'], (string) ($student['last_payment_currency'] ?: 'PLN'))) : '-' ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="row-actions" aria-label="Akcje uczennicy">
                                        <a class="btn btn-secondary btn-icon btn-sm" href="<?= FormatHelper::e($profileUrl) ?>" aria-label="Zobacz profil">
                                            <?= IconHelper::svg('view') ?>
                                        </a>
                                        <a class="btn btn-secondary btn-icon btn-sm" href="<?= FormatHelper::e(url('pages/students.php?modal=edit&id=' . $student['id'])) ?>" aria-label="Edytuj uczennicę">
                                            <?= IconHelper::svg('edit') ?>
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-secondary btn-icon btn-sm" type="button" data-dropdown-toggle aria-label="Więcej akcji">
                                                <?= IconHelper::svg('more') ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="<?= FormatHelper::e($profileUrl) ?>">Profil uczennicy</a>
                                                <a class="dropdown-item" href="<?= FormatHelper::e(url('pages/students.php?modal=edit&id=' . $student['id'])) ?>">Edytuj</a>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="student_id" value="<?= FormatHelper::e((string) $student['id']) ?>">
                                                    <button class="dropdown-item" type="submit">Archiwizuj</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <footer class="table-footer">
                <form class="rows-control" method="get">
                    <span>Wiersze</span>
                    <input type="hidden" name="search" value="<?= FormatHelper::e($filters['search']) ?>">
                    <input type="hidden" name="group" value="<?= FormatHelper::e((string) $filters['group']) ?>">
                    <input type="hidden" name="subscription" value="<?= FormatHelper::e((string) $filters['subscription']) ?>">
                    <input type="hidden" name="status" value="<?= FormatHelper::e($filters['status']) ?>">
                    <input type="hidden" name="sort" value="<?= FormatHelper::e($filters['sort']) ?>">
                    <select class="select select-compact" name="per_page" aria-label="Liczba wierszy" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50] as $perPage): ?>
                            <option value="<?= $perPage ?>" <?= $filters['per_page'] === $perPage ? 'selected' : '' ?>><?= $perPage ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <p>Wyświetlanie <?= FormatHelper::e((string) (count($students) === 0 ? 0 : (($filters['page'] - 1) * $filters['per_page'] + 1))) ?>-<?= FormatHelper::e((string) min($total, ($filters['page'] - 1) * $filters['per_page'] + count($students))) ?> z <?= FormatHelper::e((string) $total) ?> uczennic</p>
                <nav class="pagination" aria-label="Paginacja uczennic">
                    <?php
                    $baseParams = array_filter([
                        'search' => $filters['search'],
                        'group' => $filters['group'] ?: null,
                        'subscription' => $filters['subscription'] ?: null,
                        'status' => $filters['status'] ?: null,
                        'sort' => $filters['sort'],
                        'per_page' => $filters['per_page'],
                    ], static fn ($value) => $value !== null && $value !== '');
                    ?>
                    <a class="page-link" href="<?= FormatHelper::e(url('pages/students.php?' . http_build_query($baseParams + ['page' => max(1, $filters['page'] - 1)]))) ?>" aria-label="Poprzednia strona"><?= IconHelper::svg('chevron-left') ?></a>
                    <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                        <a class="page-link <?= $page === $filters['page'] ? 'is-active' : '' ?>" href="<?= FormatHelper::e(url('pages/students.php?' . http_build_query($baseParams + ['page' => $page]))) ?>"><?= $page ?></a>
                    <?php endfor; ?>
                    <a class="page-link" href="<?= FormatHelper::e(url('pages/students.php?' . http_build_query($baseParams + ['page' => min($pageCount, $filters['page'] + 1)]))) ?>" aria-label="Następna strona"><?= IconHelper::svg('chevron-right') ?></a>
                </nav>
            </footer>
        <?php endif; ?>
        </div>
    </section>

    <?php
    $renderStudentModal = static function (string $mode, array $values, array $groups, array $subscriptions): void {
        $isEdit = $mode === 'edit';
        $action = $isEdit ? 'update' : 'create';
        ?>
        <div class="modal-backdrop is-open">
            <section class="modal student-modal">
                <div class="card-header">
                    <h2 class="card-title"><?= $isEdit ? 'Edytuj uczennicę' : 'Dodaj uczennicę' ?></h2>
                    <a class="btn btn-ghost btn-icon" href="<?= FormatHelper::e(url('pages/students.php')) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
                </div>
                <form class="student-form-grid" method="post" data-photo-editor-form>
                    <input type="hidden" name="action" value="<?= FormatHelper::e($action) ?>">
                    <input type="hidden" name="photo_data" value="">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="student_id" value="<?= FormatHelper::e((string) ($values['id'] ?? $values['student_id'] ?? '')) ?>">
                    <?php endif; ?>
                    <div class="photo-editor student-form-wide" data-photo-editor>
                        <div class="photo-editor-preview">
                            <?php if (!empty($values['photo_path'])): ?>
                                <img src="<?= FormatHelper::e(url($values['photo_path'])) ?>" alt="" data-photo-editor-image>
                            <?php else: ?>
                                <span data-photo-editor-placeholder><?= FormatHelper::e(FormatHelper::initials((string) ($values['first_name'] ?? ''), (string) ($values['last_name'] ?? ''))) ?></span>
                                <img alt="" data-photo-editor-image hidden>
                            <?php endif; ?>
                        </div>
                        <div class="photo-editor-controls">
                            <label class="btn btn-secondary btn-sm">
                                Wybierz zdjęcie
                                <input class="sr-only" type="file" accept="image/png,image/jpeg,image/webp" data-photo-editor-input data-reset-on-click>
                            </label>
                            <label class="photo-zoom-control">
                                <span>Zoom</span>
                                <input type="range" min="1" max="2.4" step="0.05" value="1" data-photo-editor-zoom>
                            </label>
                        </div>
                    </div>
                    <label class="form-field">
                        <span class="form-label">Imię</span>
                        <input class="input" name="first_name" value="<?= FormatHelper::e($values['first_name'] ?? '') ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Nazwisko</span>
                        <input class="input" name="last_name" value="<?= FormatHelper::e($values['last_name'] ?? '') ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Telefon</span>
                        <input class="input" type="tel" name="phone" value="<?= FormatHelper::e(FormatHelper::normalizePhone($values['phone'] ?? '') ?? '') ?>" placeholder="+48 600 123 456" maxlength="16" inputmode="numeric" data-phone-input required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Nick Telegram</span>
                        <input class="input" name="telegram_username" value="<?= FormatHelper::e($values['telegram_username'] ?? '') ?>" placeholder="bez @">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data urodzenia</span>
                        <input class="input" type="date" name="birth_date" value="<?= FormatHelper::e($values['birth_date'] ?? '') ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data dołączenia</span>
                        <input class="input" type="date" name="enrollment_date" value="<?= FormatHelper::e($values['enrollment_date'] ?? date('Y-m-d')) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Grupa</span>
                        <select class="select" name="student_group_id" required>
                            <option value="">Wybierz</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= FormatHelper::e((string) $group['id']) ?>" <?= (int) ($values['student_group_id'] ?? 0) === (int) $group['id'] ? 'selected' : '' ?>><?= FormatHelper::e($group['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Abonament</span>
                        <select class="select" name="subscription_id" required>
                            <option value="">Wybierz</option>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <option value="<?= FormatHelper::e((string) $subscription['id']) ?>" <?= (int) ($values['subscription_id'] ?? 0) === (int) $subscription['id'] ? 'selected' : '' ?>><?= FormatHelper::e($subscription['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <?php foreach (['active', 'paused', 'inactive', 'archived'] as $status): ?>
                                <option value="<?= FormatHelper::e($status) ?>" <?= ($values['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= FormatHelper::e(FormatHelper::statusLabel($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Kontakt opiekuna</span>
                        <input class="input" type="tel" name="guardian_phone" value="<?= FormatHelper::e(FormatHelper::normalizePhone($values['guardian_phone'] ?? '') ?? '') ?>" placeholder="+48 600 123 456" maxlength="16" inputmode="numeric" data-phone-input>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Telegram opiekuna</span>
                        <input class="input" name="guardian_telegram_username" value="<?= FormatHelper::e($values['guardian_telegram_username'] ?? '') ?>" placeholder="bez @">
                    </label>
                    <label class="form-field student-form-wide">
                        <span class="form-label">Notatki</span>
                        <textarea class="textarea" name="notes"><?= FormatHelper::e($values['notes'] ?? '') ?></textarea>
                    </label>
                    <div class="student-form-actions">
                        <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/students.php')) ?>">Anuluj</a>
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Zapisz zmiany' : 'Dodaj uczennicę' ?></button>
                    </div>
                </form>
            </section>
        </div>
        <?php
    };

    if ($showAddModal) {
        $renderStudentModal('add', $_POST, $groups, $subscriptions);
    }

    if ($showEditModal) {
        $renderStudentModal('edit', $formStudent, $groups, $subscriptions);
    }
});
