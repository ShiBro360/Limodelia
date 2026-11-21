<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!csrf_token_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (strlen($newPassword) < 12) {
        $error = 'Nowe hasło musi mieć co najmniej 12 znaków.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Nowe hasła nie są takie same.';
    } else {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id AND status = \'active\' LIMIT 1');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            $error = 'Aktualne hasło jest nieprawidłowe.';
        } else {
            $update = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $update->execute([
                ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => (int) $_SESSION['user_id'],
            ]);
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $success = 'Hasło zostało zmienione.';
        }
    }
}

render_header('Zmiana hasła');
?>
<main class="auth-page">
    <form class="auth-card card" method="post" action="<?= url('change_password.php') ?>">
        <div class="brand auth-brand">
            <span class="brand-mark">L</span>
            <span><strong>Limodelia</strong><small>CRM</small></span>
        </div>
        <h1>Zmiana hasła</h1>
        <?php if ($error !== ''): ?>
            <p class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <p class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <label class="form-field">
            <span class="form-label">Aktualne hasło</span>
            <input class="input" type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label class="form-field">
            <span class="form-label">Nowe hasło</span>
            <input class="input" type="password" name="new_password" minlength="12" autocomplete="new-password" required>
        </label>
        <label class="form-field">
            <span class="form-label">Powtórz nowe hasło</span>
            <input class="input" type="password" name="confirm_password" minlength="12" autocomplete="new-password" required>
        </label>
        <button class="btn btn-primary" type="submit">Zapisz nowe hasło</button>
        <a class="btn btn-secondary" href="<?= url('pages/dashboard.php') ?>">Wróć do panelu</a>
    </form>
</main>
<?php
render_footer_scripts();
