<?php
declare(strict_types=1);

use App\Repositories\SettingsRepository;
use App\Helpers\IconHelper;
use App\Services\SettingsService;

function sidebar_school_brand(): array
{
    try {
        $school = (new SettingsService(new SettingsRepository(db())))->school();

        return [
            'name' => trim((string) ($school['name'] ?? '')) ?: 'Limodelia',
            'logo_url' => trim((string) ($school['logo_url'] ?? '')),
            'logo_dark_url' => trim((string) ($school['logo_dark_url'] ?? '')),
        ];
    } catch (Throwable) {
        return [
            'name' => 'Limodelia',
            'logo_url' => '',
            'logo_dark_url' => '',
        ];
    }
}

function sidebar_logo_src(string $path): string
{
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?://|/)#', $path) === 1) {
        return $path;
    }

    return url($path);
}

function render_sidebar(string $activePage = 'dashboard'): void
{
    $schoolBrand = sidebar_school_brand();
    $items = [
        'dashboard' => ['Panel', 'pages/dashboard.php', 'rent'],
        'students' => ['Uczennice', 'pages/students.php', 'users'],
        'payments' => ['Płatności', 'pages/payments.php', 'card'],
        'expenses' => ['Wydatki', 'pages/expenses.php', 'wallet'],
        'marketing' => ['Marketing', 'pages/marketing.php', 'trip'],
        'trips' => ['Wyjazdy', 'pages/trips.php', 'equipment'],
        'salaries' => ['Wynagrodzenia', 'pages/salaries.php', 'money'],
        'reports' => ['Raporty', 'pages/reports.php', 'profit'],
        'backups' => ['Kopie zapasowe', 'pages/backups.php', 'document'],
        'settings' => ['Ustawienia', 'pages/settings.php', 'settings'],
    ];
    ?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= url('pages/dashboard.php') ?>">
        <?php if ($schoolBrand['logo_url'] !== '' || $schoolBrand['logo_dark_url'] !== ''): ?>
            <?php $lightLogo = $schoolBrand['logo_url'] !== '' ? $schoolBrand['logo_url'] : $schoolBrand['logo_dark_url']; ?>
            <?php $darkLogo = $schoolBrand['logo_dark_url'] !== '' ? $schoolBrand['logo_dark_url'] : $lightLogo; ?>
            <span class="brand-logo-stack">
                <img class="brand-logo brand-logo-light" src="<?= htmlspecialchars(sidebar_logo_src($lightLogo), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($schoolBrand['name'], ENT_QUOTES, 'UTF-8') ?>">
                <img class="brand-logo brand-logo-dark" src="<?= htmlspecialchars(sidebar_logo_src($darkLogo), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($schoolBrand['name'], ENT_QUOTES, 'UTF-8') ?>">
            </span>
        <?php else: ?>
            <span class="brand-mark">L</span>
            <span>
                <strong><?= htmlspecialchars($schoolBrand['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small>CRM</small>
            </span>
        <?php endif; ?>
    </a>
    <nav class="sidebar-nav" aria-label="Nawigacja główna">
        <?php foreach ($items as $key => [$label, $href, $icon]): ?>
            <a class="nav-link <?= $activePage === $key ? 'is-active' : '' ?>" href="<?= url($href) ?>">
                <span class="nav-icon" aria-hidden="true"><?= IconHelper::svg($icon) ?></span>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
    <?php
}
