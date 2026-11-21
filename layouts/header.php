<?php
declare(strict_types=1);

function render_header(string $title = 'Limodelia CRM'): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $ui = \App\Services\SettingsService::defaultUi();
    $faviconUrl = url('assets/images/favicon.svg');
    try {
        $settingsService = new \App\Services\SettingsService(new \App\Repositories\SettingsRepository(db()));
        $ui = $settingsService->ui();
        $faviconPath = trim((string) ($settingsService->school()['favicon_url'] ?? ''));
        if ($faviconPath !== '') {
            $faviconUrl = preg_match('#^(https?://|/)#', $faviconPath) === 1 ? $faviconPath : url($faviconPath);
        }
    } catch (Throwable) {
    }
    $defaultTheme = (string) ($ui['default_theme'] ?? 'light');
    $palettes = (array) ($ui['palettes'] ?? []);
    $lightPalette = (array) ($palettes['light'] ?? []);
    $darkPalette = (array) ($palettes['dark'] ?? []);
    $cssFiles = [
        'variables.css',
        'themes.css',
        'layout.css',
        'components.css',
    ];
    ?>
<!doctype html>
<html lang="pl" data-theme="<?= htmlspecialchars($defaultTheme, ENT_QUOTES, 'UTF-8') ?>" data-default-theme="<?= htmlspecialchars($defaultTheme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $safeTitle ?> | Limodelia CRM</title>
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($cssFiles as $cssFile): ?>
        <?php $cssVersion = (string) filemtime(__DIR__ . '/../assets/css/' . $cssFile); ?>
        <link rel="stylesheet" href="<?= url('assets/css/' . $cssFile) ?>?v=<?= $cssVersion ?>">
    <?php endforeach; ?>
    <style id="custom-theme-palette">
        :root {
            --color-bg: <?= htmlspecialchars((string) ($lightPalette['background'] ?? '#F7F5F2'), ENT_QUOTES, 'UTF-8') ?>;
            --color-card: <?= htmlspecialchars((string) ($lightPalette['surface'] ?? '#FFFFFF'), ENT_QUOTES, 'UTF-8') ?>;
            --color-primary: <?= htmlspecialchars((string) ($lightPalette['primary'] ?? '#C89B3C'), ENT_QUOTES, 'UTF-8') ?>;
            --color-accent: <?= htmlspecialchars((string) ($lightPalette['accent'] ?? '#E25D79'), ENT_QUOTES, 'UTF-8') ?>;
            --color-primary-hover: color-mix(in srgb, var(--color-primary) 72%, var(--color-accent));
            --color-primary-soft: color-mix(in srgb, var(--color-primary) 16%, transparent);
        }
        :root[data-theme="dark"] {
            --color-bg: <?= htmlspecialchars((string) ($darkPalette['background'] ?? '#171312'), ENT_QUOTES, 'UTF-8') ?>;
            --color-card: <?= htmlspecialchars((string) ($darkPalette['surface'] ?? '#231A18'), ENT_QUOTES, 'UTF-8') ?>;
            --color-primary: <?= htmlspecialchars((string) ($darkPalette['primary'] ?? '#7A4A32'), ENT_QUOTES, 'UTF-8') ?>;
            --color-accent: <?= htmlspecialchars((string) ($darkPalette['accent'] ?? '#CF78AF'), ENT_QUOTES, 'UTF-8') ?>;
            --color-primary-hover: color-mix(in srgb, var(--color-primary) 72%, var(--color-accent));
            --color-primary-soft: color-mix(in srgb, var(--color-primary) 18%, transparent);
        }
    </style>
</head>
<body>
    <?php
}
