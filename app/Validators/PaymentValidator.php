<?php
declare(strict_types=1);

namespace App\Validators;

final class PaymentValidator
{
    public function validateCreate(array $input): array
    {
        $errors = $this->validateCommon($input);

        if ((int) ($input['student_id'] ?? 0) <= 0) {
            $errors[] = 'Uczennica jest wymagana.';
        }

        if ((int) ($input['subscription_id'] ?? 0) <= 0) {
            $errors[] = 'Abonament jest wymagany.';
        }

        return $errors;
    }

    public function validateUpdate(array $input): array
    {
        return $this->validateCommon($input);
    }

    private function validateCommon(array $input): array
    {
        $errors = [];

        if ((float) ($input['amount'] ?? 0) <= 0) {
            $errors[] = 'Kwota musi być większa od zera.';
        }

        if (empty($input['payment_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['payment_date'])) {
            $errors[] = 'Data płatności jest wymagana.';
        }

        if ((int) ($input['payment_method_id'] ?? 0) <= 0) {
            $errors[] = 'Metoda płatności jest wymagana.';
        }

        if (!in_array(($input['status'] ?? 'completed'), ['pending', 'completed', 'partial', 'refunded', 'cancelled'], true)) {
            $errors[] = 'Nieprawidłowy status płatności.';
        }

        return $errors;
    }
}
