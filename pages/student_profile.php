<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Repositories\AttendanceRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SettingsRepository;
use App\Services\AttendanceService;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\StudentService;
use App\Validators\StudentValidator;

$studentId = max(0, (int) ($_GET['id'] ?? 0));
$student = null;
$payments = [];
$subscriptionHistory = [];
$attendance = [];
$databaseError = null;
$flash = $_SESSION['student_profile_flash'] ?? null;
unset($_SESSION['student_profile_flash']);

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));
    $attendanceService = new AttendanceService(new AttendanceRepository($pdo));
    $studentService = new StudentService(
        new StudentRepository($pdo),
        new StudentValidator(),
        new StorageService($settingsService),
        $settingsService->lessonSchedule()
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $errors = [];
        $message = '';
        $fragment = '';

        if ($action === 'adjust_remaining_lessons') {
            $errors = $studentService->adjustRemainingLessons(
                $studentId,
                (int) ($_POST['remaining_lessons'] ?? -1),
                (string) ($_POST['reason'] ?? '')
            );
            $message = 'Zaktualizowano liczbę pozostałych zajęć.';
        } elseif ($action === 'update_attendance') {
            $errors = $attendanceService->updatePastRecord(
                $studentId,
                (string) ($_POST['lesson_date'] ?? ''),
                (string) ($_POST['status'] ?? ''),
                (string) ($_POST['notes'] ?? '')
            );
            $message = 'Zaktualizowano obecność i przeliczono abonament.';
            $fragment = '#obecnosci';
        } else {
            $errors = ['Nieznana operacja.'];
        }

        $_SESSION['student_profile_flash'] = [
            'type' => $errors === [] ? 'success' : 'danger',
            'message' => $errors === [] ? $message : implode(' ', $errors),
        ];
        header('Location: ' . url('pages/student_profile.php?id=' . $studentId) . $fragment);
        exit;
    }

    $profile = $studentService->profile($studentId);
    $student = $profile['student'];
    $payments = $profile['payments'];
    $subscriptionHistory = $profile['subscription_history'];
    $attendance = $profile['attendance'];
} catch (Throwable $exception) {
    $databaseError = $exception->getMessage();
}

render_page('Szczegóły uczennicy', 'students', function () use ($student, $payments, $subscriptionHistory, $attendance, $databaseError, $flash): void {
    if (!$student) {
        ?>
        <nav class="breadcrumb" aria-label="Ścieżka">
            <a href="<?= FormatHelper::e(url('pages/students.php')) ?>">Uczennice</a>
            <span aria-hidden="true">/</span>
            <span>Szczegóły uczennicy</span>
        </nav>

        <section class="empty-state module-empty">
            <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('user') ?></div>
            <h1 class="empty-state-title">Nie znaleziono uczennicy</h1>
            <p class="empty-state-text">
                <?= $databaseError ? FormatHelper::e('Błąd bazy danych: ' . $databaseError) : 'Wybrany rekord nie istnieje albo został zarchiwizowany.' ?>
            </p>
            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/students.php')) ?>">Powrót do listy</a>
        </section>
        <?php
        return;
    }

    $fullName = trim($student['first_name'] . ' ' . $student['last_name']);
    $studentCode = '#UCZ' . str_pad((string) $student['id'], 3, '0', STR_PAD_LEFT);
    $remainingLessons = (int) ($student['remaining_lessons'] ?? 0);
    $paidLessons = (int) ($student['paid_lessons_count'] ?? ($student['lessons_count'] ?? 0));
    $usedLessons = (int) ($student['used_lessons'] ?? 0);
    $isCurrentSubscriptionPaid = (bool) ($student['current_subscription_paid'] ?? false);
    $paymentCount = count($payments);
    $totalPaid = array_reduce(
        $payments,
        static fn (float $sum, array $payment): float => $sum + ((string) $payment['status'] === 'completed' ? (float) $payment['amount'] : 0.0),
        0.0
    );
    $lastPayment = $payments[0] ?? null;
    $nextPaymentMonth = FormatHelper::monthName($student['next_payment_month'] ?? date('Y-m-01'));
    $nextPaymentDate = $student['next_payment_lesson_date'] ?? $student['next_payment_month'] ?? null;
    $paymentsUrl = url('pages/payments.php?student_id=' . $student['id'] . '&period=all');
    $documents = [
        ['Umowa o uczestnictwo.pdf', 'Dodano: ' . FormatHelper::date($student['enrollment_date']), 'PDF'],
        ['Zgoda opiekuna.pdf', 'Dodano: ' . FormatHelper::date($student['enrollment_date']), 'PDF'],
    ];
    ?>
    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="alert alert-<?= FormatHelper::e((string) ($flash['type'] ?? 'info')) ?>" role="status">
            <?= FormatHelper::e((string) $flash['message']) ?>
        </div>
    <?php endif; ?>

    <nav class="breadcrumb" aria-label="Ścieżka">
        <a href="<?= FormatHelper::e(url('pages/students.php')) ?>">Uczennice</a>
        <span aria-hidden="true">/</span>
        <span>Szczegóły uczennicy</span>
    </nav>

    <section class="module-hero profile-ref-hero">
        <div>
            <h1>
                <?= FormatHelper::e($fullName) ?>
                <span class="badge badge-dot <?= FormatHelper::e(FormatHelper::statusBadge($student['status'])) ?>"><?= FormatHelper::e(FormatHelper::statusLabel($student['status'])) ?></span>
            </h1>
            <p>
                <span>ID: <?= FormatHelper::e($studentCode) ?></span>
                <span>Dołączyła: <?= FormatHelper::e(FormatHelper::date($student['enrollment_date'])) ?></span>
            </p>
        </div>
        <div class="module-actions">
            <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/students.php')) ?>">
                <?= IconHelper::svg('arrow-left') ?>
                Powrót do listy
            </a>
            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/students.php?modal=edit&id=' . $student['id'])) ?>"><?= IconHelper::svg('edit') ?> Edytuj</a>
            <button class="btn btn-secondary btn-icon" type="button" aria-label="Więcej akcji"><?= IconHelper::svg('more') ?></button>
        </div>
    </section>

    <section class="student-profile-grid">
        <article class="card student-card profile-area-student">
            <?php if (!empty($student['photo_path'])): ?>
                <img class="student-portrait avatar avatar-photo" src="<?= FormatHelper::e(url($student['photo_path'])) ?>" alt="">
            <?php else: ?>
                <span class="student-portrait avatar avatar-photo" aria-hidden="true">
                    <?= FormatHelper::e(FormatHelper::initials($student['first_name'], $student['last_name'])) ?>
                </span>
            <?php endif; ?>
            <div class="student-contact-stack">
                <div>
                    <span class="contact-icon" aria-hidden="true"><?= IconHelper::svg('phone') ?></span>
                    <strong><?= FormatHelper::e(FormatHelper::phone($student['phone'])) ?></strong>
                </div>
                <div>
                    <span class="contact-icon contact-icon-telegram" aria-hidden="true"><?= IconHelper::svg('telegram') ?></span>
                    <strong><?= $student['telegram_username'] ? '@' . FormatHelper::e($student['telegram_username']) : '-' ?></strong>
                </div>
                <div>
                    <span class="contact-icon" aria-hidden="true"><?= IconHelper::svg('age') ?></span>
                    <strong><?= FormatHelper::e($student['age'] !== null ? (string) $student['age'] . ' lat' : 'Brak wieku') ?> (<?= FormatHelper::e(FormatHelper::date($student['birth_date'])) ?>)</strong>
                </div>
                <div>
                    <span class="contact-icon" aria-hidden="true"><?= IconHelper::svg('phone') ?></span>
                    <strong><?= $student['guardian_phone'] ? FormatHelper::e(FormatHelper::phone($student['guardian_phone'])) : 'Brak kontaktu opiekuna' ?></strong>
                </div>
                <div>
                    <span class="contact-icon contact-icon-telegram" aria-hidden="true"><?= IconHelper::svg('telegram') ?></span>
                    <strong><?= $student['guardian_telegram_username'] ? '@' . FormatHelper::e($student['guardian_telegram_username']) : 'Brak Telegram opiekuna' ?></strong>
                </div>
                <div class="student-note-shortcut">
                    <span class="contact-icon" aria-hidden="true"><?= IconHelper::svg('notes') ?></span>
                    <strong><?= FormatHelper::e($student['notes'] ?: 'Brak notatek') ?></strong>
                </div>
            </div>
        </article>

        <section class="card learning-card profile-area-learning">
            <h2 class="card-title">Informacje o nauce</h2>
            <div class="learning-grid">
                <div><span>Grupa</span><strong><span class="badge badge-sand"><?= FormatHelper::e($student['group_name'] ?: 'Brak grupy') ?></span></strong></div>
                <div><span>Abonament</span><strong><?= FormatHelper::e($student['subscription_name'] ?: 'Brak abonamentu') ?></strong><em><?= FormatHelper::e(FormatHelper::money($student['subscription_price'], $student['subscription_currency'] ?? 'PLN')) ?></em></div>
                <div><span>Dzień zajęć</span><strong>Do ustalenia</strong><em>-</em></div>
                <div><span>Data rozpoczęcia</span><strong><?= FormatHelper::e(FormatHelper::date($student['subscription_start_date'] ?: $student['enrollment_date'])) ?></strong></div>
                <div><span>Status</span><strong><span class="badge badge-dot <?= FormatHelper::e(FormatHelper::statusBadge($student['status'])) ?>"><?= FormatHelper::e(FormatHelper::statusLabel($student['status'])) ?></span></strong></div>
            </div>
            <h3 class="learning-stats-title">Statystyki</h3>
            <div class="learning-stats">
                <div><span>Liczba płatności</span><strong><?= FormatHelper::e((string) $paymentCount) ?></strong></div>
                <div><span>Łączna kwota wpłat</span><strong><?= FormatHelper::e(FormatHelper::money($totalPaid)) ?></strong></div>
                <div><span>Ostatnia płatność</span><strong><?= $lastPayment ? FormatHelper::e(FormatHelper::date($lastPayment['payment_date']) . ' - ' . FormatHelper::money($lastPayment['amount'], $lastPayment['currency'])) : '-' ?></strong></div>
                <div class="profile-adjustable-stat">
                    <span>Pozostało zajęć</span>
                    <strong><?= FormatHelper::e((string) $remainingLessons) ?> / <?= FormatHelper::e((string) $paidLessons) ?></strong>
                    <button
                        class="btn btn-ghost btn-icon btn-sm profile-stat-edit"
                        type="button"
                        aria-label="Zmień liczbę pozostałych zajęć"
                        data-profile-modal-open="remaining-lessons-modal"
                    ><?= IconHelper::svg('edit') ?></button>
                </div>
            </div>
        </section>

        <section class="card next-payment-card profile-area-next">
            <div class="payment-card-icon" aria-label="Pozostałe zajęcia">
                <?= FormatHelper::e((string) $remainingLessons) ?>
            </div>
            <h2 class="card-title">Następna płatność</h2>
            <span class="next-payment-date"><?= $nextPaymentDate ? FormatHelper::e(FormatHelper::date($nextPaymentDate)) : '-' ?></span>
            <strong><?= FormatHelper::e($nextPaymentMonth) ?></strong>
            <b><?= FormatHelper::e(FormatHelper::money($student['subscription_price'], $student['subscription_currency'] ?? 'PLN')) ?></b>
            <div class="limit-note">
                <?= $isCurrentSubscriptionPaid
                    ? FormatHelper::e('Wykorzystano: ' . $usedLessons . ' z ' . $paidLessons)
                    : 'Brak opłaty za bieżący miesiąc' ?>
            </div>
        </section>

        <section class="card module-panel profile-tabs-panel profile-area-tabs" data-tabs>
            <div class="tabs profile-tabs" role="tablist" aria-label="Sekcje profilu">
                <button class="tab is-active" id="tab-platnosci" type="button" role="tab" aria-selected="true" aria-controls="panel-platnosci" data-tab-target="platnosci">Płatności</button>
                <button class="tab" id="tab-obecnosci" type="button" role="tab" aria-selected="false" aria-controls="panel-obecnosci" data-tab-target="obecnosci">Obecności</button>
                <button class="tab" id="tab-notatki" type="button" role="tab" aria-selected="false" aria-controls="panel-notatki" data-tab-target="notatki">Notatki</button>
                <button class="tab" id="tab-dokumenty" type="button" role="tab" aria-selected="false" aria-controls="panel-dokumenty" data-tab-target="dokumenty">Dokumenty</button>
                <a
                    class="btn btn-secondary btn-sm profile-tab-action"
                    href="<?= FormatHelper::e(url('pages/payments.php?modal=add&student_id=' . $student['id'])) ?>"
                    data-tab-action
                    data-action-platnosci-href="<?= FormatHelper::e(url('pages/payments.php?modal=add&student_id=' . $student['id'])) ?>"
                    data-action-platnosci-label="Dodaj płatność"
                    data-action-obecnosci-href="#obecnosci"
                    data-action-obecnosci-label="Dodaj obecność"
                    data-action-notatki-href="#notatki"
                    data-action-notatki-label="Dodaj notatkę"
                    data-action-dokumenty-href="#dokumenty"
                    data-action-dokumenty-label="Dodaj dokument"
                >
                    <?= IconHelper::svg('add') ?> <span>Dodaj płatność</span>
                </a>
            </div>

            <div class="tab-panel is-active" id="panel-platnosci" role="tabpanel" aria-labelledby="tab-platnosci" data-tab-panel="platnosci">
                <div class="table-wrap profile-table-wrap">
                    <table class="table profile-payments-table">
                        <thead>
                            <tr>
                                <th>Data płatności</th>
                                <th>Miesiąc</th>
                                <th>Abonament</th>
                                <th>Kwota</th>
                                <th>Metoda płatności</th>
                                <th>Status</th>
                                <th>Komentarz</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payments === []): ?>
                                <tr>
                                    <td colspan="8">Brak płatności dla tej uczennicy.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= FormatHelper::e(FormatHelper::date($payment['payment_date'])) ?></td>
                                    <td><?= FormatHelper::e(FormatHelper::monthName($payment['period_start'])) ?></td>
                                    <td><?= FormatHelper::e($payment['subscription_name'] ?: '-') ?></td>
                                    <td><strong><?= FormatHelper::e(FormatHelper::money($payment['amount'], $payment['currency'])) ?></strong></td>
                                    <td><?= FormatHelper::e($payment['payment_method'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge badge-dot <?= FormatHelper::e(FormatHelper::paymentStatusBadge($payment['status'])) ?>">
                                            <?= FormatHelper::e(FormatHelper::paymentStatusLabel($payment['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= FormatHelper::e($payment['comment'] ?: '-') ?></td>
                                    <td><button class="btn btn-ghost btn-icon btn-sm" type="button" aria-label="Więcej akcji"><?= IconHelper::svg('more') ?></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a class="profile-show-all text-link" href="<?= FormatHelper::e($paymentsUrl) ?>">Zobacz wszystkie płatności (<?= FormatHelper::e((string) $paymentCount) ?>)</a>
            </div>

            <div class="tab-panel" id="panel-obecnosci" role="tabpanel" aria-labelledby="tab-obecnosci" data-tab-panel="obecnosci" hidden>
                <?php if ($attendance === []): ?>
                    <section class="empty-state module-empty">
                        <h2 class="empty-state-title">Obecności</h2>
                        <p class="empty-state-text">Brak zapisanych obecności dla tej uczennicy.</p>
                    </section>
                <?php else: ?>
                    <div class="table-wrap profile-table-wrap">
                        <table class="table profile-attendance-table">
                            <thead>
                                <tr>
                                    <th>Data zajęć</th>
                                    <th>Status</th>
                                    <th>Rozliczenie</th>
                                    <th>Notatka</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <td><?= FormatHelper::e(FormatHelper::date($record['lesson_date'])) ?></td>
                                        <td>
                                            <span class="badge badge-dot <?= FormatHelper::e(AttendanceService::statusBadge((string) $record['status'])) ?>">
                                                <?= FormatHelper::e(AttendanceService::statusLabel((string) $record['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ((int) ($record['counts_as_used_lesson'] ?? 1) === 1): ?>
                                                <span class="badge badge-warning">Spisane</span>
                                            <?php elseif ((string) $record['status'] === 'absent_notice'): ?>
                                                <span class="badge badge-success">Zachowane <?= FormatHelper::e((string) ($record['notice_number'] ?? '')) ?>/2</span>
                                            <?php else: ?>
                                                <span class="badge">Nie spisuje</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= FormatHelper::e((string) ($record['notes'] ?: '-')) ?></td>
                                        <td>
                                            <button
                                                class="btn btn-secondary btn-icon btn-sm"
                                                type="button"
                                                aria-label="Edytuj obecność z dnia <?= FormatHelper::e(FormatHelper::date($record['lesson_date'])) ?>"
                                                data-profile-attendance-edit
                                                data-lesson-date="<?= FormatHelper::e((string) $record['lesson_date']) ?>"
                                                data-attendance-status="<?= FormatHelper::e((string) $record['status']) ?>"
                                                data-attendance-notes="<?= FormatHelper::e((string) ($record['notes'] ?? '')) ?>"
                                            ><?= IconHelper::svg('edit') ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-panel" id="panel-notatki" role="tabpanel" aria-labelledby="tab-notatki" data-tab-panel="notatki" hidden>
                <section class="empty-state module-empty" id="notatki">
                    <h2 class="empty-state-title">Notatki</h2>
                    <p class="empty-state-text">Tutaj pojawią się notatki wewnętrzne zespołu.</p>
                </section>
            </div>

            <div class="tab-panel" id="panel-dokumenty" role="tabpanel" aria-labelledby="tab-dokumenty" data-tab-panel="dokumenty" hidden>
                <section class="empty-state module-empty">
                    <h2 class="empty-state-title">Dokumenty</h2>
                    <p class="empty-state-text">Dokumenty uczennicy będą dostępne po wdrożeniu uploadu.</p>
                </section>
            </div>
        </section>

        <section class="card subscription-timeline-card profile-area-history">
            <h2 class="card-title">Historia abonamentów</h2>
            <div class="subscription-timeline panel-scroll">
                <?php if ($subscriptionHistory === []): ?>
                    <p class="profile-note">Brak historii abonamentów.</p>
                <?php endif; ?>
                <?php foreach ($subscriptionHistory as $history): ?>
                    <div>
                        <span></span>
                        <p><?= $history['end_date'] ? 'Do ' . FormatHelper::e(FormatHelper::date($history['end_date'])) : 'Od ' . FormatHelper::e(FormatHelper::date($history['start_date'])) ?></p>
                        <strong><?= FormatHelper::e($history['name'] ?: 'Brak') ?> <em><?= FormatHelper::e(FormatHelper::money($history['price'], $history['currency'] ?? 'PLN')) ?></em></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card documents-card profile-area-documents">
            <div class="card-header">
                <h2 class="card-title">Załączone dokumenty</h2>
                <button class="btn btn-secondary btn-sm" type="button">Dodaj dokument</button>
            </div>
            <div class="document-list panel-scroll">
                <?php foreach ($documents as [$name, $meta, $type]): ?>
                    <div class="document-row">
                        <span class="document-icon"><?= IconHelper::svg('document') ?></span>
                        <div>
                            <strong><?= FormatHelper::e($name) ?></strong>
                            <span><?= FormatHelper::e($meta) ?></span>
                        </div>
                        <button class="btn btn-secondary btn-sm document-download" type="button" aria-label="Pobierz dokument"><?= IconHelper::svg('download') ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="profile-show-all text-link profile-open-tab" href="#dokumenty" data-open-tab="dokumenty">Zobacz wszystkie dokumenty</a>
        </section>
    </section>

    <div class="modal-backdrop profile-admin-modal" id="remaining-lessons-modal" hidden>
        <form class="modal profile-correction-modal" method="post">
            <input type="hidden" name="action" value="adjust_remaining_lessons">
            <div class="modal-header">
                <div>
                    <h2>Korekta pozostałych zajęć</h2>
                    <p><?= FormatHelper::e($fullName) ?> · abonament <?= FormatHelper::e((string) $paidLessons) ?> zajęć</p>
                </div>
                <button class="btn btn-secondary btn-icon" type="button" aria-label="Zamknij" data-profile-modal-close><?= IconHelper::svg('close') ?></button>
            </div>
            <div class="form-group">
                <label for="remaining-lessons-input">Pozostało zajęć</label>
                <input
                    class="input"
                    id="remaining-lessons-input"
                    name="remaining_lessons"
                    type="number"
                    min="0"
                    max="<?= FormatHelper::e((string) $paidLessons) ?>"
                    value="<?= FormatHelper::e((string) $remainingLessons) ?>"
                    required
                >
                <small>Zakres: 0-<?= FormatHelper::e((string) $paidLessons) ?>. Kolejne rozliczone obecności nadal zmniejszą ten wynik.</small>
            </div>
            <div class="form-group">
                <label for="remaining-lessons-reason">Powód korekty</label>
                <textarea class="input" id="remaining-lessons-reason" name="reason" rows="3" minlength="3" required placeholder="Np. korekta po uzgodnieniu z administratorem"></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" type="button" data-profile-modal-close>Anuluj</button>
                <button class="btn btn-primary" type="submit"><?= IconHelper::svg('check') ?> Zapisz korektę</button>
            </div>
        </form>
    </div>

    <div class="modal-backdrop profile-admin-modal" id="attendance-edit-modal" hidden>
        <form class="modal profile-correction-modal" method="post">
            <input type="hidden" name="action" value="update_attendance">
            <input type="hidden" name="lesson_date" value="" data-profile-attendance-date-input>
            <div class="modal-header">
                <div>
                    <h2>Edytuj obecność</h2>
                    <p><?= FormatHelper::e($fullName) ?> · <span data-profile-attendance-date-label></span></p>
                </div>
                <button class="btn btn-secondary btn-icon" type="button" aria-label="Zamknij" data-profile-modal-close><?= IconHelper::svg('close') ?></button>
            </div>
            <div class="form-group">
                <label for="attendance-status-input">Status</label>
                <select class="select" id="attendance-status-input" name="status" required data-profile-attendance-status-input>
                    <option value="present">Obecna</option>
                    <option value="absent_notice">Nieobecna, zgłoszono wcześniej</option>
                    <option value="absent_no_notice">Nieobecna bez zgłoszenia</option>
                    <option value="school_cancelled">Zajęcia odwołane przez szkołę</option>
                </select>
            </div>
            <div class="form-group">
                <label for="attendance-notes-input">Notatka</label>
                <textarea class="input" id="attendance-notes-input" name="notes" rows="3" placeholder="Opcjonalnie" data-profile-attendance-notes-input></textarea>
            </div>
            <div class="alert alert-info profile-correction-note">
                Zmiana przeliczy kolejność zgłoszonych nieobecności w całym abonamencie. Pierwsze dwa zgłoszenia zachowują zajęcie, trzecie i kolejne je wykorzystują.
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" type="button" data-profile-modal-close>Anuluj</button>
                <button class="btn btn-primary" type="submit"><?= IconHelper::svg('check') ?> Zapisz zmianę</button>
            </div>
        </form>
    </div>
    <?php
});
