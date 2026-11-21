<?php
declare(strict_types=1);

function render_footer(): void
{
    ?>
<footer class="footer">
    <span>&copy; <?= date('Y') ?> Limodelia CRM</span>
    <span>Wersja interfejsu 0.1.0</span>
</footer>
    <?php
}

function render_footer_scripts(): void
{
    $scriptVersion = static function (string $path): string {
        $fullPath = __DIR__ . '/../' . $path;

        return is_file($fullPath) ? '?v=' . filemtime($fullPath) : '';
    };
    ?>
<script src="<?= url('assets/js/theme.js') ?><?= $scriptVersion('assets/js/theme.js') ?>"></script>
<script src="<?= url('assets/js/ui.js') ?><?= $scriptVersion('assets/js/ui.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?><?= $scriptVersion('assets/js/app.js') ?>"></script>
</body>
</html>
    <?php
}
