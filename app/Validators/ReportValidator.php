<?php
declare(strict_types=1);

namespace App\Validators;

use DateTimeImmutable;
use Throwable;

final class ReportValidator
{
    public const TYPES = [
        'financial_summary',
        'payments_report',
        'expenses_report',
        'salaries_report',
        'trips_report',
        'students_report',
    ];

    public function validate(array $input): array
    {
        $errors = [];
        $type = (string) ($input['type'] ?? 'financial_summary');
        $from = (string) ($input['from'] ?? '');
        $to = (string) ($input['to'] ?? '');

        if (!in_array($type, self::TYPES, true)) {
            $errors[] = 'Nieprawidłowy typ raportu.';
        }

        if (!$this->validDate($from)) {
            $errors[] = 'Data początkowa jest nieprawidłowa.';
        }

        if (!$this->validDate($to)) {
            $errors[] = 'Data końcowa jest nieprawidłowa.';
        }

        if ($this->validDate($from) && $this->validDate($to) && $from > $to) {
            $errors[] = 'Data końcowa nie może być wcześniejsza niż początkowa.';
        }

        return $errors;
    }

    private function validDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        try {
            new DateTimeImmutable($date);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
