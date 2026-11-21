<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class SalaryRepository
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

    public function activeRules(): array
    {
        return $this->pdo
            ->query("SELECT * FROM salary_rules WHERE status = 'active' ORDER BY role_scope ASC, name ASC")
            ->fetchAll();
    }

    public function ruleForRole(string $role): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM salary_rules
             WHERE status = 'active'
               AND role_scope = :role
             ORDER BY effective_from DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([':role' => $role]);

        return $stmt->fetch() ?: null;
    }

    public function ensureEmployeeUser(string $role): int
    {
        $name = match ($role) {
            'accountant' => 'Accountant',
            'marketing' => 'Marketing specialist',
            default => 'Teacher',
        };
        $email = $role . '@limodelia.local';

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            return (int) $existing;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (name, email, password_hash, status)
             VALUES (:name, :email, :password_hash, 'active')"
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => '$2y$10$payroll_placeholder_hash',
        ]);
        $userId = (int) $this->pdo->lastInsertId();

        $roleStmt = $this->pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $roleStmt->execute([':slug' => match ($role) {
            'accountant' => 'finance',
            'marketing' => 'marketing',
            default => 'teacher',
        }]);
        $roleId = (int) ($roleStmt->fetchColumn() ?: 0);

        if ($roleId > 0) {
            $assign = $this->pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, 1)');
            $assign->execute([':user_id' => $userId, ':role_id' => $roleId]);
        }

        return $userId;
    }

    public function currentMonthRevenue(string $from, string $to): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND payment_date BETWEEN :from AND :to");
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (float) $stmt->fetchColumn();
    }

    public function currentMonthNonSalaryExpenses(string $from, string $to): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM expenses
             WHERE status NOT IN ('cancelled', 'rejected')
               AND is_salary = 0
               AND expense_date BETWEEN :from AND :to"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (float) $stmt->fetchColumn();
    }

    public function marketingNewStudents(string $from, string $to): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(new_students_count), 0)
             FROM marketing_campaigns
             WHERE status <> 'cancelled'
               AND COALESCE(start_date, :from_start) <= :to
               AND COALESCE(end_date, start_date, :to_end) >= :from"
        );
        $stmt->execute([
            ':from_start' => $from,
            ':to' => $to,
            ':to_end' => $to,
            ':from' => $from,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function list(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                sp.*,
                u.name AS employee_name,
                sr.name AS rule_name,
                sr.role_scope,
                sr.rule_type,
                sr.amount AS rule_amount,
                sr.percentage AS rule_percentage
             FROM salary_payouts sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN salary_rules sr ON sr.id = sp.salary_rule_id
             {$whereSql}
             ORDER BY sp.period_start DESC, sp.id DESC
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
             FROM salary_payouts sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN salary_rules sr ON sr.id = sp.salary_rule_id
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function summary(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN sp.status <> 'cancelled' THEN sp.total_amount ELSE 0 END), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN sp.status = 'paid' THEN sp.total_amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN sp.status IN ('draft', 'calculated', 'approved') THEN sp.total_amount ELSE 0 END), 0) AS pending_amount,
                COUNT(DISTINCT CASE WHEN sp.status <> 'cancelled' THEN sp.user_id END) AS employee_count,
                COUNT(*) AS total_count,
                SUM(CASE WHEN sp.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN sp.status IN ('draft', 'calculated', 'approved') THEN 1 ELSE 0 END) AS pending_count
             FROM salary_payouts sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN salary_rules sr ON sr.id = sp.salary_rule_id
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    public function createPayout(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO salary_payouts
                (user_id, salary_rule_id, period_start, period_end, base_amount, bonus_amount, deduction_amount, net_profit_base, total_amount, currency, status, calculated_at, approved_by, approved_at, paid_at, notes)
             VALUES
                (:user_id, :salary_rule_id, :period_start, :period_end, :base_amount, 0.00, 0.00, :net_profit_base, :total_amount, 'PLN', :status, NOW(), :approved_by, :approved_at, :paid_at, :notes)"
        );
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':salary_rule_id' => $data['salary_rule_id'],
            ':period_start' => $data['period_start'],
            ':period_end' => $data['period_end'],
            ':base_amount' => $data['base_amount'],
            ':net_profit_base' => $data['net_profit_base'],
            ':total_amount' => $data['total_amount'],
            ':status' => $data['status'],
            ':approved_by' => $data['status'] === 'paid' ? 1 : null,
            ':approved_at' => $data['status'] === 'paid' ? $data['paid_at'] : null,
            ':paid_at' => $data['status'] === 'paid' ? $data['paid_at'] : null,
            ':notes' => $data['notes'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createLinkedExpense(int $payoutId, array $data): int
    {
        $categoryId = $this->salaryExpenseCategoryId();
        $methodId = $this->defaultPaymentMethodId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO expenses
                (expense_category_id, payment_method_id, salary_payout_id, is_salary, amount, currency, expense_date, description, status, approved_by, approved_at)
             VALUES
                (:category_id, :method_id, :payout_id, 1, :amount, 'PLN', :expense_date, :description, 'paid', 1, :approved_at)"
        );
        $stmt->execute([
            ':category_id' => $categoryId,
            ':method_id' => $methodId,
            ':payout_id' => $payoutId,
            ':amount' => $data['total_amount'],
            ':expense_date' => substr((string) $data['paid_at'], 0, 10),
            ':description' => $data['expense_description'],
            ':approved_at' => $data['paid_at'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE salary_payouts SET status = 'cancelled' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $expense = $this->pdo->prepare("UPDATE expenses SET status = 'cancelled' WHERE salary_payout_id = :id");
        $expense->bindValue(':id', $id, PDO::PARAM_INT);
        $expense->execute();
    }

    private function salaryExpenseCategoryId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM expense_categories WHERE slug = 'ksiegowosc' LIMIT 1");
        $id = (int) ($stmt->fetchColumn() ?: 0);

        if ($id > 0) {
            return $id;
        }

        return (int) $this->pdo->query('SELECT id FROM expense_categories ORDER BY sort_order ASC, id ASC LIMIT 1')->fetchColumn();
    }

    private function defaultPaymentMethodId(): ?int
    {
        $id = (int) $this->pdo->query("SELECT id FROM payment_methods WHERE slug = 'przelew' LIMIT 1")->fetchColumn();

        return $id > 0 ? $id : null;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if ($filters['month'] !== '') {
            $where[] = "DATE_FORMAT(sp.period_start, '%Y-%m') = :month";
            $params[':month'] = $filters['month'];
        }

        if ($filters['employee'] !== '') {
            $where[] = 'sr.role_scope = :employee';
            $params[':employee'] = $filters['employee'];
        }

        if ($filters['status'] !== '') {
            $where[] = 'sp.status = :status';
            $params[':status'] = $filters['status'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
