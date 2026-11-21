<?php
declare(strict_types=1);

namespace App\Validators;

final class ExpenseValidator
{
    public function validate(array $input): array
    {
        $errors = [];

        if (empty($input['expense_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['expense_date'])) {
            $errors[] = 'Data wydatku jest wymagana.';
        }

        if ((int) ($input['expense_category_id'] ?? 0) <= 0) {
            $errors[] = 'Kategoria jest wymagana.';
        }

        if ((float) str_replace(',', '.', (string) ($input['amount'] ?? 0)) <= 0) {
            $errors[] = 'Kwota musi być większa od zera.';
        }

        if ((int) ($input['payment_method_id'] ?? 0) <= 0) {
            $errors[] = 'Metoda płatności jest wymagana.';
        }

        if (!in_array(($input['status'] ?? 'draft'), ['draft', 'submitted', 'approved', 'rejected', 'paid', 'cancelled'], true)) {
            $errors[] = 'Nieprawidłowy status wydatku.';
        }

        return $errors;
    }
}
