<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Services\BackupService;
use App\Validators\BackupValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$backupService = null;

try {
    $backupService = new BackupService(db(), new BackupValidator());

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $result = $backupService->createBackup();
            ResponseHelper::redirect('pages/backups.php', ['saved' => 'created', 'file' => $result['file']]);
        }

        if ($action === 'restore') {
            $errors = $backupService->restore($_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/backups.php', ['saved' => 'restored']);
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd kopii zapasowych: ' . $exception->getMessage();
}

function backup_size(int|float $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 2, ',', ' ') . ' MB';
    }

    return number_format($bytes / 1024, 2, ',', ' ') . ' KB';
}

render_page('Kopie zapasowe', 'backups', function () use ($backupService, $errors, $notice): void {
    $history = $backupService instanceof BackupService ? $backupService->history() : [];
    $selectedFile = (string) ($_POST['backup_file'] ?? ($history[0]['file'] ?? ''));
    ?>
    <section class="module-hero">
        <div>
            <h1>Kopie zapasowe</h1>
            <p>Twórz i przywracaj lokalne kopie bezpieczeństwa przed dalszymi pracami produkcyjnymi.</p>
        </div>
        <div class="module-actions">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <button class="btn btn-primary" type="submit">
                    <?= IconHelper::svg('add') ?>
                    Utwórz kopię
                </button>
            </form>
        </div>
    </section>

    <?php if ($notice === 'created'): ?>
        <div class="alert alert-info">Kopia zapasowa została utworzona<?= isset($_GET['file']) ? ': ' . FormatHelper::e((string) $_GET['file']) : '' ?>.</div>
    <?php elseif ($notice === 'restored'): ?>
        <div class="alert alert-info">Kopia zapasowa została przywrócona.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="backup-grid">
        <article class="card backup-card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Create backup</h2>
                    <p class="profile-note">Backup zawiera bazę MySQL, lokalne uploady, metadane, timestamp i wersję aplikacji.</p>
                </div>
            </div>
            <form method="post" class="backup-create-panel">
                <input type="hidden" name="action" value="create">
                <div class="backup-summary-list">
                    <span>MySQL database dump</span>
                    <strong>database.sql</strong>
                    <span>Lokalne pliki</span>
                    <strong>uploads/</strong>
                    <span>Metadane</span>
                    <strong>metadata.json</strong>
                    <span>Folder docelowy</span>
                    <strong>storage/backups/</strong>
                </div>
                <button class="btn btn-primary" type="submit"><?= IconHelper::svg('add') ?> Utwórz kopię zapasową</button>
            </form>
        </article>

        <article class="card backup-card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Restore backup</h2>
                    <p class="profile-note">Przywrócenie nadpisuje obecną bazę danych i lokalne uploady z archiwum.</p>
                </div>
            </div>
            <form method="post" class="settings-form">
                <input type="hidden" name="action" value="restore">
                <label class="form-field">
                    <span class="form-label">Plik kopii</span>
                    <select class="select" name="backup_file" required>
                        <?php if ($history === []): ?>
                            <option value="">Brak kopii</option>
                        <?php else: ?>
                            <?php foreach ($history as $backup): ?>
                                <option value="<?= FormatHelper::e($backup['file']) ?>" <?= $selectedFile === $backup['file'] ? 'selected' : '' ?>>
                                    <?= FormatHelper::e($backup['file']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Potwierdzenie</span>
                    <input class="input" name="confirmation" placeholder="Wpisz RESTORE" autocomplete="off" required>
                </label>
                <div class="alert alert-info">Uwaga: aktualna baza danych zostanie nadpisana. Tej operacji nie da się cofnąć bez kolejnej kopii.</div>
                <button class="btn btn-secondary" type="submit" <?= $history === [] ? 'disabled aria-disabled="true"' : '' ?>><?= IconHelper::svg('upload') ?> Przywróć kopię</button>
            </form>
        </article>
    </section>

    <section class="card module-panel backup-history-card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Backup history</h2>
                <p class="profile-note">Kopie są przechowywane lokalnie i nie są wystawiane jako publiczne linki.</p>
            </div>
        </div>

        <?php if ($history === []): ?>
            <section class="empty-state module-empty">
                <h2 class="empty-state-title">Brak kopii zapasowych</h2>
                <p class="empty-state-text">Utwórz pierwszą kopię przed większymi zmianami w systemie.</p>
            </section>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table students-table backup-table">
                    <thead>
                    <tr>
                        <th>Plik</th>
                        <th>Data</th>
                        <th>Rozmiar</th>
                        <th>Typ</th>
                        <th>Uploady</th>
                        <th>Wersja</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $backup): ?>
                        <tr>
                            <td><strong><?= FormatHelper::e($backup['file']) ?></strong></td>
                            <td><?= FormatHelper::e((string) $backup['created_at']) ?></td>
                            <td><?= FormatHelper::e(backup_size((int) $backup['size'])) ?></td>
                            <td><span class="badge badge-sand"><?= FormatHelper::e($backup['type']) ?></span></td>
                            <td><?= $backup['has_uploads'] ? 'Tak' : 'Nie' ?></td>
                            <td><?= FormatHelper::e($backup['app_version']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card backup-card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Safety notes</h2>
                <p class="profile-note">Moduł jest przygotowany pod panel administratora. Po wdrożeniu logowania dostęp powinien być ograniczony rolą admin/owner.</p>
            </div>
        </div>
        <div class="backup-notes">
            <p>Nie przenoś folderu <strong>storage/backups</strong> do publicznego hostingu bez dodatkowej ochrony serwera.</p>
            <p>Przed restore utwórz świeżą kopię aktualnego stanu. Restore nadpisuje bazę danych i lokalne pliki uploadów.</p>
            <p>Cloudinary przechowuje pliki zdalnie. Ten moduł zabezpiecza lokalne uploady i metadane w bazie, ale nie kopiuje zdalnych assetów Cloudinary.</p>
        </div>
    </section>
    <?php
});
