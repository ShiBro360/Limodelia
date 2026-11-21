<?php
declare(strict_types=1);

namespace App\Services;

final class ExpenseReceiptStorage
{
    public function store(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Nie udało się przesłać zdjęcia. Wybierz JPG, PNG lub WebP do 2 MB.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) || filesize($tmp) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Zdjęcie wydatku może mieć maksymalnie 2 MB.');
        }
        $image = @getimagesize($tmp);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$image || !isset($extensions[$mime]) || ($image['mime'] ?? '') !== $mime) {
            throw new \RuntimeException('Wybierz prawidłowe zdjęcie JPG, PNG lub WebP.');
        }
        $directory = BASE_PATH . '/uploads/expenses';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nie udało się przygotować katalogu zdjęć.');
        }
        $path = 'uploads/expenses/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, BASE_PATH . '/' . $path)) {
            throw new \RuntimeException('Nie udało się zapisać zdjęcia wydatku.');
        }
        chmod(BASE_PATH . '/' . $path, 0640);
        return $path;
    }

    public function discard(?string $path): void
    {
        if ($path !== null && preg_match('~^uploads/expenses/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) {
            $file = BASE_PATH . '/' . $path;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
