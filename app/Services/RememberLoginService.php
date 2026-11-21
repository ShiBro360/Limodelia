<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class RememberLoginService
{
    public const COOKIE = 'limodelia_remember';
    public const LIFETIME = 30 * 86400;

    public function __construct(private readonly PDO $pdo) {}

    public function issue(array $user): string
    {
        $this->pdo->prepare('DELETE FROM remembered_logins WHERE expires_at <= ?')->execute([time()]);
        $selector = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));
        $this->pdo->prepare('INSERT INTO remembered_logins (selector, token_hash, user_id, password_fingerprint, expires_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$selector, hash('sha256', $secret), (int) $user['id'], hash('sha256', $user['password_hash']), time() + self::LIFETIME]);
        return $selector . '.' . $secret;
    }

    public function restore(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D', $token)) { return null; }
        [$selector, $secret] = explode('.', $token);
        $stmt = $this->pdo->prepare("SELECT r.token_hash, r.password_fingerprint, u.id, u.name, u.email, u.password_hash FROM remembered_logins r JOIN users u ON u.id = r.user_id WHERE r.selector = ? AND r.expires_at > ? AND u.status = 'active'");
        $stmt->execute([$selector, time()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !hash_equals($user['token_hash'], hash('sha256', $secret)) || !hash_equals($user['password_fingerprint'], hash('sha256', $user['password_hash']))) { return null; }
        return ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']];
    }

    public function revoke(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D', $token)) { return; }
        [$selector, $secret] = explode('.', $token);
        $this->pdo->prepare('DELETE FROM remembered_logins WHERE selector = ? AND token_hash = ?')->execute([$selector, hash('sha256', $secret)]);
    }

    public static function cookie(string $token): void
    {
        setcookie(self::COOKIE, $token, [
            'expires' => $token === '' ? time() - 3600 : time() + self::LIFETIME,
            'path' => '/',
            'secure' => ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
