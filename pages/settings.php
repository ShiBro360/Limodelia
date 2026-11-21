<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\SettingsRepository;
use App\Services\CloudinaryService;
use App\Services\SettingsService;
use App\Services\TelegramService;

$errors = [];
$notice = $_GET['saved'] ?? null;
$testResult = $_GET['test'] ?? null;
$testMessage = $_GET['message'] ?? null;
$settingsService = null;

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $tab = (string) ($_POST['tab'] ?? 'school');

        $errors = match ($action) {
            'save_school' => $settingsService->saveSchool($_POST, $_FILES),
            'add_subscription' => $settingsService->addSubscription($_POST),
            'save_subscription' => $settingsService->saveSubscription($_POST),
            'save_subscription_order' => $settingsService->saveSubscriptionOrder($_POST),
            'add_group' => $settingsService->saveGroup($_POST, true),
            'save_group' => $settingsService->saveGroup($_POST),
            'save_salary_rule' => $settingsService->saveSalaryRule($_POST),
            'save_theme' => $settingsService->saveTheme($_POST),
            'save_payment_settings' => $settingsService->savePaymentSettings($_POST),
            'save_lesson_schedule' => $settingsService->saveLessonSchedule($_POST),
            'save_cloudinary', 'test_cloudinary' => $settingsService->saveCloudinary($_POST),
            'save_telegram', 'test_telegram' => $settingsService->saveTelegram($_POST),
            default => [],
        };

        if ($errors === [] && $action === 'test_cloudinary') {
            try {
                (new CloudinaryService($settingsService))->testConnection();
                ResponseHelper::redirect('pages/settings.php', ['tab' => 'external', 'saved' => 'cloudinary', 'test' => 'cloudinary_connected']);
            } catch (Throwable $exception) {
                ResponseHelper::redirect('pages/settings.php', [
                    'tab' => 'external',
                    'saved' => 'cloudinary',
                    'test' => 'cloudinary_failed',
                    'message' => mb_substr($exception->getMessage(), 0, 180),
                ]);
            }
        }

        if ($errors === [] && $action === 'test_telegram') {
            try {
                (new TelegramService($settingsService))->testConnection();
                ResponseHelper::redirect('pages/settings.php', ['tab' => 'external', 'saved' => 'telegram', 'test' => 'telegram_connected']);
            } catch (Throwable $exception) {
                ResponseHelper::redirect('pages/settings.php', [
                    'tab' => 'external',
                    'saved' => 'telegram',
                    'test' => 'telegram_failed',
                    'message' => mb_substr($exception->getMessage(), 0, 180),
                ]);
            }
        }

        if ($errors === []) {
            ResponseHelper::redirect('pages/settings.php', ['tab' => $tab, 'saved' => $action]);
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd ustawień: ' . $exception->getMessage();
}

function settings_status_badge(string $status): string
{
    return match ($status) {
        'active' => 'badge-success',
        'inactive' => 'badge-warning',
        'archived' => 'badge-muted',
        default => 'badge-sand',
    };
}

function settings_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Aktywny',
        'inactive' => 'Nieaktywny',
        'archived' => 'Archiwalny',
        default => $status,
    };
}

function settings_logo_src(string $path): string
{
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?://|/)#', $path) === 1) {
        return $path;
    }

    return url($path);
}

render_page('Ustawienia', 'settings', function () use ($settingsService, $errors, $notice, $testResult, $testMessage): void {
    $school = [
        'name' => 'Limodelia',
        'phone' => '',
        'instagram' => '',
        'logo_url' => '',
        'logo_dark_url' => '',
        'favicon_url' => '',
        'nip' => '',
        'regon' => '',
    ];
    $cloudinary = [
        'enabled' => false,
        'cloud_name' => '',
        'api_key' => '',
        'api_secret' => '',
        'root_folder' => 'limodelia',
    ];
    $telegram = [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ];
    $ui = SettingsService::defaultUi();
    $paymentSettings = ['show_cancelled' => false];
    $expenseSettings = [
        'show_cancelled' => false,
        'rental_hourly_rate' => 32.00,
        'individual_rental_duration_minutes' => 60,
    ];
    $lessonSchedule = [];
    $weekdayLabels = [];
    $subscriptions = [];
    $groups = [];
    $salaryRules = [];
    $system = [
        'app_version' => '0.1.0',
        'database_name' => '',
        'php_version' => PHP_VERSION,
        'timezone' => date_default_timezone_get(),
    ];

    if ($settingsService instanceof SettingsService) {
        $school = $settingsService->school();
        $cloudinary = $settingsService->cloudinary();
        $telegram = $settingsService->telegram();
        $ui = $settingsService->ui();
        $paymentSettings = $settingsService->payments();
        $expenseSettings = $settingsService->expenses();
        $lessonSchedule = $settingsService->lessonSchedule();
        $weekdayLabels = $settingsService->weekdayLabels();
        $subscriptions = $settingsService->subscriptions();
        $groups = $settingsService->groups();
        $salaryRules = $settingsService->salaryRules();
        $system = $settingsService->systemInfo();
    }

    $nextSubscriptionSortOrder = 10;
    if ($subscriptions !== []) {
        $subscriptionSortOrders = array_map(static fn (array $subscription): int => (int) ($subscription['sort_order'] ?? 0), $subscriptions);
        $nextSubscriptionSortOrder = max($subscriptionSortOrders) + 10;
    }

    $activeTab = (string) ($_GET['tab'] ?? 'school');
    $allowedTabs = ['school', 'subscriptions', 'payments', 'schedule', 'groups', 'salaries', 'external', 'appearance', 'system'];
    $activeTab = in_array($activeTab, $allowedTabs, true) ? $activeTab : 'school';
    $hasSecret = trim((string) $cloudinary['api_secret']) !== '';
    $hasTelegramToken = trim((string) $telegram['bot_token']) !== '';
    $themeColorRoles = [
        'background' => ['Tło strony', 'Główne tło ekranów i przestrzeni roboczej.'],
        'surface' => ['Karty i panele', 'Karty, formularze, listy i rozwijane menu.'],
        'primary' => ['Główny akcent', 'Przyciski, aktywne menu i linia przychodów.'],
        'accent' => ['Dodatkowy akcent', 'Podświetlenia, najechanie i linia wydatków.'],
    ];
    ?>
    <section class="module-hero">
        <div>
            <h1>Ustawienia</h1>
            <p>Zarządzaj ustawieniami szkoły i systemu.</p>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Ustawienia zostały zapisane.</div>
    <?php endif; ?>
    <?php if ($testResult === 'cloudinary_connected'): ?>
        <div class="alert alert-info">Cloudinary: Connected.</div>
    <?php elseif ($testResult === 'cloudinary_failed'): ?>
        <div class="alert alert-info">Cloudinary: Connection failed<?= $testMessage ? ' - ' . FormatHelper::e((string) $testMessage) : '' ?>.</div>
    <?php elseif ($testResult === 'telegram_connected'): ?>
        <div class="alert alert-info">Telegram: Connected.</div>
    <?php elseif ($testResult === 'telegram_failed'): ?>
        <div class="alert alert-info">Telegram: Connection failed<?= $testMessage ? ' - ' . FormatHelper::e((string) $testMessage) : '' ?>.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="settings-shell" data-tabs>
        <nav class="tabs settings-tabs" aria-label="Sekcje ustawień">
            <button class="tab <?= $activeTab === 'school' ? 'is-active' : '' ?>" type="button" data-tab-target="school" aria-selected="<?= $activeTab === 'school' ? 'true' : 'false' ?>">Dane szkoły</button>
            <button class="tab <?= $activeTab === 'subscriptions' ? 'is-active' : '' ?>" type="button" data-tab-target="subscriptions" aria-selected="<?= $activeTab === 'subscriptions' ? 'true' : 'false' ?>">Abonamenty</button>
            <button class="tab <?= $activeTab === 'payments' ? 'is-active' : '' ?>" type="button" data-tab-target="payments" aria-selected="<?= $activeTab === 'payments' ? 'true' : 'false' ?>">Płatności</button>
            <button class="tab <?= $activeTab === 'schedule' ? 'is-active' : '' ?>" type="button" data-tab-target="schedule" aria-selected="<?= $activeTab === 'schedule' ? 'true' : 'false' ?>">Dni zajęć</button>
            <button class="tab <?= $activeTab === 'groups' ? 'is-active' : '' ?>" type="button" data-tab-target="groups" aria-selected="<?= $activeTab === 'groups' ? 'true' : 'false' ?>">Grupy</button>
            <button class="tab <?= $activeTab === 'salaries' ? 'is-active' : '' ?>" type="button" data-tab-target="salaries" aria-selected="<?= $activeTab === 'salaries' ? 'true' : 'false' ?>">Wynagrodzenia</button>
            <button class="tab <?= $activeTab === 'external' ? 'is-active' : '' ?>" type="button" data-tab-target="external" aria-selected="<?= $activeTab === 'external' ? 'true' : 'false' ?>">Usługi zewnętrzne</button>
            <button class="tab <?= $activeTab === 'appearance' ? 'is-active' : '' ?>" type="button" data-tab-target="appearance" aria-selected="<?= $activeTab === 'appearance' ? 'true' : 'false' ?>">Wygląd</button>
            <button class="tab <?= $activeTab === 'system' ? 'is-active' : '' ?>" type="button" data-tab-target="system" aria-selected="<?= $activeTab === 'system' ? 'true' : 'false' ?>">System</button>
        </nav>

        <div class="settings-tab-panel <?= $activeTab === 'school' ? 'is-active' : '' ?>" data-tab-panel="school" <?= $activeTab === 'school' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Dane szkoły</h2>
                        <p class="profile-note">Podstawowe informacje widoczne w systemie.</p>
                    </div>
                </div>
                <form class="settings-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_school">
                    <input type="hidden" name="tab" value="school">
                    <input type="hidden" name="school_logo_url" value="<?= FormatHelper::e((string) $school['logo_url']) ?>">
                    <input type="hidden" name="school_logo_dark_url" value="<?= FormatHelper::e((string) $school['logo_dark_url']) ?>">
                    <input type="hidden" name="school_favicon_url" value="<?= FormatHelper::e((string) $school['favicon_url']) ?>">
                    <div class="settings-school-grid">
                        <div class="settings-logo-previews">
                            <div>
                                <span class="form-label">Logo jasne</span>
                                <div class="settings-logo-preview">
                                    <?php if ((string) $school['logo_url'] !== ''): ?>
                                        <img src="<?= FormatHelper::e(settings_logo_src((string) $school['logo_url'])) ?>" alt="Logo jasne Limodelia">
                                    <?php else: ?>
                                        <strong>Limodelia</strong>
                                        <span>Szkoła modelek</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span class="form-label">Logo ciemne</span>
                                <div class="settings-logo-preview settings-logo-preview-dark">
                                    <?php if ((string) $school['logo_dark_url'] !== ''): ?>
                                        <img src="<?= FormatHelper::e(settings_logo_src((string) $school['logo_dark_url'])) ?>" alt="Logo ciemne Limodelia">
                                    <?php else: ?>
                                        <strong>Limodelia</strong>
                                        <span>Tryb ciemny</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="payment-form-grid">
                            <label class="form-field">
                                <span class="form-label">Nazwa szkoły</span>
                                <input class="input" name="school_name" value="<?= FormatHelper::e((string) $school['name']) ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Telefon</span>
                                <input class="input" name="school_phone" value="<?= FormatHelper::e(FormatHelper::normalizePhone((string) $school['phone']) ?? '') ?>" placeholder="+48 600 123 456" maxlength="16" inputmode="numeric" data-phone-input>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Instagram</span>
                                <input class="input" name="school_instagram" value="<?= FormatHelper::e((string) $school['instagram']) ?>" placeholder="limodelia">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Logo szkoły - tryb jasny</span>
                                <input class="input" type="file" name="school_logo_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Logo szkoły - tryb ciemny</span>
                                <input class="input" type="file" name="school_logo_dark_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Ikona karty przeglądarki</span>
                                <input class="input" type="file" name="school_favicon_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                                <small class="form-help">Najlepiej kwadratowy obraz PNG, co najmniej 64 × 64 px.</small>
                            </label>
                            <label class="form-field">
                                <span class="form-label">NIP</span>
                                <input class="input" name="school_nip" value="<?= FormatHelper::e((string) $school['nip']) ?>">
                            </label>
                            <label class="form-field">
                                <span class="form-label">REGON</span>
                                <input class="input" name="school_regon" value="<?= FormatHelper::e((string) $school['regon']) ?>">
                            </label>
                        </div>
                    </div>
                    <div class="settings-actions">
                        <button class="btn btn-primary" type="submit">Zapisz dane szkoły</button>
                    </div>
                </form>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'subscriptions' ? 'is-active' : '' ?>" data-tab-panel="subscriptions" <?= $activeTab === 'subscriptions' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Abonamenty</h2>
                        <p class="profile-note">Zmiana ceny dotyczy nowych płatności. Abonamenty nie są usuwane trwale.</p>
                    </div>
                </div>
                <details class="settings-add-panel">
                    <summary class="btn btn-secondary">+ Dodaj abonament</summary>
                    <form class="settings-row-form settings-row-form-add" method="post">
                        <input type="hidden" name="action" value="add_subscription">
                        <input type="hidden" name="tab" value="subscriptions">
                        <label class="form-field">
                            <span class="form-label">Nazwa</span>
                            <input class="input" name="name" placeholder="np. 12 zajęć" required>
                        </label>
                        <label class="form-field">
                            <span class="form-label">Zajęcia</span>
                            <input class="input" type="number" min="1" name="lessons_count" value="4" required>
                        </label>
                        <label class="form-field">
                            <span class="form-label">Cena</span>
                            <input class="input" type="number" min="0" step="0.01" name="price" placeholder="0,00" required>
                        </label>
                        <input type="hidden" name="sort_order" value="<?= $nextSubscriptionSortOrder ?>">
                        <label class="form-field">
                            <span class="form-label">Status</span>
                            <select class="select" name="status">
                                <option value="active">Aktywny</option>
                                <option value="inactive">Nieaktywny</option>
                            </select>
                        </label>
                        <button class="btn btn-primary" type="submit">Dodaj</button>
                    </form>
                </details>
                <div class="settings-list settings-sortable-list" data-subscription-sortable>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <form class="settings-row-form settings-subscription-row" method="post" draggable="true" data-subscription-sort-row data-subscription-id="<?= (int) $subscription['id'] ?>">
                            <button class="settings-sort-handle" type="button" aria-label="Przeciągnij abonament">
                                <?= IconHelper::svg('drag') ?>
                            </button>
                            <input type="hidden" name="action" value="save_subscription">
                            <input type="hidden" name="tab" value="subscriptions">
                            <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                            <input type="hidden" name="sort_order" value="<?= (int) ($subscription['sort_order'] ?? 0) ?>" data-subscription-sort-input>
                            <label class="form-field">
                                <span class="form-label">Nazwa</span>
                                <input class="input" name="name" value="<?= FormatHelper::e((string) $subscription['name']) ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Zajęcia</span>
                                <input class="input" type="number" min="1" name="lessons_count" value="<?= (int) $subscription['lessons_count'] ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Cena</span>
                                <input class="input" type="number" min="0" step="0.01" name="price" value="<?= FormatHelper::e((string) $subscription['price']) ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Status</span>
                                <select class="select" name="status">
                                    <?php foreach (['active', 'inactive', 'archived'] as $status): ?>
                                        <option value="<?= $status ?>" <?= $subscription['status'] === $status ? 'selected' : '' ?>><?= settings_status_label($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <span class="badge <?= settings_status_badge((string) $subscription['status']) ?>"><?= settings_status_label((string) $subscription['status']) ?></span>
                            <button class="btn btn-secondary" type="submit">Zapisz</button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <form class="settings-order-form" method="post" data-subscription-order-form hidden>
                    <input type="hidden" name="action" value="save_subscription_order">
                    <input type="hidden" name="tab" value="subscriptions">
                    <div data-subscription-order-fields></div>
                </form>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'payments' ? 'is-active' : '' ?>" data-tab-panel="payments" <?= $activeTab === 'payments' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Płatności</h2>
                        <p class="profile-note">Ustawienia widoczności płatności na liście.</p>
                    </div>
                </div>
                <form class="settings-form" method="post">
                    <input type="hidden" name="action" value="save_payment_settings">
                    <input type="hidden" name="tab" value="payments">
                    <section class="settings-section">
                        <label class="settings-toggle">
                            <input type="checkbox" name="payments_show_cancelled" value="1" <?= $paymentSettings['show_cancelled'] ? 'checked' : '' ?>>
                            <span>Pokazuj anulowane płatności na liście</span>
                        </label>
                        <label class="settings-toggle">
                            <input type="checkbox" name="expenses_show_cancelled" value="1" <?= $expenseSettings['show_cancelled'] ? 'checked' : '' ?>>
                            <span>Pokazuj anulowane koszty na liście</span>
                        </label>
                        <p class="form-help">Gdy opcja jest wyłączona, pozycje ze statusem Anulowana są automatycznie ukryte na stronach Płatności i Koszty.</p>
                        <div class="settings-row-form settings-row-form-wide">
                            <label class="form-field">
                                <span class="form-label">Stawka za godzinę sali</span>
                                <input class="input" type="number" min="0.01" step="0.01" name="expense_rental_hourly_rate" value="<?= FormatHelper::e(number_format((float) ($expenseSettings['rental_hourly_rate'] ?? 32.00), 2, '.', '')) ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Czas indywidualnej rezerwacji</span>
                                <input class="input" type="number" min="1" step="1" name="expense_individual_rental_duration_minutes" value="<?= FormatHelper::e((string) (int) ($expenseSettings['individual_rental_duration_minutes'] ?? 60)) ?>" required>
                                <span class="form-help">W minutach. Domyślnie 60.</span>
                            </label>
                        </div>
                        <div class="settings-actions">
                            <button class="btn btn-primary" type="submit">Zapisz ustawienia płatności</button>
                        </div>
                    </section>
                </form>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'schedule' ? 'is-active' : '' ?>" data-tab-panel="schedule" <?= $activeTab === 'schedule' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Dni zajęć</h2>
                        <p class="profile-note">Te ustawienia synchronizują kalendarz na Dashboardzie oraz rezerwację sali w kosztach.</p>
                    </div>
                </div>
                <?php
                $scheduleByDay = [];
                foreach ($lessonSchedule as $item) {
                    $scheduleByDay[(int) $item['weekday']] = $item;
                }
                ?>
                <form class="settings-form" method="post">
                    <input type="hidden" name="action" value="save_lesson_schedule">
                    <input type="hidden" name="tab" value="schedule">
                    <div class="settings-list">
                        <?php foreach ($weekdayLabels as $weekday => $label): ?>
                            <?php $item = $scheduleByDay[(int) $weekday] ?? null; ?>
                            <div class="settings-row-form settings-row-form-schedule">
                                <label class="settings-toggle settings-toggle-compact">
                                    <input type="checkbox" name="schedule_enabled[<?= (int) $weekday ?>]" value="1" <?= $item ? 'checked' : '' ?>>
                                    <span><?= FormatHelper::e($label) ?></span>
                                </label>
                                <label class="form-field">
                                    <span class="form-label">Od</span>
                                    <input class="input" type="time" name="schedule_start[<?= (int) $weekday ?>]" value="<?= FormatHelper::e((string) ($item['start'] ?? '')) ?>">
                                </label>
                                <label class="form-field">
                                    <span class="form-label">Do</span>
                                    <input class="input" type="time" name="schedule_end[<?= (int) $weekday ?>]" value="<?= FormatHelper::e((string) ($item['end'] ?? '')) ?>">
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="settings-actions">
                        <button class="btn btn-primary" type="submit">Zapisz dni zajęć</button>
                    </div>
                </form>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'groups' ? 'is-active' : '' ?>" data-tab-panel="groups" <?= $activeTab === 'groups' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Grupy</h2>
                        <p class="profile-note">Grupy kontrolują klasyfikację uczennic i filtry w CRM.</p>
                    </div>
                </div>
                <div class="settings-list">
                    <form class="settings-row-form settings-row-form-wide" method="post">
                        <input type="hidden" name="action" value="add_group">
                        <input type="hidden" name="tab" value="groups">
                        <input type="hidden" name="status" value="active">
                        <label class="form-field"><span class="form-label">Nazwa nowej grupy</span><input class="input" name="name" maxlength="120" required></label>
                        <label class="form-field"><span class="form-label">Limit uczennic</span><input class="input" type="number" min="1" name="max_students"></label>
                        <label class="form-field"><span class="form-label">Kolor</span><input class="input" type="color" name="color" value="#C89B3C"></label>
                        <label class="settings-toggle settings-toggle-compact"><input type="checkbox" name="is_visible" value="1" checked><span>Widoczna</span></label>
                        <button class="btn btn-primary" type="submit">Dodaj grupę</button>
                    </form>
                    <?php foreach ($groups as $group): ?>
                        <form class="settings-row-form settings-row-form-wide" method="post">
                            <input type="hidden" name="action" value="save_group">
                            <input type="hidden" name="tab" value="groups">
                            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                            <label class="form-field">
                                <span class="form-label">Nazwa</span>
                                <input class="input" name="name" value="<?= FormatHelper::e((string) $group['name']) ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Limit uczennic</span>
                                <input class="input" type="number" min="1" name="max_students" value="<?= FormatHelper::e((string) ($group['max_students'] ?? '')) ?>">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Kolor</span>
                                <input class="input" type="color" name="color" value="<?= FormatHelper::e((string) ($group['color'] ?: '#C89B3C')) ?>">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Status</span>
                                <select class="select" name="status">
                                    <?php foreach (['active', 'archived'] as $status): ?>
                                        <option value="<?= $status ?>" <?= $group['status'] === $status ? 'selected' : '' ?>><?= settings_status_label($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="settings-toggle settings-toggle-compact">
                                <input type="checkbox" name="is_visible" value="1" <?= ((int) $group['is_visible']) === 1 ? 'checked' : '' ?>>
                                <span>Widoczna</span>
                            </label>
                            <button class="btn btn-secondary" type="submit">Zapisz</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'salaries' ? 'is-active' : '' ?>" data-tab-panel="salaries" <?= $activeTab === 'salaries' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Wynagrodzenia</h2>
                        <p class="profile-note">Reguły są używane przez centrum wynagrodzeń i Dashboard.</p>
                    </div>
                </div>
                <div class="settings-list settings-salary-list">
                    <?php foreach ($salaryRules as $rule): ?>
                        <form class="settings-row-form settings-row-form-salary" method="post">
                            <input type="hidden" name="action" value="save_salary_rule">
                            <input type="hidden" name="tab" value="salaries">
                            <input type="hidden" name="salary_rule_id" value="<?= (int) $rule['id'] ?>">
                            <label class="form-field">
                                <span class="form-label">Nazwa</span>
                                <input class="input" name="name" value="<?= FormatHelper::e((string) $rule['name']) ?>" required>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Typ</span>
                                <select class="select" name="rule_type">
                                    <option value="fixed" <?= $rule['rule_type'] === 'fixed' ? 'selected' : '' ?>>Stała kwota</option>
                                    <option value="percentage_net_profit" <?= $rule['rule_type'] === 'percentage_net_profit' ? 'selected' : '' ?>>Procent zysku netto</option>
                                    <option value="per_student" <?= $rule['rule_type'] === 'per_student' ? 'selected' : '' ?>>Za nową uczennicę</option>
                                    <?php if ($rule['role_scope'] === 'teacher'): ?>
                                        <option value="remaining_profit" <?= $rule['rule_type'] === 'remaining_profit' ? 'selected' : '' ?>>Pozostały zysk</option>
                                    <?php endif; ?>
                                </select>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Kwota</span>
                                <input class="input" type="number" min="0" step="0.01" name="amount" value="<?= FormatHelper::e((string) ($rule['amount'] ?? '')) ?>">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Procent</span>
                                <input class="input" type="number" min="0" max="100" step="0.01" name="percentage" value="<?= FormatHelper::e((string) ($rule['percentage'] ?? '')) ?>">
                            </label>
                            <label class="form-field">
                                <span class="form-label">Status</span>
                                <select class="select" name="status">
                                    <?php foreach (['active', 'inactive', 'archived'] as $status): ?>
                                        <option value="<?= $status ?>" <?= $rule['status'] === $status ? 'selected' : '' ?>><?= settings_status_label($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="btn btn-secondary" type="submit">Zapisz</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'external' ? 'is-active' : '' ?>" data-tab-panel="external" <?= $activeTab === 'external' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Usługi zewnętrzne</h2>
                        <p class="profile-note">Konfiguracja integracji używanych przez CRM.</p>
                    </div>
                </div>
                <form class="settings-form" method="post">
                    <input type="hidden" name="tab" value="external">
                    <section class="settings-section">
                        <div>
                            <h3 class="form-section-title">Cloudinary</h3>
                            <p class="profile-note">Zdjęcia wyjazdów i przyszłe zasoby są przechowywane w Cloudinary.</p>
                        </div>
                        <label class="settings-toggle">
                            <input type="checkbox" name="cloudinary_enabled" value="1" <?= $cloudinary['enabled'] ? 'checked' : '' ?>>
                            <span>Enabled</span>
                        </label>
                        <div class="payment-form-grid">
                            <label class="form-field">
                                <span class="form-label">Cloud Name</span>
                                <input class="input" name="cloudinary_cloud_name" value="<?= FormatHelper::e((string) $cloudinary['cloud_name']) ?>" autocomplete="off">
                            </label>
                            <label class="form-field">
                                <span class="form-label">API Key</span>
                                <input class="input" name="cloudinary_api_key" value="<?= FormatHelper::e((string) $cloudinary['api_key']) ?>" autocomplete="off">
                            </label>
                            <label class="form-field">
                                <span class="form-label">API Secret</span>
                                <input class="input" type="password" name="cloudinary_api_secret" value="" placeholder="<?= $hasSecret ? '••••••••••' : '' ?>" autocomplete="new-password">
                                <?php if ($hasSecret): ?>
                                    <small class="form-help">Pozostaw puste, aby zachować obecny sekret.</small>
                                <?php endif; ?>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Root Folder</span>
                                <input class="input" name="cloudinary_root_folder" value="<?= FormatHelper::e((string) $cloudinary['root_folder']) ?>" autocomplete="off">
                            </label>
                        </div>
                        <div class="payment-form-actions settings-actions">
                            <button class="btn btn-secondary" type="submit" name="action" value="test_cloudinary">Test Cloudinary</button>
                            <button class="btn btn-primary" type="submit" name="action" value="save_cloudinary">Zapisz ustawienia</button>
                        </div>
                    </section>
                </form>
                <form class="settings-form" method="post">
                    <input type="hidden" name="tab" value="external">
                    <section class="settings-section">
                        <div>
                            <h3 class="form-section-title">Telegram</h3>
                            <p class="profile-note">Powiadomienia CRM mogą być wysyłane do grupowego czatu Telegram.</p>
                        </div>
                        <label class="settings-toggle">
                            <input type="checkbox" name="telegram_enabled" value="1" <?= $telegram['enabled'] ? 'checked' : '' ?>>
                            <span>Enabled</span>
                        </label>
                        <div class="payment-form-grid">
                            <label class="form-field">
                                <span class="form-label">Bot Token</span>
                                <input class="input" type="password" name="telegram_bot_token" value="" placeholder="<?= $hasTelegramToken ? '••••••••••' : '' ?>" autocomplete="new-password">
                                <?php if ($hasTelegramToken): ?>
                                    <small class="form-help">Pozostaw puste, aby zachować obecny token.</small>
                                <?php endif; ?>
                            </label>
                            <label class="form-field">
                                <span class="form-label">Chat ID</span>
                                <input class="input" name="telegram_chat_id" value="<?= FormatHelper::e((string) $telegram['chat_id']) ?>" placeholder="-1001234567890" autocomplete="off">
                            </label>
                        </div>
                        <div class="payment-form-actions settings-actions">
                            <button class="btn btn-secondary" type="submit" name="action" value="test_telegram">Test Telegram</button>
                            <button class="btn btn-primary" type="submit" name="action" value="save_telegram">Zapisz Telegram</button>
                        </div>
                    </section>
                </form>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'appearance' ? 'is-active' : '' ?>" data-tab-panel="appearance" <?= $activeTab === 'appearance' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Wygląd</h2>
                        <p class="profile-note">Wybierz domyślny motyw i ustaw cztery kolory odpowiedzialne za grupy elementów.</p>
                    </div>
                </div>
                <form class="settings-theme-form" method="post" data-theme-settings-form>
                    <input type="hidden" name="action" value="save_theme">
                    <input type="hidden" name="tab" value="appearance">
                    <div class="settings-theme-grid">
                        <?php foreach (['light' => ['Jasny', 'Elegant Sand'], 'dark' => ['Ciemny', 'Mahogany Rose']] as $themeName => [$themeLabel, $themeSubtitle]): ?>
                            <?php $palette = (array) ($ui['palettes'][$themeName] ?? []); ?>
                            <article class="settings-theme-option <?= $ui['default_theme'] === $themeName ? 'is-active' : '' ?>" data-theme-card="<?= $themeName ?>">
                                <label class="settings-theme-choice">
                                    <input type="radio" name="default_theme" value="<?= $themeName ?>" <?= $ui['default_theme'] === $themeName ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= $themeLabel ?></strong>
                                        <small><?= $themeSubtitle ?></small>
                                    </span>
                                    <span class="settings-theme-default-label">Motyw domyślny</span>
                                </label>
                                <div class="settings-theme-colors">
                                    <?php foreach ($themeColorRoles as $role => [$label, $description]): ?>
                                        <?php $color = (string) ($palette[$role] ?? '#000000'); ?>
                                        <label class="settings-color-control">
                                            <span class="settings-color-copy">
                                                <strong><?= $label ?></strong>
                                                <small><?= $description ?></small>
                                            </span>
                                            <span class="settings-color-value">
                                                <input type="color" name="theme_colors[<?= $themeName ?>][<?= $role ?>]" value="<?= FormatHelper::e($color) ?>" data-theme-color="<?= $themeName ?>" data-theme-role="<?= $role ?>" aria-label="<?= FormatHelper::e($label . ' — motyw ' . $themeLabel) ?>">
                                                <output><?= FormatHelper::e(strtoupper($color)) ?></output>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="settings-theme-actions">
                        <button class="btn btn-primary" type="submit">Zapisz wygląd</button>
                    </div>
                </form>
            </article>
        </div>

        <div class="settings-tab-panel <?= $activeTab === 'system' ? 'is-active' : '' ?>" data-tab-panel="system" <?= $activeTab === 'system' ? '' : 'hidden' ?>>
            <article class="card settings-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">System</h2>
                        <p class="profile-note">Informacje techniczne tylko do odczytu.</p>
                    </div>
                </div>
                <div class="settings-system-grid">
                    <div><span>Wersja aplikacji</span><strong><?= FormatHelper::e((string) $system['app_version']) ?></strong></div>
                    <div><span>Baza danych</span><strong><?= FormatHelper::e((string) $system['database_name']) ?></strong></div>
                    <div><span>PHP</span><strong><?= FormatHelper::e((string) $system['php_version']) ?></strong></div>
                    <div><span>Strefa czasowa</span><strong><?= FormatHelper::e((string) $system['timezone']) ?></strong></div>
                </div>
            </article>
        </div>
    </section>
    <?php
});
