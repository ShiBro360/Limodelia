<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'limodelia_crm';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $environment = [];
    $environmentFile = getenv('LIMODELIA_ENV_FILE') ?: '/etc/limodelia/limodelia.env';
    if (is_readable($environmentFile)) {
        $environment = parse_ini_file($environmentFile, false, INI_SCANNER_RAW) ?: [];
    }

    $host = getenv('LIMODELIA_DB_HOST') ?: ($environment['LIMODELIA_DB_HOST'] ?? DB_HOST);
    $name = getenv('LIMODELIA_DB_NAME') ?: ($environment['LIMODELIA_DB_NAME'] ?? DB_NAME);
    $user = getenv('LIMODELIA_DB_USER') ?: ($environment['LIMODELIA_DB_USER'] ?? DB_USER);
    $password = getenv('LIMODELIA_DB_PASSWORD');
    if ($password === false) {
        $password = $environment['LIMODELIA_DB_PASSWORD'] ?? DB_PASS;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $host,
        $name,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
