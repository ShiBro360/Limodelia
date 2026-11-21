<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

final class TripRepository
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
            "SELECT
                t.*,
                COALESCE(cover.thumbnail_url, cover.secure_url) AS cover_url,
                COUNT(DISTINCT tp.id) AS photo_count,
                COUNT(DISTINCT tpart.student_id) AS participant_count,
                COALESCE(SUM(CASE WHEN e.status NOT IN ('cancelled', 'rejected') THEN e.amount ELSE 0 END), 0) AS expense_total
             FROM trips t
             LEFT JOIN trip_photos cover ON cover.id = t.cover_photo_id
             LEFT JOIN trip_photos tp ON tp.trip_id = t.id
             LEFT JOIN trip_participants tpart ON tpart.trip_id = t.id AND tpart.status <> 'cancelled'
             LEFT JOIN expenses e ON e.trip_id = t.id
             {$whereSql}
             GROUP BY t.id
             ORDER BY t.start_date DESC, t.id DESC
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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM trips t {$whereSql}");
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trips WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO trips
                (name, location, start_date, end_date, capacity, price, internal_cost, currency, status, description, created_by)
             VALUES
                (:name, :location, :start_date, :end_date, :capacity, 0.00, :internal_cost, 'PLN', :status, :description, 1)"
        );
        $this->bindTripData($stmt, $data);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE trips
             SET name = :name,
                 location = :location,
                 start_date = :start_date,
                 end_date = :end_date,
                 capacity = :capacity,
                 internal_cost = :internal_cost,
                 status = :status,
                 description = :description
             WHERE id = :id"
        );
        $this->bindTripData($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cancel(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE trips SET status = 'cancelled' WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function activeStudents(): array
    {
        return $this->pdo
            ->query("SELECT id, first_name, last_name, phone FROM students WHERE status = 'active' ORDER BY last_name ASC, first_name ASC")
            ->fetchAll();
    }

    public function studentsByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, first_name, last_name FROM students WHERE id IN ({$placeholders}) ORDER BY last_name ASC, first_name ASC");
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    public function participantsByTrip(int $tripId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.id, s.first_name, s.last_name, tp.status, tp.notes
             FROM trip_participants tp
             INNER JOIN students s ON s.id = tp.student_id
             WHERE tp.trip_id = :trip_id
             ORDER BY s.last_name ASC, s.first_name ASC"
        );
        $stmt->execute([':trip_id' => $tripId]);

        return $stmt->fetchAll();
    }

    public function participantIdsByTrip(int $tripId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT student_id
             FROM trip_participants
             WHERE trip_id = :trip_id AND status <> 'cancelled'
             ORDER BY student_id ASC"
        );
        $stmt->execute([':trip_id' => $tripId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'student_id'));
    }

    public function syncParticipants(int $tripId, array $studentIds): void
    {
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0)));
        $this->pdo->prepare('DELETE FROM trip_participants WHERE trip_id = :trip_id')->execute([':trip_id' => $tripId]);

        if ($studentIds === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO trip_participants (trip_id, student_id, status)
             VALUES (:trip_id, :student_id, 'planned')"
        );

        foreach ($studentIds as $studentId) {
            $stmt->execute([
                ':trip_id' => $tripId,
                ':student_id' => $studentId,
            ]);
        }
    }

    public function monthCount(string $from, string $to): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM trips WHERE status <> 'cancelled' AND start_date BETWEEN :from AND :to");
        $stmt->execute([':from' => $from, ':to' => $to]);

        return (int) $stmt->fetchColumn();
    }

    public function monthExpenses(string $from, string $to): float
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

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(t.name LIKE :search OR t.location LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if ((int) ($filters['year'] ?? 0) > 0) {
            $where[] = 'YEAR(t.start_date) = :year';
            $params[':year'] = (int) $filters['year'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindTripData(PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':location', $data['location']);
        $stmt->bindValue(':start_date', $data['start_date']);
        $stmt->bindValue(':end_date', $data['end_date']);
        $stmt->bindValue(':capacity', $data['capacity'], $data['capacity'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':internal_cost', $data['internal_cost']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':description', $data['description']);
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
