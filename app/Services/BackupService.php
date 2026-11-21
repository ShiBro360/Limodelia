<?php
declare(strict_types=1);

namespace App\Services;

use App\Validators\BackupValidator;
use PDO;
use RuntimeException;
use Throwable;

final class BackupService
{
    private string $backupDir;

    public function __construct(
        private readonly PDO $pdo,
        private readonly BackupValidator $validator
    ) {
        $this->backupDir = BASE_PATH . '/storage/backups';
        $this->ensureBackupDirectory();
    }

    public function createBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $name = 'limodelia_backup_' . $timestamp;
        $workDir = BASE_PATH . '/storage/temp_backup_' . bin2hex(random_bytes(4));
        $zipPath = $this->backupDir . '/' . $name . '.zip';

        try {
            $this->mkdir($workDir);
            $dumpPath = $workDir . '/database.sql';
            $this->createDatabaseDump($dumpPath);
            $this->writeMetadata($workDir . '/metadata.json', $timestamp);
            $this->copyUploads($workDir . '/uploads');
            $this->makeZip($workDir, $zipPath);

            return [
                'success' => true,
                'file' => basename($zipPath),
                'path' => $zipPath,
                'size' => filesize($zipPath) ?: 0,
            ];
        } finally {
            $this->removeDirectory($workDir);
        }
    }

    public function history(): array
    {
        $files = glob($this->backupDir . '/limodelia_backup_*.zip') ?: [];
        rsort($files);

        return array_map(function (string $path): array {
            $metadata = $this->readMetadataFromBackup($path);

            return [
                'file' => basename($path),
                'path' => $path,
                'size' => filesize($path) ?: 0,
                'created_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
                'type' => 'ZIP',
                'app_version' => (string) ($metadata['app_version'] ?? 'unknown'),
                'has_uploads' => (bool) ($metadata['has_uploads'] ?? false),
            ];
        }, $files);
    }

    public function restore(array $input): array
    {
        $available = array_column($this->history(), 'file');
        $errors = $this->validator->validateRestore($input, $available);
        if ($errors !== []) {
            return $errors;
        }

        $file = basename((string) $input['backup_file']);
        $backupPath = $this->backupDir . '/' . $file;
        $workDir = BASE_PATH . '/storage/temp_restore_' . bin2hex(random_bytes(4));

        try {
            $this->mkdir($workDir);
            $this->extractZip($backupPath, $workDir);
            $sqlPath = $workDir . '/database.sql';
            if (!is_file($sqlPath)) {
                return ['Kopia zapasowa nie zawiera pliku database.sql.'];
            }

            $this->restoreDatabase($sqlPath);
            if (is_dir($workDir . '/uploads')) {
                $this->restoreUploads($workDir . '/uploads');
            }

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się przywrócić kopii: ' . $exception->getMessage()];
        } finally {
            $this->removeDirectory($workDir);
        }
    }

    public function validateRestore(array $input): array
    {
        return $this->validator->validateRestore($input, array_column($this->history(), 'file'));
    }

    public function backupDirectory(): string
    {
        return $this->backupDir;
    }

    private function ensureBackupDirectory(): void
    {
        $this->mkdir($this->backupDir);
        $this->mkdir(BASE_PATH . '/storage');

        $htaccess = $this->backupDir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }

        $index = $this->backupDir . '/index.html';
        if (!is_file($index)) {
            file_put_contents($index, '');
        }
    }

    private function createDatabaseDump(string $targetPath): void
    {
        $mysqldump = $this->findExecutable('mysqldump.exe');
        if ($mysqldump !== null) {
            $command = [
                $mysqldump,
                '--host=' . DB_HOST,
                '--user=' . DB_USER,
                '--default-character-set=' . DB_CHARSET,
                '--single-transaction',
                '--routines',
                '--triggers',
                DB_NAME,
            ];

            if (DB_PASS !== '') {
                array_splice($command, 3, 0, ['--password=' . DB_PASS]);
            }

            $this->runCommand($command, $targetPath);
            if (is_file($targetPath) && filesize($targetPath) > 0) {
                return;
            }
        }

        file_put_contents($targetPath, $this->phpSqlExport());
    }

    private function restoreDatabase(string $sqlPath): void
    {
        $mysql = $this->findExecutable('mysql.exe');
        if ($mysql !== null) {
            $command = [
                $mysql,
                '--host=' . DB_HOST,
                '--user=' . DB_USER,
                '--default-character-set=' . DB_CHARSET,
                DB_NAME,
            ];

            if (DB_PASS !== '') {
                array_splice($command, 3, 0, ['--password=' . DB_PASS]);
            }

            $this->runCommand($command, null, $sqlPath);
            return;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->splitSqlStatements((string) file_get_contents($sqlPath)) as $statement) {
            $this->pdo->exec($statement);
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function phpSqlExport(): string
    {
        $sql = "-- Limodelia CRM PHP backup\n";
        $sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        $tables = $this->pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as $tableRow) {
            $table = (string) $tableRow[0];
            $sql .= "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`;\n";
        }

        foreach ($tables as $tableRow) {
            $table = (string) $tableRow[0];
            $create = $this->pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
            $sql .= "\n" . (string) $create['Create Table'] . ";\n\n";
            $stmt = $this->pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
                $values = array_map(fn ($value): string => $value === null ? 'NULL' : $this->pdo->quote((string) $value), array_values($row));
                $sql .= 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
            }
        }

        return $sql . "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }

    private function writeMetadata(string $path, string $timestamp): void
    {
        $appVersion = (string) ($this->pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'app.version' LIMIT 1")->fetchColumn() ?: '0.1.0');
        $metadata = [
            'app' => 'Limodelia CRM',
            'app_version' => $appVersion,
            'created_at' => date('Y-m-d H:i:s'),
            'timestamp' => $timestamp,
            'database' => DB_NAME,
            'php_version' => PHP_VERSION,
            'has_uploads' => is_dir(BASE_PATH . '/uploads') && (bool) (glob(BASE_PATH . '/uploads/*') ?: []),
        ];
        file_put_contents($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function readMetadataFromBackup(string $path): array
    {
        $workDir = BASE_PATH . '/storage/temp_meta_' . bin2hex(random_bytes(4));
        try {
            $this->mkdir($workDir);
            $this->extractZip($path, $workDir);
            $meta = $workDir . '/metadata.json';

            return is_file($meta) ? (json_decode((string) file_get_contents($meta), true) ?: []) : [];
        } catch (Throwable) {
            return [];
        } finally {
            $this->removeDirectory($workDir);
        }
    }

    private function copyUploads(string $target): void
    {
        $source = BASE_PATH . '/uploads';
        if (!is_dir($source)) {
            return;
        }

        $this->copyDirectory($source, $target);
    }

    private function restoreUploads(string $source): void
    {
        $target = BASE_PATH . '/uploads';
        $this->mkdir($target);
        $this->copyDirectory($source, $target);
    }

    private function makeZip(string $sourceDir, string $zipPath): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Nie udało się utworzyć archiwum ZIP.');
            }
            foreach ($this->files($sourceDir) as $file) {
                $zip->addFile($file, str_replace('\\', '/', substr($file, strlen($sourceDir) + 1)));
            }
            $zip->close();
            return;
        }

        $this->runPowerShellArchive('Compress-Archive', $sourceDir . '/*', $zipPath);
    }

    private function extractZip(string $zipPath, string $targetDir): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Nie udało się otworzyć archiwum ZIP.');
            }
            $zip->extractTo($targetDir);
            $zip->close();
            return;
        }

        $this->runPowerShellArchive('Expand-Archive', $zipPath, $targetDir);
    }

    private function runPowerShellArchive(string $command, string $source, string $target): void
    {
        $script = $command === 'Compress-Archive'
            ? 'Compress-Archive -Path ' . $this->psQuote($source) . ' -DestinationPath ' . $this->psQuote($target) . ' -Force'
            : 'Expand-Archive -LiteralPath ' . $this->psQuote($source) . ' -DestinationPath ' . $this->psQuote($target) . ' -Force';

        $output = [];
        $code = 0;
        exec('powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($script), $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Operacja ZIP nie powiodła się: ' . implode(' ', $output));
        }
    }

    private function runCommand(array $command, ?string $stdout = null, ?string $stdin = null): void
    {
        $cmd = implode(' ', array_map('escapeshellarg', $command));
        if ($stdin !== null) {
            $cmd .= ' < ' . escapeshellarg($stdin);
        }
        if ($stdout !== null) {
            $cmd .= ' > ' . escapeshellarg($stdout);
        }
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Polecenie systemowe nie powiodło się: ' . implode(' ', $output));
        }
    }

    private function findExecutable(string $name): ?string
    {
        $candidates = [
            dirname(PHP_BINARY) . '/../mysql/bin/' . $name,
            dirname(PHP_BINARY) . '/mysql/bin/' . $name,
            'D:/XAMPPL/mysql/bin/' . $name,
            'C:/xampp/mysql/bin/' . $name,
        ];

        foreach ($candidates as $candidate) {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;
            if (($char === '"' || $char === "'") && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = $quote === $char ? null : ($quote ?? $char);
            }
            if ($char === ';' && $quote === null) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    private function copyDirectory(string $source, string $target): void
    {
        $this->mkdir($target);
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . '/' . $item;
            $to = $target . '/' . $item;
            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ($this->files($dir, true) as $path) {
            is_dir($path) ? rmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function files(string $dir, bool $includeDirectories = false): array
    {
        $items = [];
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $items = array_merge($items, $this->files($path, $includeDirectories));
                if ($includeDirectories) {
                    $items[] = $path;
                }
            } else {
                $items[] = $path;
            }
        }

        return $items;
    }

    private function mkdir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Nie udało się utworzyć folderu: ' . $dir);
        }
    }

    private function psQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
