<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$error = '';

if (is_authenticated()) {
    header('Location: ' . url('pages/dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $now = time();
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if (!csrf_token_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Sesja formularza wygasła. Spróbuj ponownie.';
    } elseif ($lockedUntil > $now) {
        $error = 'Zbyt wiele prób. Spróbuj ponownie za chwilę.';
    } elseif ($email === '' || $password === '') {
        $error = 'Wpisz adres e-mail i hasło.';
    } else {
        $stmt = db()->prepare(
            "SELECT id, name, email, password_hash
             FROM users
             WHERE email = :email AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (is_array($user) && password_verify($password, (string) $user['password_hash'])) {
            $rememberLogin = new \App\Services\RememberLoginService(db());
            $rememberLogin->revoke((string) ($_COOKIE[\App\Services\RememberLoginService::COOKIE] ?? ''));
            \App\Services\RememberLoginService::cookie(isset($_POST['remember_me']) ? $rememberLogin->issue($user) : '');
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = (string) $user['name'];
            $_SESSION['user_email'] = (string) $user['email'];
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            header('Location: ' . url('pages/dashboard.php'));
            exit;
        }

        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_locked_until'] = $now + 60;
        }
        usleep(350000);
        $error = 'Nieprawidłowy adres e-mail lub hasło.';
    }
}

render_header('Logowanie');
?>
<main class="auth-page">
    <form class="auth-card card" method="post" action="<?= url('login.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="brand auth-brand">
            <span class="brand-mark">L</span>
            <span>
                <strong>Limodelia</strong>
                <small>CRM</small>
            </span>
        </div>
        <h1>Logowanie do CRM</h1>
        <?php if ($error !== ''): ?>
            <p class="alert alert-info"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <label class="form-field">
            <span class="form-label">Adres e-mail</span>
            <input class="input" type="email" name="email" autocomplete="username" required autofocus>
        </label>
        <label class="form-field">
            <span class="form-label">Hasło</span>
            <input class="input" type="password" name="password" autocomplete="current-password" required>
        </label>
        <label class="settings-toggle">
            <input type="checkbox" name="remember_me" value="1" <?= isset($_POST['remember_me']) ? 'checked' : '' ?>>
            <span>Zapamiętaj mnie na 30 dni</span>
        </label>
        <button class="btn btn-primary" type="submit">Zaloguj</button>
    </form>
</main>
<?php
render_footer_scripts();
