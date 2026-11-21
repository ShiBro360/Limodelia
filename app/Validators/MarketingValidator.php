<?php
declare(strict_types=1);

namespace App\Validators;

final class MarketingValidator
{
    public const STATUSES = ['planned', 'active', 'paused', 'completed', 'cancelled'];
    public const SOURCES = ['posty', 'reels', 'stories', 'wspolpraca', 'instagram'];

    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors[] = 'Nazwa kampanii jest wymagana.';
        }

        if (empty($input['start_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['start_date'])) {
            $errors[] = 'Data startu jest wymagana.';
        }

        if (!empty($input['end_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['end_date'])) {
            $errors[] = 'Data zakończenia jest nieprawidłowa.';
        }

        if (!empty($input['end_date']) && !empty($input['start_date']) && (string) $input['end_date'] < (string) $input['start_date']) {
            $errors[] = 'Data zakończenia nie może być wcześniejsza niż start.';
        }

        if ((float) str_replace(',', '.', (string) ($input['budget'] ?? 0)) < 0) {
            $errors[] = 'Budżet nie może być ujemny.';
        }

        if ((int) ($input['leads_count'] ?? 0) < 0 || (int) ($input['new_students_count'] ?? 0) < 0) {
            $errors[] = 'Liczby zapytań i nowych uczennic nie mogą być ujemne.';
        }

        foreach (['instagram_post_url', 'campaign_url'] as $field) {
            $url = trim((string) ($input[$field] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors[] = 'Adres URL jest nieprawidłowy.';
                break;
            }
        }

        if (!in_array((string) ($input['status'] ?? 'planned'), self::STATUSES, true)) {
            $errors[] = 'Nieprawidłowy status kampanii.';
        }

        if (!in_array((string) ($input['source'] ?? 'posty'), self::SOURCES, true)) {
            $errors[] = 'Nieprawidłowy typ marketingu.';
        }

        return $errors;
    }
}
