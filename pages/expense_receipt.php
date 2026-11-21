<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$expense = (new \App\Repositories\ExpenseRepository(db()))->find((int) ($_GET['id'] ?? 0));
$path = (string) ($expense['receipt_path'] ?? '');
if (!preg_match('~^uploads/expenses/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path) || !is_file(BASE_PATH . '/' . $path)) {
    http_response_code(404);
    exit('Nie znaleziono zdjęcia wydatku.');
}
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($path, PATHINFO_EXTENSION)];
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: inline; filename="expense-receipt.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('Content-Length: ' . filesize(BASE_PATH . '/' . $path));
readfile(BASE_PATH . '/' . $path);
