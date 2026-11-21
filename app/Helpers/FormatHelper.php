<?php
declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use Throwable;

final class FormatHelper
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function normalizeTelegram(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : ltrim($value, '@');
    }

    public static function telegram(?string $value): string
    {
        $value = self::normalizeTelegram($value);

        return $value ? '@' . $value : '—';
    }

    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '48') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        $digits = substr($digits, 0, 9);

        return '+48 ' . implode(' ', str_split($digits, 3));
    }

    public static function phone(?string $value): string
    {
        return self::normalizePhone($value) ?? '—';
    }

    public static function phoneDigits(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '48') && strlen($digits) === 11) {
            return substr($digits, 2);
        }

        return $digits;
    }

    public static function date(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        try {
            return (new DateTimeImmutable($date))->format('d.m.Y');
        } catch (Throwable) {
            return '—';
        }
    }

    public static function money(null|float|string $amount, string $currency = 'PLN'): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $suffix = $currency === 'PLN' ? 'zł' : $currency;

        return number_format((float) $amount, 2, ',', ' ') . ' ' . $suffix;
    }

    public static function monthName(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        try {
            $dateTime = new DateTimeImmutable($date);
        } catch (Throwable) {
            return '—';
        }

        $months = [
            1 => 'Styczeń',
            2 => 'Luty',
            3 => 'Marzec',
            4 => 'Kwiecień',
            5 => 'Maj',
            6 => 'Czerwiec',
            7 => 'Lipiec',
            8 => 'Sierpień',
            9 => 'Wrzesień',
            10 => 'Październik',
            11 => 'Listopad',
            12 => 'Grudzień',
        ];

        return $months[(int) $dateTime->format('n')] . ' ' . $dateTime->format('Y');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aktywna',
            'paused' => 'Pauza',
            'inactive', 'archived' => 'Zakończona',
            'lead' => 'Lead',
            default => $status,
        };
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'active' => 'badge-success',
            'paused' => 'badge-warning',
            'inactive', 'archived' => 'badge-muted',
            default => 'badge-sand',
        };
    }

    public static function groupBadge(?string $group): string
    {
        return $group === 'Dziecięca' ? 'badge-rose' : 'badge-sand';
    }

    public static function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Opłacona',
            'pending' => 'Oczekuje',
            'partial' => 'Częściowa',
            'refunded' => 'Zwrócona',
            'cancelled' => 'Anulowana',
            default => $status,
        };
    }

    public static function paymentStatusBadge(string $status): string
    {
        return match ($status) {
            'completed' => 'badge-success',
            'pending', 'partial' => 'badge-warning',
            'cancelled', 'refunded' => 'badge-muted',
            default => 'badge-sand',
        };
    }

    public static function expenseStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Roboczy',
            'submitted' => 'Zgłoszony',
            'approved' => 'Zatwierdzony',
            'paid' => 'Opłacony',
            'rejected' => 'Odrzucony',
            'cancelled' => 'Anulowany',
            default => $status,
        };
    }

    public static function expenseStatusBadge(string $status): string
    {
        return match ($status) {
            'approved', 'paid' => 'badge-success',
            'submitted' => 'badge-warning',
            'cancelled', 'rejected' => 'badge-muted',
            default => 'badge-sand',
        };
    }

    public static function salaryStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Robocza',
            'calculated' => 'Do wypłaty',
            'approved' => 'Zatwierdzona',
            'paid' => 'Wypłacona',
            'cancelled' => 'Anulowana',
            default => $status,
        };
    }

    public static function salaryStatusBadge(string $status): string
    {
        return match ($status) {
            'paid' => 'badge-success',
            'calculated', 'approved' => 'badge-warning',
            'cancelled' => 'badge-muted',
            default => 'badge-sand',
        };
    }

    public static function initials(string $firstName, string $lastName): string
    {
        $firstInitial = preg_match('/./u', trim($firstName), $firstMatch) ? $firstMatch[0] : '';
        $lastInitial = preg_match('/./u', trim($lastName), $lastMatch) ? $lastMatch[0] : '';

        return strtoupper($firstInitial . $lastInitial);
    }
}
