<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\ExpenseCategoryRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\TripPhotoRepository;
use App\Repositories\TripRepository;
use App\Services\CloudinaryService;
use App\Services\ExpenseService;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\TripService;
use App\Validators\ExpenseValidator;
use App\Validators\TripValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$tripService = null;
$methodRepository = null;

try {
    $pdo = db();
    $settingsService = new SettingsService(new SettingsRepository($pdo));
    $expenseCategoryRepository = new ExpenseCategoryRepository($pdo);
    $tripService = new TripService(
        new TripRepository($pdo),
        new TripPhotoRepository($pdo),
        new TripValidator(),
        new CloudinaryService($settingsService),
        new StorageService($settingsService),
        new ExpenseService(new ExpenseRepository($pdo), new ExpenseValidator()),
        $expenseCategoryRepository
    );
    $methodRepository = new PaymentMethodRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $tripId = (int) ($_POST['trip_id'] ?? 0);

        if ($action === 'create') {
            $errors = $tripService->save($_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', [
                    'year' => substr((string) ($_POST['start_date'] ?? date('Y-m-d')), 0, 4),
                    'saved' => 'created',
                ]);
            }
        } elseif ($action === 'update') {
            $errors = $tripService->save($_POST, $tripId);
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['saved' => 'updated']);
            }
        } elseif ($action === 'cancel') {
            $errors = $tripService->cancel($tripId);
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['saved' => 'cancelled']);
            }
        } elseif ($action === 'upload_photo') {
            $errors = $tripService->uploadPhoto($tripId, $_FILES['photo'] ?? [], (string) ($_POST['description'] ?? ''), isset($_POST['is_cover']));
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['modal' => 'details', 'id' => $tripId, 'saved' => 'photo']);
            }
        } elseif ($action === 'save_gallery') {
            $errors = $tripService->saveGallery($tripId, $_POST, $_FILES);
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['modal' => 'details', 'id' => $tripId, 'saved' => 'gallery']);
            }
        } elseif ($action === 'delete_photo') {
            $errors = $tripService->deletePhoto((int) ($_POST['photo_id'] ?? 0));
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['modal' => 'details', 'id' => $tripId, 'saved' => 'photo_deleted']);
            }
        } elseif ($action === 'set_cover') {
            $errors = $tripService->setCover($tripId, (int) ($_POST['photo_id'] ?? 0));
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['modal' => 'details', 'id' => $tripId, 'saved' => 'cover']);
            }
        } elseif ($action === 'add_expense') {
            $errors = $tripService->addExpense($tripId, $_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/trips.php', ['modal' => 'details', 'id' => $tripId, 'saved' => 'expense']);
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd modułu wyjazdów: ' . $exception->getMessage();
}

render_page('Wyjazdy', 'trips', function () use ($tripService, $methodRepository, $errors, $notice): void {
    $filters = [
        'search' => '',
        'year' => (int) date('Y'),
        'status' => '',
        'page' => 1,
        'per_page' => 10,
    ];
    $trips = [];
    $students = [];
    $methods = [];
    $total = 0;
    $pageCount = 1;
    $editTrip = null;
    $detailTrip = null;
    $galleryTrip = null;

    if ($tripService instanceof TripService) {
        try {
            $filters = $tripService->filters($_GET);
            $pagination = $tripService->paginated($filters);
            $trips = $pagination['trips'];
            $total = $pagination['total'];
            $pageCount = $pagination['page_count'];
            $filters = $pagination['filters'];
            $students = $tripService->students();
            $methods = $methodRepository instanceof PaymentMethodRepository ? $methodRepository->active() : [];

            if (($_GET['modal'] ?? '') === 'edit') {
                $editTrip = $tripService->details((int) ($_GET['id'] ?? 0));
            }
            if (($_GET['modal'] ?? '') === 'details') {
                $detailTrip = $tripService->details((int) ($_GET['id'] ?? 0));
            }

            $galleryTripId = (int) ($_GET['gallery_trip_id'] ?? ($trips[0]['id'] ?? 0));
            if ($galleryTripId > 0) {
                $galleryTrip = $tripService->details($galleryTripId);
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się pobrać wyjazdów: ' . $exception->getMessage();
        }
    }

    $modal = (string) ($_GET['modal'] ?? '');
    $showEditModal = ($modal === 'edit' && $editTrip) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $errors !== []);
    $formTrip = $showEditModal ? ($editTrip ?: $_POST) : $_POST;
    ?>
    <section class="module-hero trips-hero">
        <div>
            <h1>Wyjazdy</h1>
        </div>
        <div class="module-actions">
            <button class="btn btn-secondary" type="button" disabled><?= IconHelper::svg('calendar') ?> Widok kalendarza</button>
            <a class="btn btn-primary" href="#trip-create-form"><?= IconHelper::svg('add') ?> Dodaj wyjazd</a>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Zmiany zostały zapisane.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="trips-page-grid">
        <div class="trips-main-stack">
            <section class="card module-panel trips-list-card">
                <form class="trips-filter-bar" action="<?= FormatHelper::e(url('pages/trips.php')) ?>" method="get" aria-label="Filtry wyjazdów">
                    <label class="form-field">
                        <span class="form-label">Rok</span>
                        <select class="select" name="year">
                            <?php $currentYear = (int) ($filters['year'] ?? date('Y')); ?>
                            <?php for ($year = ((int) date('Y')) - 1; $year <= ((int) date('Y')) + 2; $year++): ?>
                                <option value="<?= $year ?>" <?= $currentYear === $year ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <option value="">Wszystkie</option>
                            <?php foreach (trip_status_options() as $value => $label): ?>
                                <option value="<?= FormatHelper::e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field trips-search-field">
                        <span class="form-label">Szukaj</span>
                        <span class="search-field"><input type="search" name="search" value="<?= FormatHelper::e($filters['search']) ?>" placeholder="Szukaj wyjazdu..."></span>
                    </label>
                    <button class="btn btn-secondary" type="submit"><?= IconHelper::svg('filter') ?> Filtruj</button>
                    <a class="btn btn-ghost" href="<?= FormatHelper::e(url('pages/trips.php')) ?>">Reset filtrów</a>
                </form>

                <?php if ($trips === []): ?>
                    <section class="empty-state module-empty">
                        <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('trip') ?></div>
                        <h2 class="empty-state-title">Brak wyjazdów</h2>
                        <p class="empty-state-text">Dodaj pierwszy wyjazd i przypisz uczestniczki.</p>
                    </section>
                <?php else: ?>
                    <div class="table-wrap trips-table-wrap">
                        <table class="table trips-reference-table">
                            <thead>
                                <tr>
                                    <th>Nazwa wyjazdu</th>
                                    <th>Data</th>
                                    <th>Miejsce</th>
                                    <th>Uczestniczki</th>
                                    <th>Łączny budżet</th>
                                    <th>Koszt/osobę</th>
                                    <th>Status</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $trip): ?>
                                    <?php
                                    $participantCount = max(1, (int) $trip['participant_count']);
                                    $plannedCost = (float) ($trip['internal_cost'] ?? 0);
                                    $actualCost = (float) ($trip['expense_total'] ?? 0);
                                    $totalCost = $plannedCost > 0 ? $plannedCost : $actualCost;
                                    ?>
                                    <tr class="<?= $galleryTrip && (int) $galleryTrip['id'] === (int) $trip['id'] ? 'is-selected' : '' ?>" data-row-link="<?= FormatHelper::e(url('pages/trips.php?gallery_trip_id=' . $trip['id'])) ?>" tabindex="0">
                                        <td>
                                            <a class="trip-name-cell" href="<?= FormatHelper::e(url('pages/trips.php?gallery_trip_id=' . $trip['id'])) ?>">
                                                <span class="trip-thumb">
                                                    <?php if (!empty($trip['cover_url'])): ?>
                                                        <img src="<?= FormatHelper::e((string) $trip['cover_url']) ?>" alt="">
                                                    <?php else: ?>
                                                        <?= IconHelper::svg('trip') ?>
                                                    <?php endif; ?>
                                                </span>
                                                <strong><?= FormatHelper::e($trip['name']) ?></strong>
                                            </a>
                                        </td>
                                        <td><?= FormatHelper::e(trip_date_range($trip['start_date'], $trip['end_date'])) ?></td>
                                        <td><?= FormatHelper::e($trip['location'] ?: '-') ?></td>
                                        <td><strong><?= FormatHelper::e((string) $trip['participant_count']) ?></strong></td>
                                        <td><?= FormatHelper::e(FormatHelper::money($totalCost, $trip['currency'])) ?></td>
                                        <td><?= FormatHelper::e(FormatHelper::money($totalCost / $participantCount, $trip['currency'])) ?></td>
                                        <td><span class="badge badge-dot <?= FormatHelper::e(trip_badge((string) $trip['status'])) ?>"><?= FormatHelper::e(trip_label((string) $trip['status'])) ?></span></td>
                                        <td>
                                            <div class="row-actions">
                                                <a class="btn btn-secondary btn-icon btn-sm" href="<?= FormatHelper::e(url('pages/trips.php?modal=edit&id=' . $trip['id'])) ?>" aria-label="Edytuj"><?= IconHelper::svg('edit') ?></a>
                                                <form method="post" onsubmit="return confirm('Anulować wyjazd?');">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="trip_id" value="<?= FormatHelper::e((string) $trip['id']) ?>">
                                                    <button class="btn btn-secondary btn-icon btn-sm" type="submit" aria-label="Anuluj"><?= IconHelper::svg('cancel') ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <footer class="table-footer trips-table-footer">
                        <p>Wyświetlanie <?= FormatHelper::e((string) (count($trips) === 0 ? 0 : (($filters['page'] - 1) * $filters['per_page'] + 1))) ?>-<?= FormatHelper::e((string) min($total, ($filters['page'] - 1) * $filters['per_page'] + count($trips))) ?> z <?= FormatHelper::e((string) $total) ?> wyjazdów</p>
                    </footer>
                <?php endif; ?>
            </section>

            <section class="card trips-gallery-panel">
                <div class="card-header trips-gallery-header">
                    <h2 class="card-title">Zdjęcia z wyjazdu</h2>
                    <form action="<?= FormatHelper::e(url('pages/trips.php')) ?>" method="get">
                        <select class="select" name="gallery_trip_id" onchange="this.form.submit()">
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= FormatHelper::e((string) $trip['id']) ?>" <?= $galleryTrip && (int) $galleryTrip['id'] === (int) $trip['id'] ? 'selected' : '' ?>>
                                    <?= FormatHelper::e($trip['name'] . ' (' . trip_date_range($trip['start_date'], $trip['end_date']) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ($galleryTrip): ?>
                        <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/trips.php?modal=details&id=' . $galleryTrip['id'])) ?>"><?= IconHelper::svg('upload') ?> Dodaj zdjęcia</a>
                    <?php endif; ?>
                </div>
                <?php $photos = $galleryTrip['photos'] ?? []; ?>
                <?php if ($photos === []): ?>
                    <section class="empty-state trips-gallery-empty">
                        <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('gallery') ?></div>
                        <p class="empty-state-text">Brak zdjęć dla wybranego wyjazdu.</p>
                    </section>
                <?php else: ?>
                    <div class="trips-photo-grid" data-trip-justified-gallery data-details-url="<?= FormatHelper::e(url('pages/trips.php?modal=details&id=' . ($galleryTrip['id'] ?? 0))) ?>">
                        <?php foreach ($photos as $photo): ?>
                            <?php
                            $photoWidth = (int) ($photo['width'] ?? 0);
                            $photoHeight = (int) ($photo['height'] ?? 0);
                            $photoAspect = $photoWidth > 0 && $photoHeight > 0 ? round($photoWidth / $photoHeight, 4) : 1.7778;
                            ?>
                            <a class="trips-photo-tile" href="<?= FormatHelper::e($photo['secure_url']) ?>" target="_blank" rel="noopener" data-aspect="<?= FormatHelper::e((string) $photoAspect) ?>" data-photo-url="<?= FormatHelper::e($photo['secure_url']) ?>">
                                <img src="<?= FormatHelper::e($photo['thumbnail_url'] ?: $photo['secure_url']) ?>" alt="<?= FormatHelper::e($photo['description'] ?: $photo['file_name']) ?>">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="card trips-create-card" id="trip-create-form">
            <div class="card-header">
                <h2 class="card-title">Dodaj wyjazd</h2>
            </div>
            <form class="trips-create-form" method="post">
                <input type="hidden" name="action" value="create">
                <label class="form-field">
                    <span class="form-label">Nazwa wyjazdu <strong>*</strong></span>
                    <input class="input" name="name" placeholder="np. Zakopane - sesja zimowa" required>
                </label>
                <div class="trips-form-row">
                    <label class="form-field">
                        <span class="form-label">Data od <strong>*</strong></span>
                        <input class="input" type="date" name="start_date" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data do</span>
                        <input class="input" type="date" name="end_date">
                    </label>
                </div>
                <label class="form-field">
                    <span class="form-label">Miejsce <strong>*</strong></span>
                    <input class="input" name="location" placeholder="np. Zakopane" required>
                </label>
                <div class="trips-form-row">
                    <label class="form-field">
                        <span class="form-label">Łączny budżet</span>
                        <input class="input" type="number" min="0" step="0.01" name="internal_cost" placeholder="0,00 zł" data-trip-budget>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <option value="planned">Zaplanowany</option>
                            <option value="active">W trakcie</option>
                            <option value="completed">Zakończony</option>
                        </select>
                    </label>
                </div>
                <?php render_trip_participant_picker($students); ?>
                <div class="trip-cost-summary" data-trip-cost-summary>
                    <span>Uczestniczki: <strong data-trip-participant-count>0</strong></span>
                    <span>Koszt/osobę: <strong data-trip-cost-person>0,00 zł</strong></span>
                </div>
                <label class="form-field">
                    <span class="form-label">Opis wyjazdu</span>
                    <textarea class="textarea" name="description" placeholder="Napisz kilka słów o wyjeździe, celu, planie..."></textarea>
                </label>
                <div class="trips-upload-box">
                    <?= IconHelper::svg('upload') ?>
                    <strong>Zdjęcia dodasz po zapisaniu wyjazdu</strong>
                    <span>Galeria jest dostępna w szczegółach wyjazdu.</span>
                </div>
                <button class="btn btn-primary trips-save-btn" type="submit">Zapisz wyjazd</button>
            </form>
        </aside>
    </section>

    <?php if ($showEditModal) {
        render_trip_edit_modal($formTrip, $students);
    } ?>

    <?php if ($detailTrip) {
        render_trip_details_modal($detailTrip, $methods);
    } ?>
    <?php
});

function render_trip_participant_picker(array $students, array $selected = []): void
{
    $selected = array_map('intval', $selected);
    ?>
    <section class="trip-participant-picker" data-trip-participant-picker>
        <div class="trip-participant-head">
            <span class="form-label">Uczestniczki</span>
            <strong><span data-trip-selected-count><?= FormatHelper::e((string) count($selected)) ?></span> wybr.</strong>
        </div>
        <label class="search-field trip-participant-search">
            <input type="search" placeholder="Szukaj uczestniczki..." data-trip-participant-search>
        </label>
        <div class="trip-participant-list">
            <?php foreach ($students as $student): ?>
                <?php
                $studentId = (int) $student['id'];
                $name = trim((string) $student['first_name'] . ' ' . (string) $student['last_name']);
                ?>
                <label class="trip-participant-option" data-trip-participant-option data-search="<?= FormatHelper::e(strtolower($name)) ?>">
                    <input type="checkbox" name="participant_ids[]" value="<?= FormatHelper::e((string) $studentId) ?>" <?= in_array($studentId, $selected, true) ? 'checked' : '' ?>>
                    <?php if (!empty($student['photo_path'])): ?>
                        <img class="avatar avatar-sm avatar-photo" src="<?= FormatHelper::e(url($student['photo_path'])) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar avatar-sm"><?= FormatHelper::e(FormatHelper::initials((string) $student['first_name'], (string) $student['last_name'])) ?></span>
                    <?php endif; ?>
                    <span>
                        <strong><?= FormatHelper::e($name) ?></strong>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function render_trip_edit_modal(array $values, array $students): void
{
    $selected = array_map('intval', (array) ($values['participant_ids'] ?? $_POST['participant_ids'] ?? []));
    ?>
    <div class="modal-backdrop is-open">
        <section class="modal payment-modal">
            <div class="card-header">
                <h2 class="card-title">Edytuj wyjazd</h2>
                <a class="btn btn-ghost btn-icon" href="<?= FormatHelper::e(url('pages/trips.php')) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
            </div>
            <form class="payment-form-grid" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="trip_id" value="<?= FormatHelper::e((string) ($values['id'] ?? '')) ?>">
                <label class="form-field">
                    <span class="form-label">Nazwa</span>
                    <input class="input" name="name" value="<?= FormatHelper::e((string) ($values['name'] ?? '')) ?>" required>
                </label>
                <label class="form-field">
                    <span class="form-label">Data od</span>
                    <input class="input" type="date" name="start_date" value="<?= FormatHelper::e((string) ($values['start_date'] ?? date('Y-m-d'))) ?>" required>
                </label>
                <label class="form-field">
                    <span class="form-label">Data do</span>
                    <input class="input" type="date" name="end_date" value="<?= FormatHelper::e((string) ($values['end_date'] ?? '')) ?>">
                </label>
                <label class="form-field">
                    <span class="form-label">Miejsce</span>
                    <input class="input" name="location" value="<?= FormatHelper::e((string) ($values['location'] ?? '')) ?>" required>
                </label>
                <label class="form-field">
                    <span class="form-label">Łączny budżet</span>
                    <input class="input" type="number" min="0" step="0.01" name="internal_cost" value="<?= FormatHelper::e((string) ($values['internal_cost'] ?? '0.00')) ?>" data-trip-budget>
                </label>
                <label class="form-field">
                    <span class="form-label">Status</span>
                    <select class="select" name="status">
                        <?php foreach (trip_status_options() as $value => $label): ?>
                            <option value="<?= FormatHelper::e($value) ?>" <?= ($values['status'] ?? 'planned') === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="payment-form-wide">
                    <?php render_trip_participant_picker($students, $selected); ?>
                </div>
                <div class="trip-cost-summary payment-form-wide" data-trip-cost-summary>
                    <span>Uczestniczki: <strong data-trip-participant-count>0</strong></span>
                    <span>Koszt/osobę: <strong data-trip-cost-person>0,00 zł</strong></span>
                </div>
                <label class="form-field payment-form-wide">
                    <span class="form-label">Opis</span>
                    <textarea class="textarea" name="description"><?= FormatHelper::e((string) ($values['clean_description'] ?? $values['description'] ?? '')) ?></textarea>
                </label>
                <div class="payment-form-actions">
                    <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/trips.php')) ?>">Anuluj</a>
                    <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                </div>
            </form>
        </section>
    </div>
    <?php
}

function render_trip_details_modal(array $detailTrip, array $methods): void
{
    ?>
    <div class="modal-backdrop is-open">
        <section class="modal trip-details-modal">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= FormatHelper::e($detailTrip['name']) ?></h2>
                    <p class="profile-note"><?= FormatHelper::e(trip_date_range($detailTrip['start_date'], $detailTrip['end_date'])) ?> · <?= FormatHelper::e($detailTrip['location'] ?: 'Brak lokalizacji') ?></p>
                </div>
                <a class="btn btn-ghost btn-icon" href="<?= FormatHelper::e(url('pages/trips.php')) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
            </div>

            <section class="card trip-gallery-card trip-gallery-editor">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_gallery">
                    <input type="hidden" name="trip_id" value="<?= FormatHelper::e((string) $detailTrip['id']) ?>">
                    <div class="trip-gallery-toolbar">
                        <label class="trip-dropzone trip-dropzone-compact">
                            <?= IconHelper::svg('upload') ?>
                            <span>Przeciągnij zdjęcia tutaj lub kliknij, aby wybrać pliki<br><small>JPG, PNG, WEBP · maks. 10 MB na plik</small></span>
                            <input type="file" name="photos[]" accept="image/*" multiple>
                        </label>
                        <input class="input" name="description" placeholder="Opis nowych zdjęć (opcjonalnie)">
                        <label class="checkbox-row"><input type="checkbox" name="new_photos_cover" value="1"> Pierwsze nowe jako okładka</label>
                        <button class="btn btn-primary" type="submit"><?= IconHelper::svg('add') ?> Dodaj zdjęcia</button>
                    </div>

                    <?php if (($detailTrip['photos'] ?? []) === []): ?>
                        <section class="empty-state module-empty"><p class="empty-state-text">Brak zdjęć w galerii.</p></section>
                    <?php else: ?>
                        <div class="trip-gallery trip-gallery-edit-grid" data-trip-sortable-gallery>
                            <?php foreach ($detailTrip['photos'] as $photo): ?>
                                <article class="trip-photo-card trip-photo-edit-card" draggable="true">
                                    <input type="hidden" name="photo_order[]" value="<?= FormatHelper::e((string) $photo['id']) ?>">
                                    <div class="trip-photo-preview">
                                        <a href="<?= FormatHelper::e($photo['secure_url']) ?>" target="_blank" rel="noopener" aria-label="Podgląd zdjęcia">
                                            <img src="<?= FormatHelper::e($photo['thumbnail_url'] ?: $photo['secure_url']) ?>" alt="<?= FormatHelper::e($photo['description'] ?: $photo['file_name']) ?>">
                                        </a>
                                        <span class="trip-photo-drag" aria-hidden="true"><?= IconHelper::svg('more') ?></span>
                                        <label class="trip-photo-delete" title="Usuń zdjęcie">
                                            <input type="checkbox" name="delete_photo_ids[]" value="<?= FormatHelper::e((string) $photo['id']) ?>">
                                            <?= IconHelper::svg('cancel') ?>
                                        </label>
                                        <?php if ((int) $photo['is_cover'] === 1): ?>
                                            <span class="trip-photo-cover-badge"><?= IconHelper::svg('check') ?> Okładka</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="trip-photo-meta">
                                        <strong><?= FormatHelper::e($photo['description'] ?: 'Zdjęcie') ?></strong>
                                        <small><?= FormatHelper::e((string) $photo['width']) ?> x <?= FormatHelper::e((string) $photo['height']) ?></small>
                                    </div>
                                    <input class="input input-sm" name="photo_descriptions[<?= FormatHelper::e((string) $photo['id']) ?>]" value="<?= FormatHelper::e((string) ($photo['description'] ?? '')) ?>" placeholder="Opis zdjęcia...">
                                    <label class="checkbox-row trip-cover-choice">
                                        <input type="radio" name="cover_photo_id" value="<?= FormatHelper::e((string) $photo['id']) ?>" <?= (int) $photo['is_cover'] === 1 ? 'checked' : '' ?>>
                                        Ustaw jako okładkę
                                    </label>
                                </article>
                            <?php endforeach; ?>
                            <label class="trip-photo-card trip-photo-add-card">
                                <?= IconHelper::svg('add') ?>
                                <strong>Dodaj więcej zdjęć</strong>
                                <span>Przeciągnij lub kliknij</span>
                                <input type="file" name="photos[]" accept="image/*" multiple>
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="trip-gallery-actions">
                        <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/trips.php')) ?>">Anuluj</a>
                        <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                    </div>
                </form>
            </section>

            <section class="card dashboard-card-sm">
                <h3 class="card-title">Dodaj koszt wyjazdu</h3>
                <form class="payment-form-grid trip-expense-form" method="post">
                    <input type="hidden" name="action" value="add_expense">
                    <input type="hidden" name="trip_id" value="<?= FormatHelper::e((string) $detailTrip['id']) ?>">
                    <label class="form-field">
                        <span class="form-label">Data</span>
                        <input class="input" type="date" name="expense_date" value="<?= FormatHelper::e(date('Y-m-d')) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Kwota</span>
                        <input class="input" type="number" min="0.01" step="0.01" name="amount" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Metoda</span>
                        <select class="select" name="payment_method_id" required>
                            <option value="">Wybierz</option>
                            <?php foreach ($methods as $method): ?>
                                <option value="<?= FormatHelper::e((string) $method['id']) ?>"><?= FormatHelper::e($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Opis</span>
                        <input class="input" name="description" placeholder="Transport, nocleg, zaliczka">
                    </label>
                    <div class="payment-form-actions">
                        <button class="btn btn-primary" type="submit">Dodaj koszt</button>
                    </div>
                </form>
            </section>
        </section>
    </div>
    <?php
}

function trip_status_options(): array
{
    return [
        'planned' => 'Zaplanowany',
        'active' => 'W trakcie',
        'completed' => 'Zakończony',
        'cancelled' => 'Anulowany',
    ];
}

function trip_label(string $status): string
{
    return trip_status_options()[$status] ?? $status;
}

function trip_badge(string $status): string
{
    return match ($status) {
        'active' => 'badge-info',
        'completed' => 'badge-success',
        'planned' => 'badge-warning',
        'cancelled' => 'badge-muted',
        default => 'badge-sand',
    };
}

function trip_date_range(?string $start, ?string $end): string
{
    if (!$start) {
        return '-';
    }

    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $start);
    $endDate = $end ? DateTimeImmutable::createFromFormat('Y-m-d', $end) : null;
    if (!$startDate) {
        return $start;
    }
    if (!$endDate || $startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
        return $startDate->format('d.m.Y');
    }

    if ($startDate->format('m.Y') === $endDate->format('m.Y')) {
        return $startDate->format('d') . '-' . $endDate->format('d.m.Y');
    }

    return $startDate->format('d.m.Y') . '-' . $endDate->format('d.m.Y');
}
