<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class PaymentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                p.*,
                s.first_name,
                s.last_name,
                s.photo_path,
                s.phone,
                s.telegram_username,
                sg.name AS group_name,
                sub.name AS subscription_name,
                pm.name AS payment_method_name
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             {$whereSql}
             ORDER BY p.payment_date DESC, p.id DESC
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
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, s.first_name, s.last_name, sub.name AS subscription_name, pm.name AS payment_method_name
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             WHERE p.id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payments
                (payment_number, student_id, subscription_id, payment_method_id, amount, currency, payment_date, period_start, period_end, category, status, comment, receipt_path)
             VALUES
                (:payment_number, :student_id, :subscription_id, :payment_method_id, :amount, 'PLN', :payment_date, :period_start, :period_end, 'subscription', :status, :comment, :receipt_path)"
        );
        $this->bindPaymentData($stmt, $data, true);
        $stmt->bindValue(':receipt_path', $data['receipt_path'] ?? null);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments
             SET payment_method_id = :payment_method_id,
                 amount = :amount,
                 payment_date = :payment_date,
                 status = :status,
                 comment = :comment,
                 receipt_path = :receipt_path
             WHERE id = :id'
        );
        $stmt->bindValue(':payment_method_id', $data['payment_method_id'], PDO::PARAM_INT);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':payment_date', $data['payment_date']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':comment', $data['comment']);
        $stmt->bindValue(':receipt_path', $data['receipt_path'] ?? null);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET status = 'cancelled' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function nextPaymentNumber(int $year): string
    {
        $prefix = 'PAY-' . $year . '-';
        $stmt = $this->pdo->prepare('SELECT payment_number FROM payments WHERE payment_number LIKE :prefix ORDER BY payment_number DESC LIMIT 1');
        $stmt->bindValue(':prefix', $prefix . '%');
        $stmt->execute();
        $last = (string) ($stmt->fetchColumn() ?: '');
        $number = 1;

        if (preg_match('/^PAY-\d{4}-(\d{6})$/', $last, $matches)) {
            $number = (int) $matches[1] + 1;
        }

        return $prefix . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    public function subscriptionById(int $subscriptionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, lessons_count, price, currency, billing_period
             FROM subscriptions
             WHERE id = :id AND status = "active"'
        );
        $stmt->bindValue(':id', $subscriptionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function hasStudentPaymentForSubscriptionPeriod(
        int $studentId,
        int $subscriptionId,
        string $periodStart,
        string $periodEnd
    ): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM payments
             WHERE student_id = :student_id
               AND subscription_id = :subscription_id
               AND status <> 'cancelled'
               AND period_start = :period_start
               AND period_end = :period_end"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':subscription_id', $subscriptionId, PDO::PARAM_INT);
        $stmt->bindValue(':period_start', $periodStart);
        $stmt->bindValue(':period_end', $periodEnd);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasStudentPaymentForMonth(int $studentId, string $periodMonth): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM payments
             WHERE student_id = :student_id
               AND status <> 'cancelled'
               AND DATE_FORMAT(period_start, '%Y-%m') = :period_month"
        );
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':period_month', $periodMonth);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function summary(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END), 0) AS revenue,
                COUNT(*) AS total_count,
                SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN p.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    public function monthlyRevenue(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month, SUM(amount) AS revenue
             FROM payments
             WHERE status = 'completed' AND payment_date BETWEEN :from AND :to
             GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
             ORDER BY month"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return $stmt->fetchAll();
    }

    public function revenueByGroup(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sg.name AS group_name, SUM(p.amount) AS revenue
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             WHERE p.status = 'completed' AND p.payment_date BETWEEN :from AND :to
             GROUP BY sg.id, sg.name
             ORDER BY revenue DESC"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return $stmt->fetchAll();
    }

    public function recent(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, s.first_name, s.last_name, pm.name AS payment_method_name
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['search'] !== '') {
            $search = '%' . $filters['search'] . '%';
            $phoneDigits = preg_replace('/\D+/', '', $filters['search']) ?? '';
            if (str_starts_with($phoneDigits, '48') && strlen($phoneDigits) === 11) {
                $phoneDigits = substr($phoneDigits, 2);
            }
            $phoneSearch = $phoneDigits !== '' ? '%' . $phoneDigits . '%' : '__no_phone_match__';
            $where[] = "(s.first_name LIKE :search_first
                OR s.last_name LIKE :search_last
                OR s.phone LIKE :search_phone
                OR REPLACE(REPLACE(REPLACE(s.phone, '+48', ''), ' ', ''), '-', '') LIKE :search_phone_digits
                OR s.telegram_username LIKE :search_telegram
                OR p.payment_number LIKE :search_number)";
            $params[':search_first'] = $search;
            $params[':search_last'] = $search;
            $params[':search_phone'] = $search;
            $params[':search_phone_digits'] = $phoneSearch;
            $params[':search_telegram'] = $search;
            $params[':search_number'] = $search;
        }

        if (($filters['period'] ?? 'month') !== 'all' && $filters['month'] !== '') {
            $where[] = "DATE_FORMAT(p.period_start, '%Y-%m') = :month";
            $params[':month'] = $filters['month'];
        }

        if ($filters['method'] > 0) {
            $where[] = 'p.payment_method_id = :method_id';
            $params[':method_id'] = $filters['method'];
        }

        $showCancelled = (bool) ($filters['show_cancelled'] ?? false);

        if ($filters['status'] !== '') {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        } elseif (!$showCancelled) {
            $where[] = "p.status <> 'cancelled'";
        }

        if ($filters['subscription'] > 0) {
            $where[] = 'p.subscription_id = :subscription_id';
            $params[':subscription_id'] = $filters['subscription'];
        }

        if ($filters['student_id'] > 0) {
            $where[] = 'p.student_id = :student_id';
            $params[':student_id'] = $filters['student_id'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function bindPaymentData(PDOStatement $stmt, array $data, bool $withNumber): void
    {
        if ($withNumber) {
            $stmt->bindValue(':payment_number', $data['payment_number']);
        }

        $stmt->bindValue(':student_id', $data['student_id'], PDO::PARAM_INT);
        $stmt->bindValue(':subscription_id', $data['subscription_id'] ?: null, $data['subscription_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':payment_method_id', $data['payment_method_id'], PDO::PARAM_INT);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':payment_date', $data['payment_date']);
        $stmt->bindValue(':period_start', $data['period_start']);
        $stmt->bindValue(':period_end', $data['period_end']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':comment', $data['comment']);
    }
}
