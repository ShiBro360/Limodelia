<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/bootstrap.php';
date_default_timezone_set('Europe/Warsaw');
$options = getopt('', ['dry-run', 'date:']);
if (isset($options['date']) && !isset($options['dry-run'])) {
    fwrite(STDERR, "Historical dates require --dry-run.\n"); exit(1);
}
try {
    $settings = new \App\Services\SettingsService(new \App\Repositories\SettingsRepository(db()));
    $service = new \App\Services\RoomBookingReminderService(db(), $settings);
    echo json_encode($service->run(new DateTimeImmutable($options['date'] ?? 'today'), isset($options['dry-run'])), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Room booking reminder failed (' . get_class($error) . "). Pending delivery will be retried.\n");
    exit(1);
}
