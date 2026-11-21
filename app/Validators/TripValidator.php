<?php
declare(strict_types=1);

namespace App\Validators;

final class TripValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors[] = 'Nazwa wyjazdu jest wymagana.';
        }

        if (empty($input['start_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['start_date'])) {
            $errors[] = 'Data wyjazdu jest wymagana.';
        }

        if (trim((string) ($input['location'] ?? '')) === '') {
            $errors[] = 'Miejsce wyjazdu jest wymagane.';
        }

        if (!empty($input['end_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['end_date'])) {
            $errors[] = 'Data zakończenia musi mieć format RRRR-MM-DD.';
        }

        if ((float) str_replace(',', '.', (string) ($input['internal_cost'] ?? 0)) < 0) {
            $errors[] = 'Koszt nie może być ujemny.';
        }

        if (!in_array(($input['status'] ?? 'planned'), ['planned', 'active', 'completed', 'cancelled'], true)) {
            $errors[] = 'Nieprawidłowy status wyjazdu.';
        }

        return $errors;
    }
}
