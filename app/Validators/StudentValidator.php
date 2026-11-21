<?php
declare(strict_types=1);

namespace App\Validators;

use App\Helpers\FormatHelper;

final class StudentValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string) ($input['first_name'] ?? '')) === '') {
            $errors[] = 'Imię jest wymagane.';
        }

        if (trim((string) ($input['last_name'] ?? '')) === '') {
            $errors[] = 'Nazwisko jest wymagane.';
        }

        if (trim((string) ($input['phone'] ?? '')) === '') {
            $errors[] = 'Telefon jest wymagany.';
        } elseif (!$this->hasNineDigits((string) $input['phone'])) {
            $errors[] = 'Telefon musi zawierać dokładnie 9 cyfr.';
        }

        if (trim((string) ($input['guardian_phone'] ?? '')) !== '' && !$this->hasNineDigits((string) $input['guardian_phone'])) {
            $errors[] = 'Telefon opiekuna musi zawierać dokładnie 9 cyfr.';
        }

        if (!empty($input['birth_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['birth_date'])) {
            $errors[] = 'Data urodzenia musi mieć format RRRR-MM-DD.';
        }

        if (!empty($input['enrollment_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['enrollment_date'])) {
            $errors[] = 'Data dołączenia musi mieć format RRRR-MM-DD.';
        }

        if (empty($input['student_group_id'])) {
            $errors[] = 'Grupa jest wymagana.';
        }

        if (empty($input['subscription_id'])) {
            $errors[] = 'Abonament jest wymagany.';
        }

        if (!in_array(($input['status'] ?? ''), ['lead', 'active', 'paused', 'inactive', 'archived'], true)) {
            $errors[] = 'Nieprawidłowy status.';
        }

        return $errors;
    }

    private function hasNineDigits(string $value): bool
    {
        return strlen(FormatHelper::phoneDigits($value)) === 9;
    }
}
