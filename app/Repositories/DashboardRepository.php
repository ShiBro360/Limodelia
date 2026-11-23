<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DashboardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function currentMonthRevenue(string $from, string $to): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND payment_date BETWEEN :from AND :to");
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (float) $stmt->fetchColumn();
    }

    public function currentMonthExpenses(string $from, string $to): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status NOT IN ('cancelled', 'rejected') AND expense_date BETWEEN :from AND :to");
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

    public function activeStudentsCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    }

    public function monthlyRevenue(int $year): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT MONTH(payment_date) AS month, COALESCE(SUM(amount), 0) AS revenue
             FROM payments
             WHERE status = 'completed' AND YEAR(payment_date) = :year
             GROUP BY MONTH(payment_date)"
        );
        $stmt->execute([':year' => $year]);

        return $stmt->fetchAll();
    }

    /**
     * Monthly school net profit: completed payments minus every recorded expense
     * (salary expenses included), using the same filters as the dashboard KPIs
     * and the financial report, so the yearly chart and the monthly figures agree.
     */
    public function monthlyNetProfit(int $year): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT month, SUM(income) - SUM(expense) AS profit
             FROM (
                 SELECT MONTH(payment_date) AS month, SUM(amount) AS income, 0 AS expense
                 FROM payments
                 WHERE status = 'completed' AND YEAR(payment_date) = :payment_year
                 GROUP BY MONTH(payment_date)
                 UNION ALL
                 SELECT MONTH(expense_date) AS month, 0 AS income, SUM(amount) AS expense
                 FROM expenses
                 WHERE status NOT IN ('cancelled', 'rejected')
                   AND YEAR(expense_date) = :expense_year
                 GROUP BY MONTH(expense_date)
             ) monthly_totals
             GROUP BY month
             ORDER BY month"
        );
        $stmt->execute([
            ':payment_year' => $year,
            ':expense_year' => $year,
        ]);

        return $stmt->fetchAll();
    }

    public function revenueByGroup(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sg.name AS group_name, COALESCE(SUM(p.amount), 0) AS revenue
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

    public function recentPayments(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.payment_date, p.amount, p.currency, s.first_name, s.last_name, s.photo_path, sg.name AS group_name, sub.name AS subscription_name, pm.name AS method_name
             FROM payments p
             INNER JOIN students s ON s.id = p.student_id
             LEFT JOIN student_groups sg ON sg.id = s.student_group_id
             LEFT JOIN subscriptions sub ON sub.id = p.subscription_id
             INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
             WHERE p.status <> 'cancelled'
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recentExpenses(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.description, e.amount, e.currency, e.expense_date, ec.name AS category_name, ec.slug AS category_slug, pm.name AS method_name
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             WHERE e.status NOT IN ('cancelled', 'rejected')
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function rentalExpenseStatuses(string $from, string $to): array
    {
        $rentalPrefix = substr($from, 0, 7) . '-%';
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.expense_date, e.status, e.amount, e.description, ec.slug AS category_slug
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             WHERE ec.slug IN ('wynajem', 'wynajem-indywidualny')
               AND (
                   e.expense_date BETWEEN :from AND :to
                   OR e.description LIKE :rental_prefix
                   OR e.description LIKE :individual_rental_prefix
               )
             ORDER BY e.expense_date ASC, e.id DESC"
        );
        $stmt->execute([
            ':from' => $from,
            ':to' => $to,
            ':rental_prefix' => '%Rezerwacja sali: ' . $rentalPrefix . '%',
            ':individual_rental_prefix' => '%Rezerwacja sali indywidualna: ' . $rentalPrefix . '%',
        ]);

        return $stmt->fetchAll();
    }

    public function paidRentalLessonDates(string $from, string $to): array
    {
        $dates = [];

        foreach ($this->rentalExpenseStatuses($from, $to) as $row) {
            if ((string) ($row['status'] ?? '') !== 'paid') {
                continue;
            }

            $description = (string) ($row['description'] ?? '');
            $date = null;
            if (preg_match('/Rezerwacja sali(?: indywidualna)?:\s*(\d{4}-\d{2}-\d{2})/u', $description, $match)) {
                $date = $match[1];
            } else {
                $date = (string) ($row['expense_date'] ?? '');
            }

            if ($date >= $from && $date <= $to) {
                $dates[$date] = $date;
            }
        }

        ksort($dates);

        return array_values($dates);
    }

    public function yearIncome(int $year): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND YEAR(payment_date) = :year");
        $stmt->execute([':year' => $year]);

        return (float) $stmt->fetchColumn();
    }

    public function setting(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function activeSalaryRules(): array
    {
        return $this->pdo
            ->query("SELECT name, role_scope, rule_type, amount, percentage FROM salary_rules WHERE status = 'active' ORDER BY role_scope, name")
            ->fetchAll();
    }

    public function marketingNewStudents(string $from, string $to): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(new_students_count), 0)
             FROM marketing_campaigns
             WHERE status <> 'cancelled'
               AND (
                   start_date BETWEEN :from_start AND :to_start
                   OR end_date BETWEEN :from_end AND :to_end
                   OR (start_date <= :from_cover AND (end_date IS NULL OR end_date >= :to_cover))
               )"
        );
        $stmt->execute([
            ':from_start' => $from,
            ':to_start' => $to,
            ':from_end' => $from,
            ':to_end' => $to,
            ':from_cover' => $from,
            ':to_cover' => $to,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function instagramSpending(string $from, string $to): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(e.amount), 0)
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             WHERE e.status NOT IN ('cancelled', 'rejected')
               AND e.expense_date BETWEEN :from AND :to
               AND (LOWER(ec.name) LIKE '%marketing%' OR LOWER(e.description) LIKE '%instagram%')"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (float) $stmt->fetchColumn();
    }

    public function tripsThisMonth(string $from, string $to): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM trips WHERE status <> 'cancelled' AND start_date BETWEEN :from AND :to");
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (int) $stmt->fetchColumn();
    }

    public function tripExpenses(string $from, string $to): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM expenses
             WHERE trip_id IS NOT NULL
               AND status NOT IN ('cancelled', 'rejected')
               AND expense_date BETWEEN :from AND :to"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (float) $stmt->fetchColumn();
    }
}
