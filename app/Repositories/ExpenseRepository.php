<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class ExpenseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                e.*,
                ec.name AS category_name,
                ec.slug AS category_slug,
                pm.name AS payment_method_name,
                pm.slug AS payment_method_slug
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             {$whereSql}
             ORDER BY e.expense_date DESC, e.id DESC
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
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, ec.name AS category_name, ec.slug AS category_slug, pm.name AS payment_method_name
             , pm.slug AS payment_method_slug
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             WHERE e.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function categorySlugById(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT slug FROM expense_categories WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $slug = $stmt->fetchColumn();

        return $slug !== false ? (string) $slug : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO expenses
                (expense_category_id, payment_method_id, trip_id, marketing_campaign_id, amount, currency, expense_date, description, receipt_path, status, approved_by, approved_at)
             VALUES
                (:category_id, :payment_method_id, :trip_id, :marketing_campaign_id, :amount, 'PLN', :expense_date, :description, :receipt_path, :status, :approved_by, :approved_at)"
        );
        $this->bindExpenseData($stmt, $data);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        $hasLinkedSalary = $existing && !empty($existing['salary_payout_id']);

        $sql = $hasLinkedSalary
            ? "UPDATE expenses
               SET expense_category_id = :category_id,
                   payment_method_id = :payment_method_id,
                   amount = :amount,
                   expense_date = :expense_date,
                   description = :description,
                   receipt_path = :receipt_path,
                   trip_id = :trip_id,
                   marketing_campaign_id = :marketing_campaign_id,
                   status = :status,
                   approved_by = :approved_by,
                   approved_at = :approved_at
               WHERE id = :id"
            : "UPDATE expenses
               SET expense_category_id = :category_id,
                   payment_method_id = :payment_method_id,
                   amount = :amount,
                   expense_date = :expense_date,
                   description = :description,
                   receipt_path = :receipt_path,
                   status = :status,
                   trip_id = :trip_id,
                   marketing_campaign_id = :marketing_campaign_id,
                   salary_payout_id = NULL,
                   is_salary = 0,
                   approved_by = :approved_by,
                   approved_at = :approved_at
               WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $this->bindExpenseData($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE expenses SET status = 'cancelled' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function summary(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN e.status NOT IN ('cancelled', 'rejected') THEN e.amount ELSE 0 END), 0) AS total_amount,
                COUNT(*) AS total_count,
                COALESCE(AVG(CASE WHEN e.status NOT IN ('cancelled', 'rejected') THEN e.amount ELSE NULL END), 0) AS average_amount,
                COALESCE(MAX(CASE WHEN e.status NOT IN ('cancelled', 'rejected') THEN e.amount ELSE NULL END), 0) AS max_amount
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    public function yearTotal(int $year): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM expenses
             WHERE status NOT IN ('cancelled', 'rejected')
               AND YEAR(expense_date) = :year"
        );
        $stmt->execute([':year' => $year]);

        return (float) $stmt->fetchColumn();
    }

    public function byCategory(string $month): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ec.id AS category_id, ec.name AS category_name, ec.slug AS category_slug, COALESCE(SUM(e.amount), 0) AS amount
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             WHERE e.status NOT IN ('cancelled', 'rejected')
               AND DATE_FORMAT(e.expense_date, '%Y-%m') = :month
             GROUP BY ec.id, ec.name, ec.slug
             ORDER BY amount DESC"
        );
        $stmt->execute([':month' => $month]);

        return $stmt->fetchAll();
    }

    public function largest(string $month): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.description, e.amount, e.currency, e.expense_date, ec.name AS category_name
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             WHERE e.status NOT IN ('cancelled', 'rejected')
               AND DATE_FORMAT(e.expense_date, '%Y-%m') = :month
             ORDER BY e.amount DESC, e.expense_date DESC
             LIMIT 1"
        );
        $stmt->execute([':month' => $month]);

        return $stmt->fetch() ?: null;
    }

    public function reservedRentalDates(?int $exceptExpenseId = null): array
    {
        $sql = "SELECT e.description
                FROM expenses e
                INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
                WHERE ec.slug = 'wynajem'
                  AND e.status NOT IN ('cancelled', 'rejected')";
        $params = [];

        if ($exceptExpenseId !== null && $exceptExpenseId > 0) {
            $sql .= ' AND e.id <> :except_id';
            $params[':except_id'] = $exceptExpenseId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        $dates = [];
        foreach ($stmt->fetchAll() as $row) {
            if (preg_match('/Rezerwacja sali:\s*(\d{4}-\d{2}-\d{2})/u', (string) ($row['description'] ?? ''), $match)) {
                $dates[$match[1]] = true;
            }
        }

        return array_keys($dates);
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['search'] !== '') {
            $where[] = 'e.description LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if ($filters['month'] !== '') {
            $where[] = "DATE_FORMAT(e.expense_date, '%Y-%m') = :month";
            $params[':month'] = $filters['month'];
        }

        if ($filters['category'] > 0) {
            $where[] = 'e.expense_category_id = :category_id';
            $params[':category_id'] = $filters['category'];
        }

        if ($filters['method'] > 0) {
            $where[] = 'e.payment_method_id = :method_id';
            $params[':method_id'] = $filters['method'];
        }

        if (empty($filters['show_cancelled'])) {
            $where[] = "e.status <> 'cancelled'";
        }

        if (($filters['highlight_expense'] ?? 0) > 0) {
            $baseWhere = $where ? '(' . implode(' AND ', $where) . ')' : '1 = 1';
            $whereSql = 'WHERE (' . $baseWhere . ' OR e.id = :highlight_expense)';
            $params[':highlight_expense'] = (int) $filters['highlight_expense'];

            return [$whereSql, $params];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function bindExpenseData(PDOStatement $stmt, array $data): void
    {
        $approved = in_array((string) ($data['status'] ?? ''), ['approved', 'paid'], true);
        $stmt->bindValue(':category_id', $data['expense_category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':payment_method_id', $data['payment_method_id'] ?: null, $data['payment_method_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':trip_id', $data['trip_id'] ?? null, !empty($data['trip_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':marketing_campaign_id', $data['marketing_campaign_id'] ?? null, !empty($data['marketing_campaign_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':expense_date', $data['expense_date']);
        $stmt->bindValue(':description', $data['description']);
        $stmt->bindValue(':receipt_path', $data['receipt_path'] ?? null);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':approved_by', $approved ? 1 : null, $approved ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':approved_at', $approved ? date('Y-m-d H:i:s') : null);
    }
}
