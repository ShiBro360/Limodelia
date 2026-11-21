<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TripPhotoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByTrip(int $tripId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM trip_photos WHERE trip_id = :trip_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':trip_id' => $tripId]);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trip_photos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO trip_photos
                (trip_id, cloudinary_public_id, folder, version, secure_url, thumbnail_url, original_url, file_name, file_size, width, height, format, description, sort_order, is_cover, uploaded_by)
             VALUES
                (:trip_id, :public_id, :folder, :version, :secure_url, :thumbnail_url, :original_url, :file_name, :file_size, :width, :height, :format, :description, :sort_order, :is_cover, 1)"
        );
        $stmt->execute([
            ':trip_id' => $data['trip_id'],
            ':public_id' => $data['public_id'],
            ':folder' => $data['folder'],
            ':version' => $data['version'],
            ':secure_url' => $data['secure_url'],
            ':thumbnail_url' => $data['thumbnail_url'],
            ':original_url' => $data['original_url'],
            ':file_name' => $data['file_name'],
            ':file_size' => $data['file_size'],
            ':width' => $data['width'],
            ':height' => $data['height'],
            ':format' => $data['format'],
            ':description' => $data['description'],
            ':sort_order' => $data['sort_order'],
            ':is_cover' => $data['is_cover'] ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function nextSortOrder(int $tripId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM trip_photos WHERE trip_id = :trip_id');
        $stmt->execute([':trip_id' => $tripId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM trip_photos WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function setCover(int $tripId, int $photoId): void
    {
        $this->pdo->prepare('UPDATE trip_photos SET is_cover = 0 WHERE trip_id = :trip_id')->execute([':trip_id' => $tripId]);
        $this->pdo->prepare('UPDATE trip_photos SET is_cover = 1 WHERE id = :id AND trip_id = :trip_id')->execute([
            ':id' => $photoId,
            ':trip_id' => $tripId,
        ]);
        $this->pdo->prepare('UPDATE trips SET cover_photo_id = :photo_id WHERE id = :trip_id')->execute([
            ':photo_id' => $photoId,
            ':trip_id' => $tripId,
        ]);
    }

    public function clearCoverIfNeeded(int $photoId): void
    {
        $photo = $this->find($photoId);
        if (!$photo || !(bool) $photo['is_cover']) {
            return;
        }

        $this->pdo->prepare('UPDATE trips SET cover_photo_id = NULL WHERE id = :trip_id')->execute([':trip_id' => $photo['trip_id']]);
    }

    public function updateSortOrder(int $photoId, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare('UPDATE trip_photos SET sort_order = :sort_order WHERE id = :id');
        $stmt->execute([':sort_order' => $sortOrder, ':id' => $photoId]);
    }

    public function updateDescription(int $photoId, ?string $description): void
    {
        $stmt = $this->pdo->prepare('UPDATE trip_photos SET description = :description WHERE id = :id');
        $stmt->execute([
            ':description' => $description,
            ':id' => $photoId,
        ]);
    }
}
