<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use App\Repositories\AttendanceRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\StudentRepository;
use App\Services\AttendanceService;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\StudentService;
use App\Services\TelegramAttendanceBotService;
use App\Services\TelegramService;
use App\Validators\StudentValidator;

$lockHandle = fopen(dirname(__DIR__) . '/storage/telegram_attendance_bot.lock', 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "Nie mozna utworzyc blokady procesu Telegram.\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Limodelia Telegram attendance bot is already running.\n";
    exit(0);
}

$pdo = db();
$settingsRepository = new SettingsRepository($pdo);
$settingsService = new SettingsService($settingsRepository);
$attendanceService = new AttendanceService(new AttendanceRepository($pdo));
$studentService = new StudentService(
    new StudentRepository($pdo),
    new StudentValidator(),
    new StorageService($settingsService),
    $settingsService->lessonSchedule()
);
$telegramService = new TelegramService($settingsService);
$bot = new TelegramAttendanceBotService(
    $telegramService,
    $settingsService,
    $attendanceService,
    $studentService,
    new NotificationRepository($pdo),
    new DashboardRepository($pdo)
);

$options = getopt('', ['once', 'send-today', 'date::', 'help']);

if (isset($options['help'])) {
    echo "Limodelia Telegram attendance bot\n";
    echo "Usage:\n";
    echo "  php scripts/telegram_attendance_bot.php\n";
    echo "  php scripts/telegram_attendance_bot.php --once\n";
    echo "  php scripts/telegram_attendance_bot.php --send-today\n";
    echo "  php scripts/telegram_attendance_bot.php --date=YYYY-MM-DD\n";
    exit(0);
}

if (!$telegramService->isConfigured()) {
    fwrite(STDERR, "Telegram nie jest skonfigurowany. Wpisz token i chat ID w Ustawieniach.\n");
    exit(1);
}

$telegramService->deleteWebhook();
$telegramService->setCommands([
    ['command' => 'nowa', 'description' => 'Dodaj uczennicę, grupę i abonament'],
    ['command' => 'anuluj', 'description' => 'Anuluj dodawanie uczennicy'],
    ['command' => 'obecnosc', 'description' => 'Wybierz opłacony termin zajęć'],
    ['command' => 'dzisiaj', 'description' => 'Lista obecności na dziś'],
    ['command' => 'abonament', 'description' => 'Zmień liczbę pozostałych zajęć'],
    ['command' => 'help', 'description' => 'Pokaż dostępne komendy'],
]);

$date = (string) ($options['date'] ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
if (isset($options['send-today']) || isset($options['date'])) {
    $sent = $bot->sendAttendanceForm($date);
    echo "Wysłano formularze obecności: {$sent}\n";
    exit(0);
}

if (isset($options['once'])) {
    $processed = $bot->runOnce(1);
    echo "Przetworzone aktualizacje: {$processed}\n";
    exit(0);
}

echo "Limodelia Telegram attendance bot started. Press Ctrl+C to stop.\n";
while (true) {
    try {
        $processed = $bot->runOnce(25);
        if ($processed > 0) {
            echo date('Y-m-d H:i:s') . " Updates: {$processed}\n";
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . $exception->getMessage() . PHP_EOL);
        sleep(5);
    }
}
