<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\DashboardRepository;
use Throwable;

final class TelegramAttendanceBotService
{
    private const CALLBACK_PREFIX = 'att';

    private const STATUS_MAP = [
        'present' => 'present',
        'notice' => 'absent_notice',
        'absent' => 'absent_no_notice',
        'cancelled' => 'school_cancelled',
    ];

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly SettingsService $settings,
        private readonly AttendanceService $attendance,
        private readonly StudentService $students,
        private readonly NotificationRepository $notifications,
        private readonly DashboardRepository $dashboard
    ) {
    }

    public function runOnce(int $timeout = 20): int
    {
        $offset = $this->settings->telegramLastUpdateId();
        $updates = $this->telegram->getUpdates($offset > 0 ? $offset + 1 : 0, $timeout);
        $processed = 0;

        foreach ($updates as $update) {
            $updateId = (int) ($update['update_id'] ?? 0);
            if ($updateId <= 0) {
                continue;
            }

            try {
                $this->handleUpdate($update);
            } catch (Throwable) {
                // Keep polling alive even if one Telegram update is malformed.
            }

            $this->settings->saveTelegramLastUpdateId($updateId);
            $processed++;
        }

        return $processed;
    }

    public function sendAttendanceForm(string $lessonDate): int
    {
        if (!$this->isValidDate($lessonDate)) {
            $this->telegram->sendMessage('Nieprawidłowa data. Użyj formatu YYYY-MM-DD.');
            return 0;
        }

        $students = $this->attendance->studentsForLesson($lessonDate);
        if ($students === []) {
            $this->telegram->sendMessage(
                '<b>Obecność na zajęciach</b>' . "\n"
                . $this->escape($this->formatDate($lessonDate)) . "\n\n"
                . 'Brak aktywnych uczennic do oznaczenia.'
            );
            return 0;
        }

        $this->telegram->sendMessage(
            $this->rosterText($lessonDate, $students),
            $this->rosterKeyboard($lessonDate, $students)
        );

        return count($students);
    }

    private function handleUpdate(array $update): void
    {
        if ((new TelegramStudentRegistrationService(db(), $this->telegram, $this->students))->handle($update)) { return; }
        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if (!$this->isAllowedChat($chatId)) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return;
        }

        if (preg_match('/^\/(?:start|help)(?:@[A-Za-z0-9_]+)?$/u', $text) === 1) {
            $this->telegram->sendMessageToChat($chatId, $this->helpText());
            return;
        }

        if (preg_match('/^\/(?:today|dzisiaj)(?:@[A-Za-z0-9_]+)?$/u', $text) === 1) {
            $this->sendAttendanceForm((new \DateTimeImmutable('today'))->format('Y-m-d'));
            return;
        }

        if (preg_match('/^\/(?:abonament|saldo)(?:@[A-Za-z0-9_]+)?$/u', $text) === 1) {
            $this->showBalanceList($chatId, 0);
            return;
        }

        if (preg_match('/^\/(?:obecnosc|obecność|zajecia|zajęcia)(?:@[A-Za-z0-9_]+)?(?:\s+(\d{4}-\d{2}-\d{2}))?$/u', $text, $matches) === 1) {
            $date = (string) ($matches[1] ?? '');
            if ($date !== '') {
                $this->sendAttendanceForm($date);
            } else {
                $this->showAttendanceMonth($chatId, 0, (new \DateTimeImmutable('today'))->format('Y-m'));
            }
        }
    }

    private function handleCallbackQuery(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
        $chatId = (string) ($message['chat']['id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);

        if (!$this->isAllowedChat($chatId)) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, 'Ten czat nie jest połączony z CRM.');
            }
            return;
        }

        $callbackData = (string) ($callback['data'] ?? '');
        if (str_starts_with($callbackData, 'bal|')) {
            $this->handleBalanceCallback($callbackId, $chatId, $messageId, $callbackData);
            return;
        }
        if (str_starts_with($callbackData, 'cal|')) {
            $this->handleCalendarCallback($callbackId, $chatId, $messageId, $callbackData);
            return;
        }

        $parts = explode('|', $callbackData);
        if (count($parts) !== 4 || $parts[0] !== self::CALLBACK_PREFIX) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, 'Nieprawidłowa akcja.');
            }
            return;
        }

        [, $lessonDate, $studentIdRaw, $action] = $parts;
        $studentId = (int) $studentIdRaw;

        if (!$this->isValidDate($lessonDate)) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, 'Nieprawidłowe dane obecności.');
            }
            return;
        }

        if ($action === 'list') {
            $this->showRoster($chatId, $messageId, $lessonDate);
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId);
            }
            return;
        }

        if ($action === 'open' && $studentId > 0) {
            $student = $this->studentForDate($lessonDate, $studentId);
            if ($student === null) {
                if ($callbackId !== '') {
                    $this->telegram->answerCallbackQuery($callbackId, 'Nie znaleziono uczennicy.');
                }
                return;
            }

            $this->showStudent($chatId, $messageId, $lessonDate, $student);
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId);
            }
            return;
        }

        $status = self::STATUS_MAP[$action] ?? null;
        if ($status === null || $studentId <= 0) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, 'Nieprawidłowe dane obecności.');
            }
            return;
        }

        $student = $this->studentForDate($lessonDate, $studentId);
        $errors = $this->attendance->markSingleForAnyDate($lessonDate, $studentId, $status);

        if ($errors !== []) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, $errors[0]);
            }
            return;
        }

        if ($callbackId !== '') {
            $this->telegram->answerCallbackQuery($callbackId, 'Zapisano obecność.');
        }

        if ($messageId > 0 && $student !== null) {
            $this->showRoster($chatId, $messageId, $lessonDate);
        }
    }

    private function attendanceKeyboard(string $lessonDate, int $studentId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Obecna', 'callback_data' => self::CALLBACK_PREFIX . '|' . $lessonDate . '|' . $studentId . '|present'],
                    ['text' => '🟡 Uprzedziła', 'callback_data' => self::CALLBACK_PREFIX . '|' . $lessonDate . '|' . $studentId . '|notice'],
                ],
                [
                    ['text' => '🔴 Bez uprzedzenia', 'callback_data' => self::CALLBACK_PREFIX . '|' . $lessonDate . '|' . $studentId . '|absent'],
                    ['text' => '⚪ Odwołane', 'callback_data' => self::CALLBACK_PREFIX . '|' . $lessonDate . '|' . $studentId . '|cancelled'],
                ],
                [
                    ['text' => '← Wróć do listy', 'callback_data' => self::CALLBACK_PREFIX . '|' . $lessonDate . '|0|list'],
                ],
            ],
        ];
    }

    private function showRoster(string $chatId, int $messageId, string $lessonDate): void
    {
        if ($messageId <= 0) {
            return;
        }

        $students = $this->attendance->studentsForLesson($lessonDate);
        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            $this->rosterText($lessonDate, $students),
            $this->rosterKeyboard($lessonDate, $students)
        );
    }

    private function showStudent(string $chatId, int $messageId, string $lessonDate, array $student): void
    {
        if ($messageId <= 0) {
            return;
        }

        $studentId = (int) ($student['id'] ?? 0);
        $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        $group = (string) ($student['group_name'] ?? 'Bez grupy');
        $status = (string) ($student['attendance_status'] ?? 'present');
        $absenceCount = (int) ($student['absence_count'] ?? 0);
        $used = (int) ($student['counts_as_used_lesson'] ?? 1) === 1 ? 'tak' : 'nie';

        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            '<b>Obecność na zajęciach</b>' . "\n"
            . $this->escape($this->formatDate($lessonDate)) . "\n\n"
            . '<b>' . $this->escape($name) . '</b>' . "\n"
            . $this->escape($group) . "\n"
            . 'Status: <b>' . $this->escape(AttendanceService::statusLabel($status)) . '</b>' . "\n"
            . 'Nieobecności w abonamencie: <b>' . $absenceCount . '</b>' . "\n"
            . 'Zużywa zajęcie: <b>' . $used . '</b>',
            $this->attendanceKeyboard($lessonDate, $studentId)
        );
    }

    private function rosterText(string $lessonDate, array $students): string
    {
        $lines = ['<b>Obecność na zajęciach</b>', $this->escape($this->formatDate($lessonDate)), ''];

        foreach ($students as $index => $student) {
            $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
            $status = (string) ($student['attendance_status'] ?? 'present');
            $lines[] = ($index + 1) . '. ' . $this->statusIcon($status) . ' <b>' . $this->escape($name) . '</b>'
                . ' — ' . $this->escape(AttendanceService::statusLabel($status));
        }

        $lines[] = '';
        $lines[] = 'Wybierz uczennicę, aby zmienić jej status.';

        return implode("\n", $lines);
    }

    private function rosterKeyboard(string $lessonDate, array $students): array
    {
        $rows = [];
        $row = [];

        foreach ($students as $index => $student) {
            $studentId = (int) ($student['id'] ?? 0);
            $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
            $status = (string) ($student['attendance_status'] ?? 'present');
            $row[] = [
                'text' => ($index + 1) . '. ' . $this->statusIcon($status) . ' ' . $this->shortName($name),
                'callback_data' => self::CALLBACK_PREFIX . '|' . $lessonDate . '|' . $studentId . '|open',
            ];

            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }

        if ($row !== []) {
            $rows[] = $row;
        }

        $rows[] = [
            ['text' => '📅 Wybór daty', 'callback_data' => 'cal|' . substr($lessonDate, 0, 7) . '|month'],
            ['text' => '🎟 Pozostałe zajęcia', 'callback_data' => 'bal|0|list'],
        ];

        return ['inline_keyboard' => $rows];
    }

    private function handleCalendarCallback(string $callbackId, string $chatId, int $messageId, string $callbackData): void
    {
        $parts = array_pad(explode('|', $callbackData), 3, '');
        [, $value, $action] = $parts;

        if ($action === 'month' && preg_match('/^\d{4}-\d{2}$/', $value)) {
            $this->showAttendanceMonth($chatId, $messageId, $value);
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($action === 'open' && $this->isValidDate($value)) {
            $this->showRoster($chatId, $messageId, $value);
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        $this->telegram->answerCallbackQuery($callbackId, 'Nieprawidłowa data zajęć.');
    }

    private function showAttendanceMonth(string $chatId, int $messageId, string $month): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return;
        }

        $monthDate = new \DateTimeImmutable($month . '-01');
        $from = $monthDate->format('Y-m-01');
        $to = $monthDate->format('Y-m-t');
        $dates = $this->dashboard->paidRentalLessonDates($from, $to);
        $text = '<b>Wybierz opłacony termin zajęć</b>' . "\n"
            . $this->escape($this->monthLabel($monthDate)) . "\n\n";

        if ($dates === []) {
            $text .= 'Brak opłaconych terminów w tym miesiącu.';
        } else {
            $text .= 'Naciśnij datę, aby otworzyć listę obecności.';
        }

        $keyboard = $this->attendanceMonthKeyboard($monthDate, $dates);
        if ($messageId > 0) {
            $this->telegram->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->telegram->sendMessageToChat($chatId, $text, $keyboard);
        }
    }

    private function attendanceMonthKeyboard(\DateTimeImmutable $monthDate, array $dates): array
    {
        $rows = [];
        $row = [];
        $weekdayLabels = [1 => 'Pn', 2 => 'Wt', 3 => 'Śr', 4 => 'Cz', 5 => 'Pt', 6 => 'Sb', 7 => 'Nd'];

        foreach ($dates as $date) {
            $day = new \DateTimeImmutable($date);
            $row[] = [
                'text' => $day->format('d.m') . ' ' . $weekdayLabels[(int) $day->format('N')],
                'callback_data' => 'cal|' . $date . '|open',
            ];
            if (count($row) === 3) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        $previous = $monthDate->modify('-1 month')->format('Y-m');
        $next = $monthDate->modify('+1 month')->format('Y-m');
        $rows[] = [
            ['text' => '← ' . $this->shortMonthLabel($monthDate->modify('-1 month')), 'callback_data' => 'cal|' . $previous . '|month'],
            ['text' => $this->shortMonthLabel($monthDate->modify('+1 month')) . ' →', 'callback_data' => 'cal|' . $next . '|month'],
        ];

        return ['inline_keyboard' => $rows];
    }

    private function monthLabel(\DateTimeImmutable $date): string
    {
        $months = [1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień', 5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień', 9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień'];

        return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    private function shortMonthLabel(\DateTimeImmutable $date): string
    {
        $months = [1 => 'Sty', 2 => 'Lut', 3 => 'Mar', 4 => 'Kwi', 5 => 'Maj', 6 => 'Cze', 7 => 'Lip', 8 => 'Sie', 9 => 'Wrz', 10 => 'Paź', 11 => 'Lis', 12 => 'Gru'];

        return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    private function handleBalanceCallback(string $callbackId, string $chatId, int $messageId, string $callbackData): void
    {
        $parts = array_pad(explode('|', $callbackData), 4, '');
        [, $studentIdRaw, $action, $valueRaw] = $parts;
        $studentId = (int) $studentIdRaw;

        if ($action === 'list') {
            $this->showBalanceList($chatId, $messageId);
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($action === 'open' && $studentId > 0) {
            if (!$this->showBalanceStudent($chatId, $messageId, $studentId)) {
                $this->telegram->answerCallbackQuery($callbackId, 'Nie znaleziono aktywnego abonamentu.');
                return;
            }
            $this->telegram->answerCallbackQuery($callbackId);
            return;
        }

        if ($action !== 'set' || $studentId <= 0 || !preg_match('/^\d+$/', $valueRaw)) {
            $this->telegram->answerCallbackQuery($callbackId, 'Nieprawidłowa zmiana abonamentu.');
            return;
        }

        $profile = $this->students->profile($studentId);
        $student = $profile['student'] ?? null;
        if (!is_array($student)) {
            $this->telegram->answerCallbackQuery($callbackId, 'Nie znaleziono uczennicy.');
            return;
        }

        $previous = (int) ($student['remaining_lessons'] ?? 0);
        $remaining = (int) $valueRaw;
        $errors = $this->students->adjustRemainingLessons(
            $studentId,
            $remaining,
            'Zmiana liczby pozostałych zajęć przez Telegram.'
        );

        if ($errors !== []) {
            $this->telegram->answerCallbackQuery($callbackId, $errors[0]);
            return;
        }

        $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        $this->notifications->createOnce([
            'title' => 'Korekta abonamentu: ' . $name,
            'message' => 'Przez Telegram zmieniono liczbę pozostałych zajęć z ' . $previous . ' na ' . $remaining . '.',
            'type' => 'info',
            'related_entity_type' => 'student_remaining_lessons',
            'related_entity_id' => $studentId,
        ]);

        $this->telegram->answerCallbackQuery($callbackId, 'Zmieniono liczbę zajęć.');
        $this->showBalanceStudent($chatId, $messageId, $studentId);
    }

    private function showBalanceList(string $chatId, int $messageId): void
    {
        $students = $this->balanceStudents();
        $text = '<b>Pozostałe zajęcia w abonamencie</b>' . "\n\n";

        if ($students === []) {
            $text .= 'Brak uczennic z aktywnym opłaconym abonamentem.';
        } else {
            foreach ($students as $index => $student) {
                $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
                $text .= ($index + 1) . '. <b>' . $this->escape($name) . '</b> — '
                    . (int) ($student['remaining_lessons'] ?? 0) . '/' . (int) ($student['paid_lessons_count'] ?? 0) . "\n";
            }
            $text .= "\nWybierz uczennicę, aby zmienić liczbę zajęć.";
        }

        $keyboard = $this->balanceListKeyboard($students);
        if ($messageId > 0) {
            $this->telegram->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->telegram->sendMessageToChat($chatId, $text, $keyboard);
        }
    }

    private function showBalanceStudent(string $chatId, int $messageId, int $studentId): bool
    {
        $profile = $this->students->profile($studentId);
        $student = $profile['student'] ?? null;
        if (!is_array($student) || !(bool) ($student['current_subscription_paid'] ?? false)) {
            return false;
        }

        $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        $remaining = (int) ($student['remaining_lessons'] ?? 0);
        $paid = (int) ($student['paid_lessons_count'] ?? 0);
        $subscription = (string) ($student['subscription_name'] ?? 'Abonament');
        $text = '<b>Korekta pozostałych zajęć</b>' . "\n\n"
            . '<b>' . $this->escape($name) . '</b>' . "\n"
            . $this->escape($subscription) . "\n"
            . 'Pozostało: <b>' . $remaining . ' z ' . $paid . '</b>' . "\n\n"
            . 'Wybierz nową liczbę:';

        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            $text,
            $this->balanceValueKeyboard($studentId, $remaining, $paid)
        );

        return true;
    }

    private function balanceStudents(): array
    {
        $result = $this->students->paginated($this->students->filters([
            'status' => 'active',
            'sort' => 'name',
            'page' => 1,
            'per_page' => 50,
        ]));

        return array_values(array_filter(
            $result['students'] ?? [],
            static fn (array $student): bool => (bool) ($student['current_subscription_paid'] ?? false)
        ));
    }

    private function balanceListKeyboard(array $students): array
    {
        $rows = [];
        $row = [];
        foreach ($students as $index => $student) {
            $name = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
            $row[] = [
                'text' => ($index + 1) . '. ' . $this->shortName($name),
                'callback_data' => 'bal|' . (int) $student['id'] . '|open',
            ];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        return ['inline_keyboard' => $rows];
    }

    private function balanceValueKeyboard(int $studentId, int $current, int $maximum): array
    {
        $rows = [];
        $row = [];
        for ($value = 0; $value <= $maximum; $value++) {
            $row[] = [
                'text' => ($value === $current ? '✓ ' : '') . $value,
                'callback_data' => 'bal|' . $studentId . '|set|' . $value,
            ];
            if (count($row) === 5) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [['text' => '← Wróć do listy', 'callback_data' => 'bal|0|list']];

        return ['inline_keyboard' => $rows];
    }

    private function statusIcon(string $status): string
    {
        return match ($status) {
            'absent_notice' => '🟡',
            'absent_no_notice' => '🔴',
            'school_cancelled' => '⚪',
            default => '✅',
        };
    }

    private function shortName(string $name): string
    {
        return mb_strlen($name) > 20 ? mb_substr($name, 0, 19) . '…' : $name;
    }

    private function studentForDate(string $lessonDate, int $studentId): ?array
    {
        foreach ($this->attendance->studentsForLesson($lessonDate) as $student) {
            if ((int) ($student['id'] ?? 0) === $studentId) {
                return $student;
            }
        }

        return null;
    }

    private function isAllowedChat(string $chatId): bool
    {
        return $chatId !== '' && $chatId === $this->telegram->chatId();
    }

    private function helpText(): string
    {
        return '<b>Limodelia CRM</b>' . "\n"
            . 'Komendy:' . "\n"
            . '/obecnosc - wybór opłaconego terminu' . "\n"
            . '/obecnosc YYYY-MM-DD - lista obecności na wybrany dzień' . "\n"
            . '/abonament - zmiana liczby pozostałych zajęć' . "\n"
            . '/nowa - dodaj uczennicę, grupę i abonament' . "\n"
            . '/anuluj - anuluj dodawanie uczennicy' . "\n"
            . '/dzisiaj - szybka lista na dziś';
    }

    private function formatDate(string $date): string
    {
        return (new \DateTimeImmutable($date))->format('d.m.Y');
    }

    private function isValidDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
