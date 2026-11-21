<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class MarketingRepository
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
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT mc.*, e.id AS linked_expense_id, e.status AS expense_status
             FROM marketing_campaigns mc
             LEFT JOIN expenses e ON e.marketing_campaign_id = mc.id
             {$whereSql}
             ORDER BY mc.start_date DESC, mc.id DESC
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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM marketing_campaigns mc {$whereSql}");
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function summary(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN mc.status <> 'cancelled' THEN mc.budget ELSE 0 END), 0) AS budget,
                COALESCE(SUM(CASE WHEN mc.status <> 'cancelled' THEN mc.leads_count ELSE 0 END), 0) AS leads,
                COALESCE(SUM(CASE WHEN mc.status <> 'cancelled' THEN mc.new_students_count ELSE 0 END), 0) AS new_students,
                COUNT(*) AS campaigns_count
             FROM marketing_campaigns mc
             {$whereSql}"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM marketing_campaigns WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO marketing_campaigns
                (name, source, instagram_post_url, campaign_url, status, budget, leads_count, new_students_count, currency, start_date, end_date, description, created_by)
             VALUES
                (:name, :source, :instagram_post_url, :campaign_url, :status, :budget, :leads_count, :new_students_count, 'PLN', :start_date, :end_date, :description, 1)"
        );
        $this->bindCampaignData($stmt, $data);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE marketing_campaigns
             SET name = :name,
                 source = :source,
                 instagram_post_url = :instagram_post_url,
                 campaign_url = :campaign_url,
                 status = :status,
                 budget = :budget,
                 leads_count = :leads_count,
                 new_students_count = :new_students_count,
                 start_date = :start_date,
                 end_date = :end_date,
                 description = :description
             WHERE id = :id"
        );
        $this->bindCampaignData($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE marketing_campaigns SET status = 'cancelled' WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function linkedExpenseId(int $campaignId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM expenses WHERE marketing_campaign_id = :id ORDER BY id DESC LIMIT 1');
        $stmt->execute([':id' => $campaignId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    }

    public function marketingCategoryId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM expense_categories WHERE slug = 'marketing' LIMIT 1");

        return (int) $stmt->fetchColumn();
    }

    public function defaultPaymentMethodId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM payment_methods WHERE slug IN ('karta', 'przelew') ORDER BY FIELD(slug, 'karta', 'przelew') LIMIT 1");
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? $id : (int) $this->pdo->query('SELECT id FROM payment_methods ORDER BY id LIMIT 1')->fetchColumn();
    }

    public function monthlyTrend(int $year): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT MONTH(start_date) AS month, COALESCE(SUM(budget), 0) AS budget, COALESCE(SUM(new_students_count), 0) AS new_students
             FROM marketing_campaigns
             WHERE status <> 'cancelled' AND YEAR(start_date) = :year
             GROUP BY MONTH(start_date)"
        );
        $stmt->execute([':year' => $year]);

        return $stmt->fetchAll();
    }

    public function topCampaigns(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM marketing_campaigns mc
             {$whereSql}
             ORDER BY new_students_count DESC, budget DESC
             LIMIT 3"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function sourceBreakdown(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(NULLIF(mc.source, ''), 'instagram') AS source,
                    COALESCE(SUM(CASE WHEN mc.status <> 'cancelled' THEN mc.leads_count ELSE 0 END), 0) AS leads,
                    COALESCE(SUM(CASE WHEN mc.status <> 'cancelled' THEN mc.new_students_count ELSE 0 END), 0) AS new_students,
                    COALESCE(SUM(CASE WHEN mc.status <> 'cancelled' THEN mc.budget ELSE 0 END), 0) AS budget
             FROM marketing_campaigns mc
             {$whereSql}
             GROUP BY COALESCE(NULLIF(mc.source, ''), 'instagram')
             ORDER BY leads DESC, budget DESC"
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function orphanMarketingExpenses(): array
    {
        $stmt = $this->pdo->query(
            "SELECT e.id, e.amount, e.currency, e.expense_date, e.description, e.status, pm.name AS payment_method_name
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             LEFT JOIN payment_methods pm ON pm.id = e.payment_method_id
             WHERE ec.slug = 'marketing'
               AND e.marketing_campaign_id IS NULL
               AND e.status <> 'cancelled'
             ORDER BY e.expense_date ASC, e.id ASC"
        );

        return $stmt->fetchAll();
    }

    public function attachExpenseToCampaign(int $expenseId, int $campaignId): void
    {
        $stmt = $this->pdo->prepare('UPDATE expenses SET marketing_campaign_id = :campaign_id WHERE id = :expense_id');
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':expense_id' => $expenseId,
        ]);
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(mc.name LIKE :search OR mc.description LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = '(mc.end_date IS NULL OR mc.end_date >= :from_date)';
            $params[':from_date'] = $filters['from'];
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = '(mc.start_date IS NULL OR mc.start_date <= :to_date)';
            $params[':to_date'] = $filters['to'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'mc.status = :status';
            $params[':status'] = $filters['status'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindCampaignData(PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':source', $data['source']);
        $stmt->bindValue(':instagram_post_url', $data['instagram_post_url']);
        $stmt->bindValue(':campaign_url', $data['campaign_url']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':budget', $data['budget']);
        $stmt->bindValue(':leads_count', $data['leads_count'], PDO::PARAM_INT);
        $stmt->bindValue(':new_students_count', $data['new_students_count'], PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $data['start_date']);
        $stmt->bindValue(':end_date', $data['end_date']);
        $stmt->bindValue(':description', $data['description']);
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
