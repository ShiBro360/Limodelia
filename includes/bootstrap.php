<?php
declare(strict_types=1);

ini_set('default_charset', 'UTF-8');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('BASE_PATH', dirname(__DIR__));

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = str_replace('/pages', '', rtrim(dirname($scriptName), '/\\'));

if ($baseUrl === '/' || $baseUrl === '.' || $baseUrl === '\\') {
    $baseUrl = '';
}

define('BASE_URL', $baseUrl);

require_once BASE_PATH . '/config/db.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

foreach (glob(BASE_PATH . '/layouts/*.php') ?: [] as $layoutFile) {
    require_once $layoutFile;
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function current_user_name(): string
{
    return (string) ($_SESSION['user_name'] ?? 'Administrator');
}

function is_authenticated(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_token_is_valid(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_authentication(): void
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === 'login.php') {
        return;
    }

    if (!is_authenticated()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

if (PHP_SAPI !== 'cli') {
    if (!is_authenticated() && !empty($_COOKIE[\App\Services\RememberLoginService::COOKIE])) {
        try {
            $rememberedUser = (new \App\Services\RememberLoginService(db()))->restore((string) $_COOKIE[\App\Services\RememberLoginService::COOKIE]);
            if ($rememberedUser !== null) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $rememberedUser['id'];
                $_SESSION['user_name'] = $rememberedUser['name'];
                $_SESSION['user_email'] = $rememberedUser['email'];
            } else {
                \App\Services\RememberLoginService::cookie('');
            }
        } catch (Throwable) {
            // Database failures must never authenticate a remembered browser.
            error_log('Remembered login could not be restored.');
        }
    }
    require_authentication();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['notification_action'] ?? '') === 'mark_all_read') {
    try {
        (new \App\Repositories\NotificationRepository(db()))->markAllRead();
    } catch (Throwable) {
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? url('pages/dashboard.php');
    header('Location: ' . $redirect);
    exit;
}
