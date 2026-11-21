<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\FormatHelper;
use App\Repositories\SettingsRepository;

final class SettingsService
{
    private const UI_PALETTES = [
        'light' => [
            'background' => '#F7F5F2',
            'surface' => '#FFFFFF',
            'primary' => '#C89B3C',
            'accent' => '#E25D79',
        ],
        'dark' => [
            'background' => '#171312',
            'surface' => '#231A18',
            'primary' => '#7A4A32',
            'accent' => '#CF78AF',
        ],
    ];

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function cloudinary(): array
    {
        $rows = $this->settings->group('cloudinary');

        return [
            'enabled' => $this->boolValue($rows['cloudinary.enabled']['setting_value'] ?? 'false'),
            'cloud_name' => (string) ($rows['cloudinary.cloud_name']['setting_value'] ?? ''),
            'api_key' => (string) ($rows['cloudinary.api_key']['setting_value'] ?? ''),
            'api_secret' => (string) ($rows['cloudinary.api_secret']['setting_value'] ?? ''),
            'root_folder' => (string) ($rows['cloudinary.root_folder']['setting_value'] ?? 'limodelia'),
        ];
    }

    public function telegram(): array
    {
        $rows = $this->settings->group('telegram');

        return [
            'enabled' => $this->boolValue($rows['telegram.enabled']['setting_value'] ?? 'false'),
            'bot_token' => (string) ($rows['telegram.bot_token']['setting_value'] ?? ''),
            'chat_id' => (string) ($rows['telegram.chat_id']['setting_value'] ?? ''),
        ];
    }

    public function telegramLastUpdateId(): int
    {
        return max(0, (int) ($this->settings->get('telegram.last_update_id') ?? '0'));
    }

    public function saveTelegramLastUpdateId(int $updateId): void
    {
        $this->settings->set('telegram.last_update_id', (string) max(0, $updateId), 'telegram', 'number', true);
    }

    public function school(): array
    {
        $rows = $this->settings->group('school');

        return [
            'name' => (string) ($rows['school.name']['setting_value'] ?? 'Limodelia'),
            'phone' => (string) ($rows['school.phone']['setting_value'] ?? ''),
            'instagram' => (string) ($rows['school.instagram']['setting_value'] ?? ''),
            'logo_url' => (string) ($rows['school.logo_url']['setting_value'] ?? ''),
            'logo_dark_url' => (string) ($rows['school.logo_dark_url']['setting_value'] ?? ''),
            'favicon_url' => (string) ($rows['school.favicon_url']['setting_value'] ?? ''),
            'nip' => (string) ($rows['school.nip']['setting_value'] ?? ''),
            'regon' => (string) ($rows['school.regon']['setting_value'] ?? ''),
        ];
    }

    public function ui(): array
    {
        $rows = $this->settings->group('ui');
        $theme = (string) ($rows['ui.default_theme']['setting_value'] ?? 'light');

        $palettes = self::UI_PALETTES;
        foreach ($palettes as $paletteName => $colors) {
            foreach ($colors as $role => $fallback) {
                $value = (string) ($rows["ui.{$paletteName}.{$role}"]['setting_value'] ?? $fallback);
                $palettes[$paletteName][$role] = $this->normalizeHexColor($value, $fallback);
            }
        }

        return [
            'default_theme' => in_array($theme, ['light', 'dark'], true) ? $theme : 'light',
            'palettes' => $palettes,
        ];
    }

    public static function defaultUi(): array
    {
        return [
            'default_theme' => 'light',
            'palettes' => self::UI_PALETTES,
        ];
    }

    public function subscriptions(): array
    {
        return $this->settings->subscriptions();
    }

    public function payments(): array
    {
        $rows = $this->settings->group('payments');

        return [
            'show_cancelled' => $this->boolValue($rows['payments.show_cancelled']['setting_value'] ?? 'false'),
        ];
    }

    public function expenses(): array
    {
        $rows = $this->settings->group('expenses');
        $rentalHourlyRate = (float) str_replace(',', '.', (string) ($rows['expenses.rental_hourly_rate']['setting_value'] ?? '32.00'));
        $individualRentalDuration = (int) ($rows['expenses.individual_rental_duration_minutes']['setting_value'] ?? 60);

        return [
            'show_cancelled' => $this->boolValue($rows['expenses.show_cancelled']['setting_value'] ?? 'false'),
            'rental_hourly_rate' => $rentalHourlyRate > 0 ? $rentalHourlyRate : 32.00,
            'individual_rental_duration_minutes' => $individualRentalDuration > 0 ? $individualRentalDuration : 60,
        ];
    }

    public function lessonSchedule(): array
    {
        $value = $this->settings->get('schedule.lesson_days');
        if ($value === null || trim($value) === '') {
            return $this->defaultLessonSchedule();
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $this->defaultLessonSchedule();
        }

        return $this->normalizeLessonSchedule($decoded);
    }

    public function groups(): array
    {
        return $this->settings->groups();
    }

    public function salaryRules(): array
    {
        return $this->settings->salaryRules();
    }

    public function systemInfo(): array
    {
        return [
            'app_version' => (string) ($this->settings->get('app.version') ?? '0.1.0'),
            'database_name' => $this->settings->databaseName(),
            'php_version' => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
        ];
    }

    public function saveSchool(array $input, array $files = []): array
    {
        $name = trim((string) ($input['school_name'] ?? ''));
        $phone = $this->normalizePhone((string) ($input['school_phone'] ?? ''));
        $instagram = trim((string) ($input['school_instagram'] ?? ''));
        $logoUrl = trim((string) ($input['school_logo_url'] ?? ''));
        $logoDarkUrl = trim((string) ($input['school_logo_dark_url'] ?? ''));
        $faviconUrl = trim((string) ($input['school_favicon_url'] ?? ''));
        $nip = preg_replace('/\D+/', '', (string) ($input['school_nip'] ?? '')) ?? '';
        $regon = preg_replace('/\D+/', '', (string) ($input['school_regon'] ?? '')) ?? '';

        $errors = [];
        if ($name === '') {
            $errors[] = 'Nazwa szkoły jest wymagana.';
        }
        if ($nip !== '' && strlen($nip) !== 10) {
            $errors[] = 'NIP powinien mieć 10 cyfr.';
        }
        if ($regon !== '' && !in_array(strlen($regon), [9, 14], true)) {
            $errors[] = 'REGON powinien mieć 9 albo 14 cyfr.';
        }

        $uploadedLogo = $this->storeSchoolLogo($files['school_logo_file'] ?? null, $errors, 'light');
        if ($uploadedLogo !== null) {
            $logoUrl = $uploadedLogo;
        }

        $uploadedDarkLogo = $this->storeSchoolLogo($files['school_logo_dark_file'] ?? null, $errors, 'dark');
        if ($uploadedDarkLogo !== null) {
            $logoDarkUrl = $uploadedDarkLogo;
        }

        $uploadedFavicon = $this->storeSchoolLogo($files['school_favicon_file'] ?? null, $errors, 'favicon');
        if ($uploadedFavicon !== null) {
            $faviconUrl = $uploadedFavicon;
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->set('school.name', $name, 'school');
        $this->settings->set('school.phone', $phone, 'school');
        $this->settings->set('school.instagram', ltrim($instagram, '@'), 'school');
        $this->settings->set('school.logo_url', $logoUrl, 'school');
        $this->settings->set('school.logo_dark_url', $logoDarkUrl, 'school');
        $this->settings->set('school.favicon_url', $faviconUrl, 'school');
        $this->settings->set('school.nip', $nip, 'school');
        $this->settings->set('school.regon', $regon, 'school');

        return [];
    }

    private function storeSchoolLogo(mixed $file, array &$errors, string $variant = 'logo'): ?string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Nie udało się przesłać logo szkoły.';
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $errors[] = 'Nieprawidłowy plik logo.';
            return null;
        }

        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $errors[] = 'Logo szkoły może mieć maksymalnie 5 MB.';
            return null;
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            $errors[] = 'Logo szkoły musi być plikiem graficznym.';
            return null;
        }

        $mime = (string) ($imageInfo['mime'] ?? '');
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($extensions[$mime])) {
            $errors[] = 'Logo szkoły musi być w formacie JPG, PNG, WEBP albo GIF.';
            return null;
        }

        $directory = BASE_PATH . '/uploads/settings';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $errors[] = 'Nie udało się utworzyć folderu na logo.';
            return null;
        }

        $safeVariant = preg_replace('/[^a-z0-9_-]+/i', '_', $variant) ?: 'logo';
        $fileName = 'school_logo_' . $safeVariant . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $extensions[$mime];
        $target = $directory . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $target)) {
            $errors[] = 'Nie udało się zapisać logo szkoły.';
            return null;
        }

        return 'uploads/settings/' . $fileName;
    }

    public function saveSubscription(array $input): array
    {
        $id = (int) ($input['subscription_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $lessons = (int) ($input['lessons_count'] ?? 0);
        $price = (float) str_replace(',', '.', (string) ($input['price'] ?? 0));
        $status = (string) ($input['status'] ?? 'active');
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Brak ID abonamentu.';
        }
        if ($name === '') {
            $errors[] = 'Nazwa abonamentu jest wymagana.';
        }
        if ($lessons <= 0) {
            $errors[] = 'Liczba zajęć musi być większa od zera.';
        }
        if ($price < 0) {
            $errors[] = 'Cena nie może być ujemna.';
        }
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            $errors[] = 'Nieprawidłowy status abonamentu.';
        }
        if ($sortOrder < 0) {
            $errors[] = 'Kolejność nie może być ujemna.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->updateSubscription($id, [
            'name' => $name,
            'lessons_count' => $lessons,
            'price' => number_format($price, 2, '.', ''),
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);

        return [];
    }

    public function saveSubscriptionOrder(array $input): array
    {
        $ids = array_values(array_filter(
            array_map(static fn ($id): int => (int) $id, (array) ($input['subscription_order'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids === []) {
            return ['Brak abonamentów do zapisania.'];
        }

        $this->settings->updateSubscriptionOrder($ids);

        return [];
    }

    public function addSubscription(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $lessons = (int) ($input['lessons_count'] ?? 0);
        $price = (float) str_replace(',', '.', (string) ($input['price'] ?? 0));
        $status = (string) ($input['status'] ?? 'active');
        $sortOrder = (int) ($input['sort_order'] ?? $this->settings->nextSubscriptionSortOrder());

        $errors = [];
        if ($name === '') {
            $errors[] = 'Nazwa abonamentu jest wymagana.';
        }
        if ($lessons <= 0) {
            $errors[] = 'Liczba zajęć musi być większa od zera.';
        }
        if ($price < 0) {
            $errors[] = 'Cena nie może być ujemna.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Nieprawidłowy status abonamentu.';
        }
        if ($sortOrder < 0) {
            $errors[] = 'Kolejność nie może być ujemna.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->createSubscription([
            'name' => $name,
            'slug' => $this->uniqueSubscriptionSlug($name),
            'lessons_count' => $lessons,
            'price' => number_format($price, 2, '.', ''),
            'currency' => 'PLN',
            'billing_period' => 'monthly',
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);

        return [];
    }

    public function saveGroup(array $input, bool $create = false): array
    {
        $id = (int) ($input['group_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $maxStudents = trim((string) ($input['max_students'] ?? ''));
        $color = trim((string) ($input['color'] ?? ''));
        $status = (string) ($input['status'] ?? 'active');
        $visible = isset($input['is_visible']) ? 1 : 0;

        $errors = [];
        if (!$create && $id <= 0) {
            $errors[] = 'Brak ID grupy.';
        }
        if ($name === '') {
            $errors[] = 'Nazwa grupy jest wymagana.';
        }
        if ($maxStudents !== '' && (int) $maxStudents <= 0) {
            $errors[] = 'Limit uczennic musi być większy od zera.';
        }
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $errors[] = 'Kolor grupy musi być w formacie HEX.';
        }
        if (!in_array($status, ['active', 'archived'], true)) {
            $errors[] = 'Nieprawidłowy status grupy.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $data = [
            'name' => $name,
            'max_students' => $maxStudents === '' ? null : (int) $maxStudents,
            'color' => $color === '' ? null : $color,
            'status' => $status,
            'is_visible' => $visible,
        ];
        if ($create) {
            $this->settings->createGroup($data);
        } else {
            $this->settings->updateGroup($id, $data);
        }

        return [];
    }

    public function saveSalaryRule(array $input): array
    {
        $id = (int) ($input['salary_rule_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $ruleType = (string) ($input['rule_type'] ?? 'fixed');
        $amount = trim((string) ($input['amount'] ?? ''));
        $percentage = trim((string) ($input['percentage'] ?? ''));
        $status = (string) ($input['status'] ?? 'active');

        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Brak ID reguły wynagrodzenia.';
        }
        if ($name === '') {
            $errors[] = 'Nazwa reguły jest wymagana.';
        }
        if (!in_array($ruleType, ['fixed', 'percentage_net_profit', 'per_student', 'remaining_profit'], true)) {
            $errors[] = 'Nieprawidłowy typ wynagrodzenia.';
        }
        if (in_array($ruleType, ['fixed', 'per_student'], true) && (float) str_replace(',', '.', $amount) < 0) {
            $errors[] = 'Kwota wynagrodzenia nie może być ujemna.';
        }
        if ($ruleType === 'percentage_net_profit') {
            $percent = (float) str_replace(',', '.', $percentage);
            if ($percent < 0 || $percent > 100) {
                $errors[] = 'Procent musi być w zakresie 0-100.';
            }
        }
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            $errors[] = 'Nieprawidłowy status reguły.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->updateSalaryRule($id, [
            'name' => $name,
            'rule_type' => $ruleType,
            'amount' => in_array($ruleType, ['fixed', 'per_student'], true) ? number_format((float) str_replace(',', '.', $amount), 2, '.', '') : null,
            'percentage' => $ruleType === 'percentage_net_profit' ? number_format((float) str_replace(',', '.', $percentage), 2, '.', '') : null,
            'status' => $status,
        ]);

        return [];
    }

    public function saveTheme(array $input): array
    {
        $theme = (string) ($input['default_theme'] ?? 'light');
        if (!in_array($theme, ['light', 'dark'], true)) {
            return ['Nieprawidłowy motyw systemu.'];
        }

        $submittedPalettes = is_array($input['theme_colors'] ?? null) ? $input['theme_colors'] : [];
        $validatedPalettes = [];
        foreach (self::UI_PALETTES as $paletteName => $colors) {
            $submittedColors = is_array($submittedPalettes[$paletteName] ?? null) ? $submittedPalettes[$paletteName] : [];
            foreach ($colors as $role => $fallback) {
                $value = strtoupper(trim((string) ($submittedColors[$role] ?? $fallback)));
                if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
                    return ['Każdy kolor motywu musi mieć format #RRGGBB.'];
                }
                $validatedPalettes[$paletteName][$role] = $value;
            }
        }

        $this->settings->set('ui.default_theme', $theme, 'ui');
        foreach ($validatedPalettes as $paletteName => $colors) {
            foreach ($colors as $role => $value) {
                $this->settings->set("ui.{$paletteName}.{$role}", $value, 'ui');
            }
        }

        return [];
    }

    private function normalizeHexColor(string $value, string $fallback): string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^#[0-9A-F]{6}$/', $value) === 1 ? $value : $fallback;
    }

    public function savePaymentSettings(array $input): array
    {
        $rentalHourlyRate = (float) str_replace(',', '.', (string) ($input['expense_rental_hourly_rate'] ?? '32.00'));
        $individualRentalDuration = (int) ($input['expense_individual_rental_duration_minutes'] ?? 60);
        $errors = [];

        if ($rentalHourlyRate <= 0) {
            $errors[] = 'Stawka za godzinę sali musi być większa od zera.';
        }
        if ($individualRentalDuration <= 0) {
            $errors[] = 'Czas indywidualnej rezerwacji musi być większy od zera.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $this->settings->set(
            'payments.show_cancelled',
            isset($input['payments_show_cancelled']) ? 'true' : 'false',
            'payments',
            'boolean'
        );
        $this->settings->set(
            'expenses.show_cancelled',
            isset($input['expenses_show_cancelled']) ? 'true' : 'false',
            'expenses',
            'boolean'
        );
        $this->settings->set(
            'expenses.rental_hourly_rate',
            number_format($rentalHourlyRate, 2, '.', ''),
            'expenses',
            'number'
        );
        $this->settings->set(
            'expenses.individual_rental_duration_minutes',
            (string) $individualRentalDuration,
            'expenses',
            'number'
        );

        return [];
    }

    public function saveLessonSchedule(array $input): array
    {
        $enabled = (array) ($input['schedule_enabled'] ?? []);
        $starts = (array) ($input['schedule_start'] ?? []);
        $ends = (array) ($input['schedule_end'] ?? []);
        $items = [];
        $errors = [];

        foreach ($this->weekdayLabels() as $weekday => $label) {
            if (!isset($enabled[$weekday])) {
                continue;
            }

            $start = trim((string) ($starts[$weekday] ?? ''));
            $end = trim((string) ($ends[$weekday] ?? ''));

            if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                $errors[] = 'Uzupełnij poprawny czas dla dnia: ' . $label . '.';
                continue;
            }

            if ($end <= $start) {
                $errors[] = 'Godzina zakończenia musi być późniejsza niż rozpoczęcia dla dnia: ' . $label . '.';
                continue;
            }

            $items[] = [
                'weekday' => (int) $weekday,
                'label' => $label,
                'start' => $start,
                'end' => $end,
            ];
        }

        if ($items === []) {
            $errors[] = 'Wybierz przynajmniej jeden dzień zajęć.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->set('schedule.lesson_days', json_encode($items, JSON_UNESCAPED_UNICODE), 'schedule', 'json');

        return [];
    }

    public function saveCloudinary(array $input): array
    {
        $enabled = isset($input['cloudinary_enabled']);
        $cloudName = trim((string) ($input['cloudinary_cloud_name'] ?? ''));
        $apiKey = trim((string) ($input['cloudinary_api_key'] ?? ''));
        $apiSecret = trim((string) ($input['cloudinary_api_secret'] ?? ''));
        $rootFolder = trim((string) ($input['cloudinary_root_folder'] ?? 'limodelia'));
        $current = $this->cloudinary();

        $errors = [];
        if ($enabled && $cloudName === '') {
            $errors[] = 'Cloud Name jest wymagany, gdy Cloudinary jest włączone.';
        }
        if ($enabled && $apiKey === '') {
            $errors[] = 'API Key jest wymagany, gdy Cloudinary jest włączone.';
        }
        if ($enabled && $apiSecret === '' && $current['api_secret'] === '') {
            $errors[] = 'API Secret jest wymagany, gdy Cloudinary jest włączone.';
        }
        if ($rootFolder === '') {
            $rootFolder = 'limodelia';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->set('cloudinary.enabled', $enabled ? 'true' : 'false', 'cloudinary', 'boolean');
        $this->settings->set('cloudinary.cloud_name', $cloudName, 'cloudinary', 'string', true);
        $this->settings->set('cloudinary.api_key', $apiKey, 'cloudinary', 'string', true);
        if ($apiSecret !== '') {
            $this->settings->set('cloudinary.api_secret', $apiSecret, 'cloudinary', 'string', true);
        }
        $this->settings->set('cloudinary.root_folder', $rootFolder, 'cloudinary', 'string');

        return [];
    }

    public function saveTelegram(array $input): array
    {
        $enabled = isset($input['telegram_enabled']);
        $botToken = trim((string) ($input['telegram_bot_token'] ?? ''));
        $chatId = trim((string) ($input['telegram_chat_id'] ?? ''));
        $current = $this->telegram();

        if ($botToken === '') {
            $botToken = (string) ($current['bot_token'] ?? '');
        }

        $errors = [];
        if ($enabled && $botToken === '') {
            $errors[] = 'Bot Token jest wymagany, jeśli Telegram jest włączony.';
        }
        if ($enabled && $chatId === '') {
            $errors[] = 'Chat ID jest wymagany, jeśli Telegram jest włączony.';
        }

        if ($botToken !== '' && !preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $botToken)) {
            $errors[] = 'Bot Token ma nieprawidłowy format.';
        }
        if ($chatId !== '' && !preg_match('/^-?\d+$/', $chatId)) {
            $errors[] = 'Chat ID powinien zawierać tylko cyfry, opcjonalnie z minusem na początku.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->settings->set('telegram.enabled', $enabled ? 'true' : 'false', 'telegram', 'boolean');
        $this->settings->set('telegram.chat_id', $chatId, 'telegram', 'string', true);
        if ($botToken !== '') {
            $this->settings->set('telegram.bot_token', $botToken, 'telegram', 'string', true);
        }

        return [];
    }

    public function boolValue(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function weekdayLabels(): array
    {
        return [
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            6 => 'Sobota',
            7 => 'Niedziela',
        ];
    }

    private function defaultLessonSchedule(): array
    {
        return [
            ['weekday' => 1, 'label' => 'Poniedziałek', 'start' => '17:00', 'end' => '18:00'],
            ['weekday' => 3, 'label' => 'Środa', 'start' => '19:00', 'end' => '20:00'],
        ];
    }

    private function normalizeLessonSchedule(array $items): array
    {
        $labels = $this->weekdayLabels();
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $weekday = (int) ($item['weekday'] ?? 0);
            $start = trim((string) ($item['start'] ?? ''));
            $end = trim((string) ($item['end'] ?? ''));

            if (!isset($labels[$weekday]) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $end <= $start) {
                continue;
            }

            $normalized[] = [
                'weekday' => $weekday,
                'label' => $labels[$weekday],
                'start' => $start,
                'end' => $end,
            ];
        }

        return $normalized !== [] ? $normalized : $this->defaultLessonSchedule();
    }

    private function normalizePhone(string $value): string
    {
        return FormatHelper::normalizePhone($value) ?? '';
    }

    private function uniqueSubscriptionSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 2;

        while ($this->settings->subscriptionSlugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = strtr($value, [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ż' => 'z',
            'ź' => 'z',
            'Ą' => 'a',
            'Ć' => 'c',
            'Ę' => 'e',
            'Ł' => 'l',
            'Ń' => 'n',
            'Ó' => 'o',
            'Ś' => 's',
            'Ż' => 'z',
            'Ź' => 'z',
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'abonament';
    }
}
