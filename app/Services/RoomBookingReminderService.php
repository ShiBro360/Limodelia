<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class RoomBookingReminderService
{
    public function __construct(private readonly PDO $pdo, private readonly SettingsService $settings) {}

    public static function plan(DateTimeImmutable $today, array $schedule): ?array
    {
        $booking = $today;
        $nextMonth = $booking->modify('first day of next month');
        $lesson = $nextMonth->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), min((int) $booking->format('j'), (int) $nextMonth->format('t')));
        $slots = array_values(array_filter($schedule, static fn (array $slot): bool => (int) ($slot['weekday'] ?? 0) === (int) $lesson->format('N')));
        if ($slots === []) { return null; }
        return ['booking_date' => $booking->format('Y-m-d'), 'lesson_date' => $lesson->format('Y-m-d'), 'slots' => $slots];
    }

    public function run(DateTimeImmutable $today, bool $dryRun = false): array
    {
        $plan = self::plan($today, $this->settings->lessonSchedule());
        if ($plan === null) { return ['status' => 'no_lesson']; }
        $notifications = new NotificationRepository($this->pdo);
        if ($notifications->hasRoomReservationForDate($plan['lesson_date'])) {
            return $plan + ['status' => 'already_reserved'];
        }
        if ($dryRun) { return $plan + ['status' => 'would_remind']; }
        if (!(int) $this->pdo->query("SELECT GET_LOCK('limodelia_room_booking_reminder', 0)")->fetchColumn()) {
            return ['status' => 'locked'];
        }
        try {
            $stmt = $this->pdo->prepare('SELECT sent_at FROM room_booking_reminders WHERE lesson_date = ?');
            $stmt->execute([$plan['lesson_date']]);
            $sentAt = $stmt->fetchColumn();
            if ($sentAt !== false && $sentAt !== null) { return $plan + ['status' => 'already_sent']; }
            $times = implode(', ', array_map(static fn (array $slot): string => ($slot['start'] ?? '') . '-' . ($slot['end'] ?? ''), $plan['slots']));
            $title = 'Zarezerwuj salę na ' . (new DateTimeImmutable($plan['lesson_date']))->format('d.m.Y');
            $message = 'Dzisiaj (' . (new DateTimeImmutable($plan['booking_date']))->format('d.m.Y') . ') zarezerwuj salę na zajęcia ' . (new DateTimeImmutable($plan['lesson_date']))->format('d.m.Y') . ' (' . $times . ').';
            $notifications->createOnce(['title' => $title, 'message' => $message, 'type' => 'warning', 'related_entity_type' => 'room_booking_reminder', 'related_entity_id' => null]);
            $this->pdo->prepare('INSERT IGNORE INTO room_booking_reminders (lesson_date) VALUES (?)')->execute([$plan['lesson_date']]);
            $telegram = new TelegramService($this->settings);
            if (!$telegram->isConfigured()) { throw new RuntimeException('Telegram is not configured; reminder remains pending.'); }
            $telegram->sendMessage('<b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b>' . "\n" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            $this->pdo->prepare('UPDATE room_booking_reminders SET sent_at = NOW() WHERE lesson_date = ?')->execute([$plan['lesson_date']]);
            return $plan + ['status' => 'sent'];
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('limodelia_room_booking_reminder')");
        }
    }
}
