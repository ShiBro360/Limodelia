<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExpenseCategoryRepository;
use App\Repositories\TripPhotoRepository;
use App\Repositories\TripRepository;
use App\Validators\TripValidator;
use RuntimeException;
use Throwable;

final class TripService
{
    public function __construct(
        private readonly TripRepository $trips,
        private readonly TripPhotoRepository $photos,
        private readonly TripValidator $validator,
        private readonly CloudinaryService $cloudinary,
        private readonly StorageService $storage,
        private readonly ExpenseService $expenses,
        private readonly ExpenseCategoryRepository $categories
    ) {
    }

    public function filters(array $query): array
    {
        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'year' => (int) ($query['year'] ?? substr((string) ($query['month'] ?? date('Y-m')), 0, 4)),
            'status' => (string) ($query['status'] ?? ''),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => (int) ($query['per_page'] ?? 10),
        ];
        $currentYear = (int) date('Y');
        $filters['year'] = $filters['year'] >= 2000 && $filters['year'] <= $currentYear + 10 ? $filters['year'] : $currentYear;
        $filters['per_page'] = in_array($filters['per_page'], [10, 25, 50], true) ? $filters['per_page'] : 10;

        return $filters;
    }

    public function paginated(array $filters): array
    {
        $total = $this->trips->count($filters);
        $pageCount = max(1, (int) ceil($total / $filters['per_page']));
        $filters['page'] = min($filters['page'], $pageCount);

        return [
            'trips' => $this->trips->list($filters),
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => $filters,
        ];
    }

    public function details(int $id): ?array
    {
        $trip = $this->trips->find($id);
        if (!$trip) {
            return null;
        }

        $trip['participant_ids'] = $this->trips->participantIdsByTrip($id);
        $trip['participant_count'] = count($trip['participant_ids']);
        $trip['clean_description'] = (string) ($trip['description'] ?? '');
        $trip['photos'] = $this->photos->listByTrip($id);
        $trip['participant_rows'] = $this->trips->participantsByTrip($id);

        return $trip;
    }

    public function students(): array
    {
        return $this->trips->activeStudents();
    }

    public function save(array $input, ?int $id = null): array
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        try {
            $data = $this->normalizeTrip($input);
            $participantIds = $this->participantIdsFromInput($input);

            $this->trips->beginTransaction();
            $tripId = $id === null ? $this->trips->create($data) : $id;
            if ($id !== null) {
                $this->trips->update($id, $data);
            }
            $this->trips->syncParticipants($tripId, $participantIds);
            $this->trips->commit();

            return [];
        } catch (Throwable $exception) {
            $this->trips->rollBackIfActive();

            return ['Nie udało się zapisać wyjazdu: ' . $exception->getMessage()];
        }
    }

    public function cancel(int $id): array
    {
        try {
            $this->trips->cancel($id);

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się anulować wyjazdu: ' . $exception->getMessage()];
        }
    }

    public function uploadPhoto(int $tripId, array $file, string $description, bool $cover): array
    {
        try {
            $trip = $this->trips->find($tripId);
            if (!$trip) {
                return ['Nie znaleziono wyjazdu.'];
            }

            $folder = $cover ? $this->storage->tripCoverFolder($tripId) : $this->storage->tripGalleryFolder($tripId);
            $meta = $this->cloudinary->upload($file, $folder);
            $meta['trip_id'] = $tripId;
            $meta['description'] = trim($description) ?: null;
            $meta['sort_order'] = $this->photos->nextSortOrder($tripId);
            $meta['is_cover'] = $cover;

            $photoId = $this->photos->create($meta);
            if ($cover || empty($trip['cover_photo_id'])) {
                $this->photos->setCover($tripId, $photoId);
            }

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się przesłać zdjęcia: ' . $exception->getMessage()];
        }
    }

    public function deletePhoto(int $photoId): array
    {
        try {
            $photo = $this->photos->find($photoId);
            if (!$photo) {
                return ['Nie znaleziono zdjęcia.'];
            }

            $this->cloudinary->delete((string) $photo['cloudinary_public_id']);
            $this->photos->clearCoverIfNeeded($photoId);
            $this->photos->delete($photoId);

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się usunąć zdjęcia: ' . $exception->getMessage()];
        }
    }

    public function saveGallery(int $tripId, array $input, array $files): array
    {
        try {
            $trip = $this->trips->find($tripId);
            if (!$trip) {
                return ['Nie znaleziono wyjazdu.'];
            }

            foreach (array_values((array) ($input['photo_order'] ?? [])) as $sort => $photoId) {
                $this->photos->updateSortOrder((int) $photoId, $sort + 1);
            }

            foreach ((array) ($input['photo_descriptions'] ?? []) as $photoId => $description) {
                $this->photos->updateDescription((int) $photoId, trim((string) $description) ?: null);
            }

            $coverPhotoId = (int) ($input['cover_photo_id'] ?? 0);
            if ($coverPhotoId > 0) {
                $this->photos->setCover($tripId, $coverPhotoId);
            }

            foreach ((array) ($input['delete_photo_ids'] ?? []) as $photoId) {
                $deleteErrors = $this->deletePhoto((int) $photoId);
                if ($deleteErrors !== []) {
                    return $deleteErrors;
                }
            }

            $description = trim((string) ($input['description'] ?? ''));
            $newCover = isset($input['new_photos_cover']);
            foreach ($this->normalizeUploadedFiles($files['photos'] ?? []) as $index => $file) {
                $uploadErrors = $this->uploadPhoto($tripId, $file, $description, $newCover && $index === 0);
                if ($uploadErrors !== []) {
                    return $uploadErrors;
                }
            }

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się zapisać galerii: ' . $exception->getMessage()];
        }
    }

    public function setCover(int $tripId, int $photoId): array
    {
        try {
            $this->photos->setCover($tripId, $photoId);

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się ustawić okładki: ' . $exception->getMessage()];
        }
    }

    public function movePhoto(int $tripId, int $photoId, string $direction): array
    {
        $photos = $this->photos->listByTrip($tripId);
        $ids = array_column($photos, 'id');
        $index = array_search($photoId, $ids, true);
        if ($index === false) {
            return ['Nie znaleziono zdjęcia.'];
        }

        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($ids[$swap])) {
            return [];
        }

        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        foreach ($ids as $sort => $photoId) {
            $this->photos->updateSortOrder((int) $photoId, $sort + 1);
        }

        return [];
    }

    public function addExpense(int $tripId, array $input): array
    {
        $trip = $this->trips->find($tripId);
        if (!$trip) {
            return ['Nie znaleziono wyjazdu.'];
        }

        $categoryId = $this->tripCategoryId();
        $description = trim((string) ($input['description'] ?? ''));
        $input['expense_category_id'] = $categoryId;
        $input['trip_id'] = $tripId;
        $input['description'] = 'Wyjazd: ' . $trip['name'] . ($description !== '' ? ' - ' . $description : '');
        $input['status'] = $input['status'] ?? 'draft';

        return $this->expenses->create($input);
    }

    private function normalizeTrip(array $input): array
    {
        $notes = trim((string) ($input['notes'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        if ($notes !== '') {
            $description = trim($description . "\n\nNotatki: " . $notes);
        }

        return [
            'name' => trim((string) $input['name']),
            'location' => trim((string) ($input['location'] ?? '')),
            'start_date' => (string) $input['start_date'],
            'end_date' => (string) ($input['end_date'] ?: $input['start_date']),
            'capacity' => null,
            'internal_cost' => number_format((float) str_replace(',', '.', (string) ($input['internal_cost'] ?? 0)), 2, '.', ''),
            'status' => (string) ($input['status'] ?? 'planned'),
            'description' => $description !== '' ? $description : null,
        ];
    }

    private function participantIdsFromInput(array $input): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) ($input['participant_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
    }

    private function normalizeUploadedFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? [] : [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private function tripCategoryId(): int
    {
        foreach ($this->categories->active() as $category) {
            if (($category['slug'] ?? '') === 'wyjazdy') {
                return (int) $category['id'];
            }
        }

        throw new RuntimeException('Brak kategorii Wyjazdy.');
    }
}
