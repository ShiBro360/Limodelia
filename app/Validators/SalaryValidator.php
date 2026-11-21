<?php
declare(strict_types=1);

namespace App\Validators;

final class SalaryValidator
{
    public function validatePayout(array $input): array
    {
        $errors = [];

        if (!in_array(($input['employee'] ?? ''), ['teacher', 'accountant', 'marketing'], true)) {
            $errors[] = 'Pracownik jest wymagany.';
        }

        if (empty($input['month']) || !preg_match('/^\d{4}-\d{2}$/', (string) $input['month'])) {
            $errors[] = 'Miesiąc wypłaty jest wymagany.';
        }

        if (empty($input['paid_at']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['paid_at'])) {
            $errors[] = 'Data wypłaty jest wymagana.';
        }

        if (!in_array(($input['status'] ?? 'paid'), ['calculated', 'paid'], true)) {
            $errors[] = 'Nieprawidłowy status wypłaty.';
        }

        return $errors;
    }
}
