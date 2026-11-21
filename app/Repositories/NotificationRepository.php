<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createOnce(array $data): bool
    {
        $exists = $this->pdo->prepare(
            "SELECT id
             FROM notifications
             WHERE title = :title
               AND message = :message
               AND type = :type
               AND related_entity_type <=> :related_entity_type
               AND related_entity_id <=> :related_entity_id
               AND status <> 'archived'
             LIMIT 1"
        );
        $exists->execute([
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':type' => $data['type'],
            ':related_entity_type' => $data['related_entity_type'] ?? null,
            ':related_entity_id' => $data['related_entity_id'] ?? null,
        ]);

        if ($exists->fetchColumn() !== false) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications
                (user_id, title, message, type, status, related_entity_type, related_entity_id)
             VALUES
                (:user_id, :title, :message, :type, 'unread', :related_entity_type, :related_entity_id)"
        );
        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':type' => $data['type'] ?? 'info',
            ':related_entity_type' => $data['related_entity_type'] ?? null,
            ':related_entity_id' => $data['related_entity_id'] ?? null,
        ]);

        return true;
    }

    public function unreadCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE status = 'unread'");

        return (int) $stmt->fetchColumn();
    }

    public function recentUnread(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, message, type, related_entity_type, related_entity_id, created_at
             FROM notifications
             WHERE status = 'unread'
             ORDER BY created_at DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markAllRead(): void
    {
        $this->pdo->exec(
            "UPDATE notifications
             SET status = 'read', read_at = NOW()
             WHERE status = 'unread'"
        );
    }

    public function hasRoomReservationForDate(string $date): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
             WHERE e.status NOT IN ('cancelled', 'rejected')
               AND (
                    LOWER(ec.name) LIKE '%wynajem%'
                    OR LOWER(ec.name) LIKE '%sala%'
                    OR ec.slug = 'wynajem'
               )
               AND e.description LIKE :date_marker
             LIMIT 1"
        );
        $stmt->execute([':date_marker' => '%' . $date . '%']);

        return $stmt->fetchColumn() !== false;
    }
}
