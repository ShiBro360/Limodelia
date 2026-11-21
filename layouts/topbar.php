<?php
declare(strict_types=1);

function topbar_notifications(): array
{
    try {
        $pdo = db();
        $settingsRepository = new \App\Repositories\SettingsRepository($pdo);
        $settingsService = new \App\Services\SettingsService($settingsRepository);
        $storageService = new \App\Services\StorageService($settingsService);
        $studentRepository = new \App\Repositories\StudentRepository($pdo);
        $studentService = new \App\Services\StudentService(
            $studentRepository,
            new \App\Validators\StudentValidator(),
            $storageService,
            $settingsService->lessonSchedule()
        );
        $notificationService = new \App\Services\NotificationService(
            new \App\Repositories\NotificationRepository($pdo),
            $studentRepository,
            $studentService,
            $settingsService
        );

        $notificationService->sync();

        return [
            'count' => $notificationService->unreadCount(),
            'items' => $notificationService->recentUnread(6),
        ];
    } catch (\Throwable) {
        return [
            'count' => 0,
            'items' => [],
        ];
    }
}

function topbar_notification_link(array $notification): string
{
    $type = (string) ($notification['related_entity_type'] ?? '');
    $id = (int) ($notification['related_entity_id'] ?? 0);

    if ($type === 'student_zero_lessons' && $id > 0) {
        return url('pages/student_profile.php?id=' . $id);
    }

    if ($type === 'room_booking_reminder') {
        return url('pages/expenses.php?modal=add');
    }

    return '#';
}

function render_topbar(string $title): void
{
    $notifications = topbar_notifications();
    $notificationCount = (int) $notifications['count'];
    $notificationItems = (array) $notifications['items'];
    ?>
<header class="topbar">
    <button class="btn btn-secondary btn-icon menu-button" type="button" data-sidebar-toggle aria-label="Otworz menu">
        <?= \App\Helpers\IconHelper::svg('menu') ?>
    </button>
    <div class="topbar-search">
        <div class="search-field">
            <input type="search" placeholder="Szukaj..." aria-label="Szukaj">
        </div>
    </div>
    <div class="topbar-actions">
        <button class="btn btn-ghost btn-icon theme-toggle topbar-icon" type="button" data-theme-toggle aria-pressed="false" aria-label="Przelacz motyw">
            <?= \App\Helpers\IconHelper::svg('sun', 'svg-icon theme-icon theme-icon-sun') ?>
            <?= \App\Helpers\IconHelper::svg('moon', 'svg-icon theme-icon theme-icon-moon') ?>
            <span class="theme-toggle-label sr-only" data-theme-label>Tryb jasny</span>
        </button>
        <div class="notification-menu">
            <button class="btn btn-ghost btn-icon topbar-icon notification-button" type="button" aria-label="Powiadomienia" aria-expanded="false">
                <?= \App\Helpers\IconHelper::svg('bell') ?>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-count"><?= htmlspecialchars((string) min($notificationCount, 99), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-dropdown" role="dialog" aria-label="Powiadomienia">
                <div class="notification-dropdown-header">
                    <strong>Powiadomienia</strong>
                    <span><?= htmlspecialchars((string) $notificationCount, ENT_QUOTES, 'UTF-8') ?> nowe</span>
                </div>
                <?php if ($notificationItems === []): ?>
                    <div class="notification-empty">Brak nowych powiadomień.</div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notificationItems as $notification): ?>
                            <a class="notification-item notification-item-<?= htmlspecialchars((string) $notification['type'], ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars(topbar_notification_link($notification), ENT_QUOTES, 'UTF-8') ?>">
                                <span class="notification-item-icon" aria-hidden="true">
                                    <?= \App\Helpers\IconHelper::svg(($notification['related_entity_type'] ?? '') === 'room_booking_reminder' ? 'calendar' : 'bell') ?>
                                </span>
                                <span class="notification-item-body">
                                    <strong><?= htmlspecialchars((string) $notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <form class="notification-footer" method="post">
                        <input type="hidden" name="notification_action" value="mark_all_read">
                        <button class="btn btn-secondary btn-sm" type="submit">Oznacz jako przeczytane</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="topbar-user">
            <span class="avatar avatar-photo" aria-hidden="true">L</span>
            <span>
                <strong><?= htmlspecialchars(current_user_name(), ENT_QUOTES, 'UTF-8') ?></strong>
                <small><a href="<?= url('change_password.php') ?>">Zmień hasło</a></small>
            </span>
            <a class="btn btn-ghost btn-icon" href="<?= url('logout.php') ?>" aria-label="Wyloguj"><?= \App\Helpers\IconHelper::svg('logout') ?></a>
        </div>
    </div>
</header>
    <?php
}
