<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\FormatHelper;
use App\Repositories\NotificationRepository;
use App\Repositories\StudentRepository;
use DateTimeImmutable;
use Throwable;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly StudentRepository $students,
        private readonly StudentService $studentService,
        private readonly SettingsService $settings
    ) {
    }

    public function sync(): void
    {
        $today = new DateTimeImmutable('today');

        $this->syncZeroLessonNotifications();
        // Room booking reminders run independently via the scheduled CLI task.
    }

    public function unreadCount(): int
    {
        return $this->notifications->unreadCount();
    }

    public function recentUnread(int $limit = 6): array
    {
        return $this->notifications->recentUnread($limit);
    }

    private function syncZeroLessonNotifications(): void
    {
        foreach ($this->students->activeStudentsForNotifications() as $row) {
            try {
                $profile = $this->studentService->profile((int) $row['id']);
            } catch (Throwable) {
                continue;
            }

            $student = $profile['student'] ?? null;
            if (!is_array($student)) {
                continue;
            }

            $paidLessons = (int) ($student['paid_lessons_count'] ?? 0);
            $remainingLessons = (int) ($student['remaining_lessons'] ?? 0);
            if ($paidLessons <= 0 || $remainingLessons !== 0) {
                continue;
            }

            $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
            $nextPaymentDate = (string) ($student['next_payment_lesson_date'] ?? '');
            $periodStart = (string) ($student['current_period_start'] ?? '');
            $subscriptionName = (string) ($student['subscription_name'] ?? 'abonament');

            $message = $name . ' ma 0 zajęć w aktualnym abonamencie ' . $subscriptionName . '.';
            if ($nextPaymentDate !== '') {
                $message .= ' Następna płatność od: ' . FormatHelper::date($nextPaymentDate) . '.';
            }
            if ($periodStart !== '') {
                $message .= ' Abonament rozpoczęty: ' . FormatHelper::date($periodStart) . '.';
            }

            $this->createAndDeliver([
                'title' => 'Brak zajęć: ' . $name,
                'message' => $message,
                'type' => 'warning',
                'related_entity_type' => 'student_zero_lessons',
                'related_entity_id' => (int) $student['id'],
            ]);
        }
    }

    private function syncRoomBookingReminder(DateTimeImmutable $today): void
    {
        $tomorrow = $today->modify('+1 day');
        $weekday = (int) $tomorrow->format('N');
        $slots = $this->slotsForWeekday($weekday);

        if ($slots === []) {
            return;
        }

        $date = $tomorrow->format('Y-m-d');
        if ($this->notifications->hasRoomReservationForDate($date)) {
            return;
        }

        foreach ($slots as $slot) {
            $time = trim((string) ($slot['start'] ?? '') . '-' . (string) ($slot['end'] ?? ''), '-');
            $label = (string) ($slot['label'] ?? $this->weekdayName($weekday));
            $suffix = $time !== '' ? ' (' . $time . ')' : '';

            $this->createAndDeliver([
                'title' => 'Zarezerwuj salę: ' . FormatHelper::date($date),
                'message' => 'Jutro są zajęcia: ' . $label . $suffix . '. Sprawdź i zarezerwuj salę.',
                'type' => 'warning',
                'related_entity_type' => 'room_booking_reminder',
                'related_entity_id' => null,
            ]);
        }
    }

    private function createAndDeliver(array $data): void
    {
        if (!$this->notifications->createOnce($data)) {
            return;
        }

        try {
            (new TelegramService($this->settings))->sendMessage(
                '<b>' . htmlspecialchars((string) $data['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>' . "\n"
                . htmlspecialchars((string) $data['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        } catch (Throwable) {
        }
    }

    private function slotsForWeekday(int $weekday): array
    {
        $slots = [];
        foreach ($this->settings->lessonSchedule() as $slot) {
            if ((int) ($slot['weekday'] ?? 0) === $weekday) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    private function weekdayName(int $weekday): string
    {
        return $this->settings->weekdayLabels()[$weekday] ?? 'Dzień zajęć';
    }
}
