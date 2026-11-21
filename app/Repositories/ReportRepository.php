<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function storeGenerated(string $type, string $from, string $to, array $filters): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reports (name, report_type, period_start, period_end, file_url, filters_json, status, generated_by)
             VALUES (:name, :report_type, :period_start, :period_end, NULL, :filters_json, 'generated', 1)"
        );
        $stmt->execute([
            ':name' => $this->reportName($type, $from, $to),
            ':report_type' => $this->databaseType($type),
            ':period_start' => $from,
            ':period_end' => $to,
            ':filters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function financialSummary(string $from, string $to): array
    {
        return [
            'income' => $this->single("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND payment_date BETWEEN :from AND :to", $from, $to),
            'expenses' => $this->single("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status NOT IN ('cancelled', 'rejected') AND expense_date BETWEEN :from AND :to", $from, $to),
            'payments_count' => (int) $this->single("SELECT COUNT(*) FROM payments WHERE status <> 'cancelled' AND payment_date BETWEEN :from AND :to", $from, $to),
            'active_students' => (int) $this->pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(),
            'salary_costs' => $this->single("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE is_salary = 1 AND status NOT IN ('cancelled', 'rejected') AND expense_date BETWEEN :from AND :to", $from, $to),
            'trip_costs' => $this->single("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE trip_id IS NOT NULL AND status NOT IN ('cancelled', 'rejected') AND expense_date BETWEEN :from AND :to", $from, $to),
            'marketing_costs' => $this->single(
                "SELECT COALESCE(SUM(e.amount), 0)
                 FROM expenses e
                 INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
                 WHERE e.status NOT IN ('cancelled', 'rejected')
                   AND e.expense_date BETWEEN :from AND :to
                   AND (ec.slug = 'marketing' OR LOWER(e.description) LIKE '%instagram%')",
                $from,
                $to
            ),
        ];
    }

    public function monthlyIncomeExpenses(int $year): array
    {
        $payments = $this->pdo->prepare(
            "SELECT MONTH(payment_date) AS month, COALESCE(SUM(amount), 0) AS amount
             FROM payments
             WHERE status = 'completed' AND YEAR(payment_date) = :year
             GROUP BY MONTH(payment_date)"
        );
        $payments->execute([':year' => $year]);

        $expenses = $this->pdo->prepare(
            "SELECT MONTH(expense_date) AS month, COALESCE(SUM(amount), 0) AS amount
             FROM expenses
             WHERE status NOT IN ('cancelled', 'rejected') AND YEAR(expense_date) = :year
             GROUP BY MONTH(expense_date)"
        );
        $expenses->execute([':year' => $year]);

        $rows = array_fill(1, 12, ['income' => 0.0, 'expenses' => 0.0]);
        foreach ($payments->fetchAll() as $row) {
            $rows[(int) $row['month']]['income'] = (float) $row['amount'];
        }
        foreach ($expenses->fetchAll() as $row) {
            $rows[(int) $row['month']]['expenses'] = (float) $row['amount'];
        }

        return $rows;
    }

    public function paymentsReport(string $from, string $to): array
    {
        return [
            'by_date' => $this->groupRows("SELECT payment_date AS label, COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS count FROM payments WHERE status = 'completed' AND payment_date BETWEEN :from AND :to GROUP BY payment_date ORDER BY payment_date DESC", $from, $to),
            'by_student' => $this->groupRows("SELECT CONCAT(s.first_name, ' ', s.last_name) AS label, COALESCE(SUM(p.amount), 0) AS amount, COUNT(*) AS count FROM payments p INNER JOIN students s ON s.id = p.student_id WHERE p.status = 'completed' AND p.payment_date BETWEEN :from AND :to GROUP BY s.id, s.first_name, s.last_name ORDER BY amount DESC", $from, $to),
            'by_subscription' => $this->groupRows("SELECT COALESCE(sub.name, 'Brak abonamentu') AS label, COALESCE(SUM(p.amount), 0) AS amount, COUNT(*) AS count FROM payments p LEFT JOIN subscriptions sub ON sub.id = p.subscription_id WHERE p.status = 'completed' AND p.payment_date BETWEEN :from AND :to GROUP BY sub.id, sub.name ORDER BY amount DESC", $from, $to),
            'by_method' => $this->groupRows("SELECT pm.name AS label, COALESCE(SUM(p.amount), 0) AS amount, COUNT(*) AS count FROM payments p INNER JOIN payment_methods pm ON pm.id = p.payment_method_id WHERE p.status = 'completed' AND p.payment_date BETWEEN :from AND :to GROUP BY pm.id, pm.name ORDER BY amount DESC", $from, $to),
            'details' => $this->paymentDetails($from, $to),
        ];
    }

    public function expensesReport(string $from, string $to): array
    {
        return [
            'by_category' => $this->groupRows("SELECT ec.name AS label, COALESCE(SUM(e.amount), 0) AS amount, COUNT(*) AS count FROM expenses e INNER JOIN expense_categories ec ON ec.id = e.expense_category_id WHERE e.status NOT IN ('cancelled', 'rejected') AND e.expense_date BETWEEN :from AND :to GROUP BY ec.id, ec.name ORDER BY amount DESC", $from, $to),
            'by_source' => $this->groupRows("SELECT CASE WHEN e.is_salary = 1 THEN 'Wynagrodzenia' WHEN e.trip_id IS NOT NULL THEN 'Wyjazdy' WHEN e.marketing_campaign_id IS NOT NULL OR ec.slug = 'marketing' THEN 'Marketing' ELSE 'Manual' END AS label, COALESCE(SUM(e.amount), 0) AS amount, COUNT(*) AS count FROM expenses e INNER JOIN expense_categories ec ON ec.id = e.expense_category_id WHERE e.status NOT IN ('cancelled', 'rejected') AND e.expense_date BETWEEN :from AND :to GROUP BY label ORDER BY amount DESC", $from, $to),
            'by_method' => $this->groupRows("SELECT COALESCE(pm.name, 'Brak metody') AS label, COALESCE(SUM(e.amount), 0) AS amount, COUNT(*) AS count FROM expenses e LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id WHERE e.status NOT IN ('cancelled', 'rejected') AND e.expense_date BETWEEN :from AND :to GROUP BY pm.id, pm.name ORDER BY amount DESC", $from, $to),
            'details' => $this->expenseDetails($from, $to),
        ];
    }

    public function salariesReport(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sp.*, u.name AS employee_name, sr.role_scope, sr.name AS rule_name, e.id AS linked_expense_id, e.status AS expense_status
             FROM salary_payouts sp
             INNER JOIN users u ON u.id = sp.user_id
             LEFT JOIN salary_rules sr ON sr.id = sp.salary_rule_id
             LEFT JOIN expenses e ON e.salary_payout_id = sp.id
             WHERE sp.period_start <= :to AND sp.period_end >= :from
             ORDER BY sp.period_start DESC, sp.id DESC"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return ['payouts' => $stmt->fetchAll()];
    }

    public function tripsReport(string $from, string $to): array
    {
        $summary = [
            'trips_count' => (int) $this->single("SELECT COUNT(*) FROM trips WHERE status <> 'cancelled' AND start_date BETWEEN :from AND :to", $from, $to),
            'participants_count' => (int) $this->single("SELECT COUNT(*) FROM trip_participants tp INNER JOIN trips t ON t.id = tp.trip_id WHERE tp.status <> 'cancelled' AND t.start_date BETWEEN :from AND :to", $from, $to),
            'total_trip_costs' => $this->single("SELECT COALESCE(SUM(internal_cost), 0) FROM trips WHERE status <> 'cancelled' AND start_date BETWEEN :from AND :to", $from, $to),
            'photos_count' => (int) $this->single("SELECT COUNT(*) FROM trip_photos p INNER JOIN trips t ON t.id = p.trip_id WHERE t.start_date BETWEEN :from AND :to", $from, $to),
            'trip_expenses' => $this->single("SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.trip_id IS NOT NULL AND e.status NOT IN ('cancelled', 'rejected') AND e.expense_date BETWEEN :from AND :to", $from, $to),
        ];

        $stmt = $this->pdo->prepare(
            "SELECT t.*, COUNT(DISTINCT tp.student_id) AS participants_count, COUNT(DISTINCT ph.id) AS photos_count, COALESCE(SUM(CASE WHEN e.status NOT IN ('cancelled', 'rejected') THEN e.amount ELSE 0 END), 0) AS expenses_amount
             FROM trips t
             LEFT JOIN trip_participants tp ON tp.trip_id = t.id AND tp.status <> 'cancelled'
             LEFT JOIN trip_photos ph ON ph.trip_id = t.id
             LEFT JOIN expenses e ON e.trip_id = t.id
             WHERE t.start_date BETWEEN :from AND :to
             GROUP BY t.id
             ORDER BY t.start_date DESC, t.id DESC"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return ['summary' => $summary, 'details' => $stmt->fetchAll()];
    }

    public function studentsReport(string $from, string $to): array
    {
        return [
            'active' => (int) $this->pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(),
            'archived' => (int) $this->pdo->query("SELECT COUNT(*) FROM students WHERE status IN ('archived', 'inactive')")->fetchColumn(),
            'by_group' => $this->pdo->query("SELECT COALESCE(sg.name, 'Brak grupy') AS label, COUNT(*) AS count FROM students s LEFT JOIN student_groups sg ON sg.id = s.student_group_id GROUP BY sg.id, sg.name ORDER BY count DESC")->fetchAll(),
            'by_subscription' => $this->pdo->query("SELECT COALESCE(sub.name, 'Brak abonamentu') AS label, COUNT(DISTINCT s.id) AS count FROM students s LEFT JOIN student_subscriptions ss ON ss.student_id = s.id AND ss.status = 'active' LEFT JOIN subscriptions sub ON sub.id = ss.subscription_id GROUP BY sub.id, sub.name ORDER BY count DESC")->fetchAll(),
        ];
    }

    private function paymentDetails(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, sub.name AS subscription_name, pm.name AS method_name
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             WHERE p.payment_date BETWEEN :from AND :to
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT 50"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return $stmt->fetchAll();
    }

    private function expenseDetails(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, ec.name AS category_name, pm.name AS method_name
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             WHERE e.expense_date BETWEEN :from AND :to
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT 50"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return $stmt->fetchAll();
    }

    private function groupRows(string $sql, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $from, ':to' => $to]);

        return $stmt->fetchAll();
    }

    private function single(string $sql, string $from, string $to): float
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (float) $stmt->fetchColumn();
    }

    private function databaseType(string $type): string
    {
        return match ($type) {
            'financial_summary' => 'profit_loss',
            'payments_report' => 'revenue',
            'expenses_report' => 'expenses',
            'salaries_report' => 'salaries',
            'trips_report' => 'trips',
            'students_report' => 'students',
            default => 'custom',
        };
    }

    private function reportName(string $type, string $from, string $to): string
    {
        return $type . ' ' . $from . ' - ' . $to;
    }
}
