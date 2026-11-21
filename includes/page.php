<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function render_page(string $title, string $activePage, callable $content): void
{
    render_header($title);
    echo '<div class="app-shell">';
    render_sidebar($activePage);
    echo '<div class="mobile-backdrop" data-sidebar-backdrop></div>';
    echo '<main class="main">';
    render_topbar($title);
    echo '<section class="content"><div class="content-inner">';
    $content();
    echo '</div></section>';
    echo '</main>';
    echo '</div>';
    render_footer_scripts();
}
