<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AttendanceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function activeStudentsForDate(string $lessonDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                s.id,
                s.first_name,
                s.last_name,
                s.photo_path,
                sg.name AS group_name,
                current_subscription.slug AS subscription_slug,
                ar.status AS attendance_status,
                ar.counts_as_used_lesson,
                ar.notice_number,
                ar.notes AS attendance_notes
             FROM students s
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             INNER JOIN payments current_payment
                ON current_payment.id = (
                    SELECT p2.id
                    FROM payments p2
                    WHERE p2.student_id = s.id
                      AND p2.category = 'subscription'
                      AND p2.status = 'completed'
                      AND p2.payment_date <= :payment_lesson_date
                    ORDER BY p2.payment_date DESC, p2.id DESC
                    LIMIT 1
                )
             INNER JOIN subscriptions current_subscription
                ON current_subscription.id = current_payment.subscription_id
             LEFT JOIN attendance_records ar
                ON ar.student_id = s.id
               AND ar.lesson_date = :lesson_date
             WHERE s.status = 'active'
               AND current_subscription.slug NOT LIKE 'indywidualne-%'
             ORDER BY s.last_name ASC, s.first_name ASC"
        );
        $stmt->bindValue(':lesson_date', $lessonDate);
        $stmt->bindValue(':payment_lesson_date', $lessonDate);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function schoolCancelledDates(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT lesson_date FROM attendance_records
             WHERE lesson_date BETWEEN :from AND :to
             GROUP BY lesson_date
             HAVING COUNT(*) > 0 AND SUM(status <> 'school_cancelled') = 0"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function forStudent(int $studentId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, payment_id, lesson_date, status, counts_as_used_lesson, notice_number, notes, recorded_at, created_at, updated_at
             FROM attendance_records
             WHERE student_id = :student_id
             ORDER BY lesson_date DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findForStudentDate(int $studentId, string $lessonDate): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM attendance_records WHERE student_id = :student_id AND lesson_date = :lesson_date LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId, ':lesson_date' => $lessonDate]);

        return $stmt->fetch() ?: null;
    }

    public function auditCorrection(int $studentId, string $lessonDate, array $oldValues, array $newValues): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO activity_logs
                (action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
             VALUES
                ('correct_attendance', 'student', :student_id, :description, :old_values, :new_values, :ip_address, :user_agent)"
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':description' => 'Korekta obecności z dnia ' . $lessonDate,
            ':old_values' => json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            ':new_values' => json_encode($newValues, JSON_UNESCAPED_UNICODE),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public function saveForDate(string $lessonDate, array $records): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO attendance_records (student_id, payment_id, lesson_date, status, counts_as_used_lesson, notice_number, notes, recorded_at)
                 VALUES (:student_id, :payment_id, :lesson_date, :status, :counts_as_used_lesson, :notice_number, :notes, NOW())
                 ON DUPLICATE KEY UPDATE
                    payment_id = VALUES(payment_id),
                    status = VALUES(status),
                    counts_as_used_lesson = VALUES(counts_as_used_lesson),
                    notice_number = VALUES(notice_number),
                    notes = VALUES(notes),
                    recorded_at = NOW(),
                    updated_at = CURRENT_TIMESTAMP"
            );

            $affectedPayments = [];
            foreach ($records as $record) {
                $existing = $this->pdo->prepare(
                    'SELECT payment_id FROM attendance_records WHERE student_id = :student_id AND lesson_date = :lesson_date LIMIT 1'
                );
                $existing->execute([':student_id' => (int) $record['student_id'], ':lesson_date' => $lessonDate]);
                $oldPaymentId = $existing->fetchColumn();
                if ($oldPaymentId !== false && $oldPaymentId !== null) {
                    $affectedPayments[(int) $record['student_id'] . ':' . (int) $oldPaymentId] = [(int) $record['student_id'], (int) $oldPaymentId];
                }

                $stmt->bindValue(':student_id', (int) $record['student_id'], PDO::PARAM_INT);
                $stmt->bindValue(':payment_id', $record['payment_id'], $record['payment_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':lesson_date', $lessonDate);
                $stmt->bindValue(':status', (string) $record['status']);
                $stmt->bindValue(':counts_as_used_lesson', (int) $record['counts_as_used_lesson'], PDO::PARAM_INT);
                $stmt->bindValue(':notice_number', $record['notice_number'], $record['notice_number'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':notes', $record['notes']);
                $stmt->execute();

                if ($record['payment_id'] !== null) {
                    $affectedPayments[(int) $record['student_id'] . ':' . (int) $record['payment_id']] = [(int) $record['student_id'], (int) $record['payment_id']];
                }
            }

            foreach ($affectedPayments as [$studentId, $paymentId]) {
                $this->recalculatePaymentAttendance($studentId, $paymentId);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function recalculatePaymentAttendance(int $studentId, int $paymentId): void
    {
        $select = $this->pdo->prepare(
            'SELECT id, status
             FROM attendance_records
             WHERE student_id = :student_id AND payment_id = :payment_id
             ORDER BY lesson_date ASC, id ASC
             FOR UPDATE'
        );
        $select->execute([':student_id' => $studentId, ':payment_id' => $paymentId]);
        $records = $select->fetchAll();

        $update = $this->pdo->prepare(
            'UPDATE attendance_records
             SET counts_as_used_lesson = :counts_as_used_lesson,
                 notice_number = :notice_number
             WHERE id = :id'
        );
        $noticeNumber = 0;

        foreach ($records as $record) {
            $status = (string) $record['status'];
            $countsAsUsed = 1;
            $currentNotice = null;

            if ($status === 'school_cancelled') {
                $countsAsUsed = 0;
            } elseif ($status === 'absent_notice') {
                $noticeNumber++;
                $currentNotice = $noticeNumber;
                $countsAsUsed = $noticeNumber > 2 ? 1 : 0;
            }

            $update->bindValue(':counts_as_used_lesson', $countsAsUsed, PDO::PARAM_INT);
            $update->bindValue(':notice_number', $currentNotice, $currentNotice === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $update->bindValue(':id', (int) $record['id'], PDO::PARAM_INT);
            $update->execute();
        }
    }

    public function currentSubscriptionPaymentForStudent(int $studentId, string $lessonDate): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.payment_date, sub.lessons_count
             FROM payments p
             INNER JOIN subscriptions sub ON sub.id = p.subscription_id
             WHERE p.student_id = :student_id
               AND p.category = 'subscription'
               AND p.status = 'completed'
               AND p.payment_date <= :lesson_date
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT 1"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':lesson_date', $lessonDate);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function noticeCountForPayment(int $paymentId, int $studentId, string $lessonDate): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM attendance_records
             WHERE payment_id = :payment_id
               AND student_id = :student_id
               AND status = 'absent_notice'
               AND lesson_date < :lesson_date"
        );
        $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':lesson_date', $lessonDate);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function absenceCountForPayment(int $paymentId, int $studentId, string $lessonDate): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM attendance_records
             WHERE payment_id = :payment_id
               AND student_id = :student_id
               AND status IN ('absent_notice', 'absent_no_notice')
               AND lesson_date <= :lesson_date"
        );
        $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':lesson_date', $lessonDate);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
