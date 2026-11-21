<?php
declare(strict_types=1);

namespace App\Validators;

final class BackupValidator
{
    public function validateRestore(array $input, array $availableFiles): array
    {
        $errors = [];
        $file = basename((string) ($input['backup_file'] ?? ''));
        $confirmation = trim((string) ($input['confirmation'] ?? ''));

        if ($file === '') {
            $errors[] = 'Wybierz plik kopii zapasowej.';
        } elseif (!in_array($file, $availableFiles, true)) {
            $errors[] = 'Wybrana kopia zapasowa nie istnieje.';
        }

        if ($confirmation !== 'RESTORE') {
            $errors[] = 'Aby przywrócić kopię, wpisz RESTORE.';
        }

        return $errors;
    }
}
