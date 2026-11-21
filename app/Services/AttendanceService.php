<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AttendanceRepository;

final class AttendanceService
{
    private const STATUSES = ['present', 'absent_notice', 'absent_no_notice', 'school_cancelled'];
    private const FREE_NOTICE_LIMIT = 2;

    public function __construct(private readonly AttendanceRepository $attendance)
    {
    }

    public function studentsForLesson(string $lessonDate): array
    {
        if (!$this->isValidDate($lessonDate)) {
            return [];
        }

        $students = $this->attendance->activeStudentsForDate($lessonDate);

        foreach ($students as &$student) {
            $student['attendance_status'] = $student['attendance_status'] ?: 'present';
            $student['counts_as_used_lesson'] = (int) ($student['counts_as_used_lesson'] ?? 1);
            $student['notice_number'] = $student['notice_number'] !== null ? (int) $student['notice_number'] : null;
            $student['attendance_notes'] = $student['attendance_notes'] ?: '';
            $student['absence_count'] = 0;

            $payment = $this->attendance->currentSubscriptionPaymentForStudent((int) $student['id'], $lessonDate);
            if ($payment !== null) {
                $student['absence_count'] = $this->attendance->absenceCountForPayment(
                    (int) $payment['id'],
                    (int) $student['id'],
                    $lessonDate
                );
            }
        }
        unset($student);

        return $students;
    }

    public function schoolCancelledDates(string $from, string $to): array
    {
        return $this->attendance->schoolCancelledDates($from, $to);
    }

    public function cancelLesson(string $lessonDate): array
    {
        $records = [];
        foreach ($this->studentsForLesson($lessonDate) as $student) {
            $records[(int) $student['id']] = [
                'status' => 'school_cancelled',
                'notes' => (string) ($student['attendance_notes'] ?? ''),
            ];
        }

        return $this->save($lessonDate, ['attendance' => $records]);
    }

    public function forStudent(int $studentId): array
    {
        return $studentId > 0 ? $this->attendance->forStudent($studentId) : [];
    }

    public function save(string $lessonDate, array $input, bool $allowPast = false): array
    {
        if (!$this->isValidDate($lessonDate)) {
            return ['Nieprawidłowa data zajęć.'];
        }

        $lessonDay = new \DateTimeImmutable($lessonDate);
        $today = new \DateTimeImmutable('today');
        if ($lessonDay < $today && !$allowPast) {
            return ['Obecność można zapisać tylko w dniu zajęć albo przed nim. Minione dni są zablokowane.'];
        }

        $students = $input['attendance'] ?? [];
        if (!is_array($students) || $students === []) {
            return ['Brak uczennic do zapisania obecności.'];
        }

        $records = [];
        foreach ($students as $studentId => $data) {
            if (!is_array($data)) {
                continue;
            }

            $status = (string) ($data['status'] ?? 'present');
            if (!in_array($status, self::STATUSES, true)) {
                return ['Nieprawidłowy status obecności.'];
            }

            $payment = $this->attendance->currentSubscriptionPaymentForStudent((int) $studentId, $lessonDate);
            $paymentId = $payment !== null ? (int) $payment['id'] : null;
            $noticeNumber = null;
            $countsAsUsedLesson = 1;

            if ($status === 'school_cancelled') {
                $countsAsUsedLesson = 0;
            } elseif ($status === 'absent_notice') {
                $noticeNumber = $paymentId !== null
                    ? $this->attendance->noticeCountForPayment($paymentId, (int) $studentId, $lessonDate) + 1
                    : 1;
                $countsAsUsedLesson = $noticeNumber > self::FREE_NOTICE_LIMIT ? 1 : 0;
            }

            $records[] = [
                'student_id' => (int) $studentId,
                'payment_id' => $paymentId,
                'status' => $status,
                'counts_as_used_lesson' => $countsAsUsedLesson,
                'notice_number' => $noticeNumber,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ];
        }

        if ($records === []) {
            return ['Brak poprawnych rekordów obecności.'];
        }

        $this->attendance->saveForDate($lessonDate, $records);

        return [];
    }

    public function markSingle(string $lessonDate, int $studentId, string $status, string $notes = '', bool $allowPast = false): array
    {
        if ($studentId <= 0) {
            return ['Nieprawidłowa uczennica.'];
        }

        return $this->save($lessonDate, [
            'attendance' => [
                $studentId => [
                    'status' => $status,
                    'notes' => $notes,
                ],
            ],
        ], $allowPast);
    }

    public function markSingleForAnyDate(string $lessonDate, int $studentId, string $status, string $notes = ''): array
    {
        if ($studentId <= 0) {
            return ['Nieprawidłowa uczennica.'];
        }

        $existing = $this->attendance->findForStudentDate($studentId, $lessonDate);
        if ($existing !== null && new \DateTimeImmutable($lessonDate) < new \DateTimeImmutable('today')) {
            return $this->updatePastRecord($studentId, $lessonDate, $status, $notes);
        }

        return $this->markSingle($lessonDate, $studentId, $status, $notes, true);
    }

    public function updatePastRecord(int $studentId, string $lessonDate, string $status, string $notes = ''): array
    {
        if ($studentId <= 0) {
            return ['Nieprawidłowa uczennica.'];
        }
        $existing = $this->attendance->findForStudentDate($studentId, $lessonDate);
        if ($existing === null) {
            return ['Nie znaleziono zapisu obecności do edycji.'];
        }

        $errors = $this->save($lessonDate, [
            'attendance' => [
                $studentId => ['status' => $status, 'notes' => $notes],
            ],
        ], true);

        if ($errors !== []) {
            return $errors;
        }

        $updated = $this->attendance->findForStudentDate($studentId, $lessonDate);
        $this->attendance->auditCorrection(
            $studentId,
            $lessonDate,
            [
                'status' => $existing['status'],
                'counts_as_used_lesson' => (int) $existing['counts_as_used_lesson'],
                'notice_number' => $existing['notice_number'] !== null ? (int) $existing['notice_number'] : null,
                'notes' => $existing['notes'],
            ],
            [
                'status' => $updated['status'] ?? $status,
                'counts_as_used_lesson' => (int) ($updated['counts_as_used_lesson'] ?? 1),
                'notice_number' => isset($updated['notice_number']) ? (int) $updated['notice_number'] : null,
                'notes' => $updated['notes'] ?? ($notes !== '' ? $notes : null),
            ]
        );

        return [];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'absent_notice' => 'Nieobecna z uprzedzeniem',
            'absent_no_notice' => 'Nieobecna bez uprzedzenia',
            'school_cancelled' => 'Odwołane przez szkołę',
            default => 'Obecna',
        };
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'absent_notice' => 'badge-warning',
            'absent_no_notice' => 'badge-danger',
            'school_cancelled' => 'badge-neutral',
            default => 'badge-success',
        };
    }

    private function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
