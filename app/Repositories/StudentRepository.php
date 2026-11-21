<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function list(array $filters): array
    {
        [$whereSql, $params] = $this->buildListWhere($filters);
        $orderBy = match ($filters['sort']) {
            'name' => 's.last_name ASC, s.first_name ASC',
            'end_date' => 'cs.end_date ASC, s.last_name ASC',
            'status' => 's.status ASC, s.last_name ASC',
            default => 's.created_at DESC, s.id DESC',
        };

        $stmt = $this->pdo->prepare(
            "SELECT
                s.*,
                TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) AS age,
                sg.name AS group_name,
                sub.name AS subscription_name,
                sub.price AS subscription_price,
                cs.end_date AS subscription_end_date,
                cs.subscription_id AS current_subscription_id,
                (
                    SELECT p.payment_date
                    FROM payments p
                    WHERE p.student_id = s.id AND p.status <> 'cancelled'
                    ORDER BY p.payment_date DESC, p.id DESC
                    LIMIT 1
                ) AS last_payment_date,
                (
                    SELECT p.amount
                    FROM payments p
                    WHERE p.student_id = s.id AND p.status <> 'cancelled'
                    ORDER BY p.payment_date DESC, p.id DESC
                    LIMIT 1
                ) AS last_payment_amount,
                (
                    SELECT p.currency
                    FROM payments p
                    WHERE p.student_id = s.id AND p.status <> 'cancelled'
                    ORDER BY p.payment_date DESC, p.id DESC
                    LIMIT 1
                ) AS last_payment_currency
             FROM students s
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             {$this->currentSubscriptionJoin()}
             {$whereSql}
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset"
        );

        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $filters['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($filters['page'] - 1) * $filters['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(array $filters): int
    {
        [$whereSql, $params] = $this->buildListWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students s {$this->currentSubscriptionJoin()} {$whereSql}");
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findForEdit(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, cs.subscription_id
             FROM students s
             {$this->currentSubscriptionJoin()}
             WHERE s.id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function findProfile(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                s.*,
                TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) AS age,
                sg.name AS group_name,
                sub.name AS subscription_name,
                sub.price AS subscription_price,
                sub.currency AS subscription_currency,
                sub.lessons_count,
                cs.start_date AS subscription_start_date,
                cs.end_date AS subscription_end_date
             FROM students s
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             {$this->currentSubscriptionJoin()}
             WHERE s.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function payments(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.payment_date, p.period_start, p.period_end, p.amount, p.currency, p.status, p.comment,
                    sub.name AS subscription_name, sub.lessons_count, pm.name AS payment_method
             FROM payments p
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
             WHERE p.student_id = :student_id
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT 20'
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function currentCompletedSubscriptionPayment(int $studentId, string $date): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                p.id,
                p.payment_date,
                p.period_start,
                p.period_end,
                p.subscription_id,
                p.amount,
                p.currency,
                p.remaining_lessons_override,
                p.remaining_lessons_override_used_baseline,
                sub.name AS subscription_name,
                sub.lessons_count,
                sub.price AS subscription_price,
                sub.currency AS subscription_currency
             FROM payments p
             INNER JOIN subscriptions sub ON sub.id = p.subscription_id
             WHERE p.student_id = :student_id
               AND p.category = 'subscription'
               AND p.status = 'completed'
               AND p.payment_date <= :payment_date_start
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT 1"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':payment_date_start', $date);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function subscriptionHistory(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM (
                SELECT
                    p.payment_date AS start_date,
                    NULL AS end_date,
                    p.status,
                    COALESCE(sub.name, 'Brak abonamentu') AS name,
                    p.amount AS price,
                    p.currency,
                    0 AS source_order,
                    p.id AS sort_id
                FROM payments p
                LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
                WHERE p.student_id = :student_id_payments
                  AND p.category = 'subscription'
                  AND p.status <> 'cancelled'

                UNION ALL

                SELECT
                    ss.start_date,
                    ss.end_date,
                    ss.status,
                    COALESCE(sub.name, 'Brak abonamentu') AS name,
                    sub.price,
                    sub.currency,
                    1 AS source_order,
                    ss.id AS sort_id
                FROM student_subscriptions ss
                LEFT JOIN subscriptions sub ON sub.id = ss.subscription_id
                WHERE ss.student_id = :student_id_subscriptions
                  AND NOT EXISTS (
                      SELECT 1
                      FROM payments p2
                      WHERE p2.student_id = ss.student_id
                        AND p2.category = 'subscription'
                        AND p2.status <> 'cancelled'
                  )
             ) history
             ORDER BY start_date DESC, source_order ASC, sort_id DESC"
        );
        $stmt->bindValue(':student_id_payments', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id_subscriptions', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function attendance(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, payment_id, lesson_date, status, counts_as_used_lesson, notice_number, notes
             FROM attendance_records
             WHERE student_id = :student_id
             ORDER BY lesson_date DESC, id DESC
             LIMIT 30'
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function attendanceForPayment(int $paymentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT lesson_date, status, counts_as_used_lesson, notice_number
             FROM attendance_records
             WHERE payment_id = :payment_id
             ORDER BY lesson_date ASC, id ASC'
        );
        $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function setRemainingLessonsOverride(
        int $studentId,
        int $paymentId,
        int $previousRemaining,
        int $remainingLessons,
        int $usedBaseline,
        string $reason
    ): void {
        $this->pdo->beginTransaction();

        try {
            $update = $this->pdo->prepare(
                'UPDATE payments
                 SET remaining_lessons_override = :remaining_lessons,
                     remaining_lessons_override_used_baseline = :used_baseline
                 WHERE id = :payment_id
                   AND student_id = :student_id
                   AND category = \'subscription\'
                   AND status = \'completed\''
            );
            $update->execute([
                ':remaining_lessons' => $remainingLessons,
                ':used_baseline' => $usedBaseline,
                ':payment_id' => $paymentId,
                ':student_id' => $studentId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('Nie znaleziono aktywnej płatności abonamentowej.');
            }

            $log = $this->pdo->prepare(
                "INSERT INTO activity_logs
                    (action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
                 VALUES
                    ('adjust_remaining_lessons', 'student', :student_id, :description, :old_values, :new_values, :ip_address, :user_agent)"
            );
            $log->execute([
                ':student_id' => $studentId,
                ':description' => $reason,
                ':old_values' => json_encode(['remaining_lessons' => $previousRemaining], JSON_UNESCAPED_UNICODE),
                ':new_values' => json_encode([
                    'remaining_lessons' => $remainingLessons,
                    'payment_id' => $paymentId,
                    'used_baseline' => $usedBaseline,
                ], JSON_UNESCAPED_UNICODE),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function activeForPaymentSelect(?string $periodMonth = null): array
    {
        $paymentFilter = '';

        if ($periodMonth !== null && preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            $paymentFilter = "
                AND NOT EXISTS (
                    SELECT 1
                    FROM payments p
                    WHERE p.student_id = s.id
                      AND p.status <> 'cancelled'
                      AND DATE_FORMAT(p.period_start, '%Y-%m') = :period_month
                )";
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                s.id,
                s.first_name,
                s.last_name,
                s.phone,
                s.telegram_username,
                sg.name AS group_name,
                cs.subscription_id,
                sub.name AS subscription_name,
                sub.price AS subscription_price
             FROM students s
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             {$this->currentSubscriptionJoin()}
             WHERE s.status = 'active'
             {$paymentFilter}
             ORDER BY s.last_name ASC, s.first_name ASC"
        );
        if ($paymentFilter !== '') {
            $stmt->bindValue(':period_month', $periodMonth);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function activeStudentsForNotifications(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, first_name, last_name
             FROM students
             WHERE status = 'active'
             ORDER BY last_name ASC, first_name ASC"
        );

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO students
                (student_group_id, first_name, last_name, birth_date, phone, telegram_username, guardian_name, guardian_phone, guardian_telegram_username, enrollment_date, status, notes)
             VALUES
                (:group_id, :first_name, :last_name, :birth_date, :phone, :telegram, :guardian_name, :guardian_phone, :guardian_telegram, :enrollment_date, :status, :notes)'
        );
        $this->bindStudentData($stmt, $data);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE students
             SET student_group_id = :group_id,
                 first_name = :first_name,
                 last_name = :last_name,
                 birth_date = :birth_date,
                 phone = :phone,
                 telegram_username = :telegram,
                 guardian_name = :guardian_name,
                 guardian_phone = :guardian_phone,
                 guardian_telegram_username = :guardian_telegram,
                 enrollment_date = :enrollment_date,
                 status = :status,
                 notes = :notes
             WHERE id = :id'
        );
        $this->bindStudentData($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updatePhoto(int $id, string $photoPath): void
    {
        $stmt = $this->pdo->prepare('UPDATE students SET photo_path = :photo_path WHERE id = :id');
        $stmt->bindValue(':photo_path', $photoPath);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function activeSubscriptionId(int $studentId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT subscription_id
             FROM student_subscriptions
             WHERE student_id = :student_id AND status = 'active'
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function closeActiveSubscriptions(int $studentId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE student_subscriptions
             SET status = 'ended', end_date = COALESCE(end_date, CURRENT_DATE)
             WHERE student_id = :student_id AND status = 'active'"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function createSubscription(int $studentId, int $subscriptionId, string $startDate): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO student_subscriptions (student_id, subscription_id, start_date, status)
             VALUES (:student_id, :subscription_id, :start_date, 'active')"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':subscription_id', $subscriptionId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->execute();
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE students SET status = 'archived' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function currentSubscriptionJoin(): string
    {
        return "
            LEFT JOIN (
                SELECT ss.*
                FROM student_subscriptions ss
                INNER JOIN (
                    SELECT student_id, MAX(id) AS id
                    FROM student_subscriptions
                    WHERE status = 'active'
                    GROUP BY student_id
                ) latest ON latest.id = ss.id
            ) cs ON cs.student_id = s.id
            LEFT JOIN subscriptions sub ON sub.id = cs.subscription_id
        ";
    }

    private function buildListWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['status'] === '') {
            $where[] = "s.status <> 'archived'";
        } elseif (in_array($filters['status'], ['lead', 'active', 'paused', 'inactive', 'archived'], true)) {
            $where[] = 's.status = :status';
            $params[':status'] = $filters['status'];
        }

        if ($filters['search'] !== '') {
            $where[] = "(s.first_name LIKE :search_first_name
                OR s.last_name LIKE :search_last_name
                OR s.phone LIKE :search_phone
                OR REPLACE(REPLACE(REPLACE(s.phone, '+48', ''), ' ', ''), '-', '') LIKE :search_phone_digits
                OR s.telegram_username LIKE :search_telegram)";
            $search = '%' . $filters['search'] . '%';
            $phoneDigits = preg_replace('/\D+/', '', $filters['search']) ?? '';
            if (str_starts_with($phoneDigits, '48') && strlen($phoneDigits) === 11) {
                $phoneDigits = substr($phoneDigits, 2);
            }
            $phoneSearch = $phoneDigits !== '' ? '%' . $phoneDigits . '%' : '__no_phone_match__';
            $params[':search_first_name'] = $search;
            $params[':search_last_name'] = $search;
            $params[':search_phone'] = $search;
            $params[':search_phone_digits'] = $phoneSearch;
            $params[':search_telegram'] = $search;
        }

        if ($filters['group'] > 0) {
            $where[] = 's.student_group_id = :group_id';
            $params[':group_id'] = $filters['group'];
        }

        if ($filters['subscription'] > 0) {
            $where[] = 'cs.subscription_id = :subscription_id';
            $params[':subscription_id'] = $filters['subscription'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function bindStudentData(\PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':group_id', $data['student_group_id'], PDO::PARAM_INT);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':birth_date', $data['birth_date']);
        $stmt->bindValue(':phone', $data['phone']);
        $stmt->bindValue(':telegram', $data['telegram_username']);
        $stmt->bindValue(':guardian_name', $data['guardian_name']);
        $stmt->bindValue(':guardian_phone', $data['guardian_phone']);
        $stmt->bindValue(':guardian_telegram', $data['guardian_telegram_username']);
        $stmt->bindValue(':enrollment_date', $data['enrollment_date']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':notes', $data['notes']);
    }
}
