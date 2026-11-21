<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function group(string $group): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT setting_key, setting_value, value_type, is_private
             FROM settings
             WHERE setting_group = :setting_group
             ORDER BY setting_key ASC'
        );
        $stmt->execute([':setting_group' => $group]);

        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = $row;
        }

        return $settings;
    }

    public function set(string $key, string $value, string $group, string $type = 'string', bool $private = false): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value, setting_group, value_type, is_private, updated_by)
             VALUES (:setting_key, :setting_value, :setting_group, :value_type, :is_private, 1)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                value_type = VALUES(value_type),
                is_private = VALUES(is_private),
                updated_by = VALUES(updated_by)"
        );
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
            ':setting_group' => $group,
            ':value_type' => $type,
            ':is_private' => $private ? 1 : 0,
        ]);
    }

    public function subscriptions(): array
    {
        return $this->pdo
            ->query('SELECT id, name, lessons_count, price, status, sort_order FROM subscriptions ORDER BY sort_order ASC, lessons_count ASC, price ASC, id ASC')
            ->fetchAll();
    }

    public function updateSubscription(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE subscriptions
             SET name = :name,
                 lessons_count = :lessons_count,
                 price = :price,
                 status = :status,
                 sort_order = :sort_order
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':lessons_count' => $data['lessons_count'],
            ':price' => $data['price'],
            ':status' => $data['status'],
            ':sort_order' => $data['sort_order'],
            ':id' => $id,
        ]);
    }

    public function updateSubscriptionOrder(array $ids): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE subscriptions
             SET sort_order = :sort_order
             WHERE id = :id'
        );

        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([
                ':sort_order' => ($index + 1) * 10,
                ':id' => (int) $id,
            ]);
        }
    }

    public function subscriptionSlugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createSubscription(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscriptions (name, slug, lessons_count, price, currency, billing_period, status, sort_order)
             VALUES (:name, :slug, :lessons_count, :price, :currency, :billing_period, :status, :sort_order)'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':lessons_count' => $data['lessons_count'],
            ':price' => $data['price'],
            ':currency' => $data['currency'] ?? 'PLN',
            ':billing_period' => $data['billing_period'] ?? 'monthly',
            ':status' => $data['status'],
            ':sort_order' => $data['sort_order'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function nextSubscriptionSortOrder(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM subscriptions')->fetchColumn();
    }

    public function groups(): array
    {
        return $this->pdo
            ->query('SELECT id, name, max_students, color, status, is_visible FROM student_groups ORDER BY sort_order ASC, name ASC')
            ->fetchAll();
    }

    public function createGroup(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO student_groups (name, slug, max_students, color, status, is_visible, sort_order) VALUES (:name, :slug, :max_students, :color, :status, :is_visible, :sort_order)');
        $data['slug'] = 'group-' . bin2hex(random_bytes(12));
        $data['sort_order'] = (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM student_groups')->fetchColumn();
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateGroup(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE student_groups
             SET name = :name,
                 max_students = :max_students,
                 color = :color,
                 status = :status,
                 is_visible = :is_visible
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':max_students' => $data['max_students'],
            ':color' => $data['color'],
            ':status' => $data['status'],
            ':is_visible' => $data['is_visible'],
            ':id' => $id,
        ]);
    }

    public function salaryRules(): array
    {
        return $this->pdo
            ->query("SELECT id, name, role_scope, rule_type, amount, percentage, status FROM salary_rules WHERE role_scope IN ('accountant', 'teacher', 'marketing') ORDER BY FIELD(role_scope, 'teacher', 'accountant', 'marketing'), name ASC")
            ->fetchAll();
    }

    public function updateSalaryRule(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE salary_rules
             SET name = :name,
                 rule_type = :rule_type,
                 amount = :amount,
                 percentage = :percentage,
                 status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':rule_type' => $data['rule_type'],
            ':amount' => $data['amount'],
            ':percentage' => $data['percentage'],
            ':status' => $data['status'],
            ':id' => $id,
        ]);
    }

    public function databaseName(): string
    {
        return (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
    }
}
