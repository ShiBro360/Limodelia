<?php
declare(strict_types=1);

namespace App\Helpers;

final class ResponseHelper
{
    public static function redirect(string $path, array $params = []): never
    {
        $query = $params ? '?' . http_build_query($params) : '';
        header('Location: ' . url($path) . $query);
        exit;
    }
}
