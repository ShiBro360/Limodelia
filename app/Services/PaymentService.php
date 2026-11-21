<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PaymentRepository;
use App\Validators\PaymentValidator;
use DateTimeImmutable;
use Throwable;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentValidator $validator,
        private readonly bool $showCancelled = false
    ) {
    }

    public function filters(array $query): array
    {
        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'period' => (string) ($query['period'] ?? 'month'),
            'month' => (string) ($query['month'] ?? date('Y-m')),
            'method' => (int) ($query['method'] ?? 0),
            'status' => (string) ($query['status'] ?? ''),
            'subscription' => (int) ($query['subscription'] ?? 0),
            'student_id' => (int) ($query['student_id'] ?? 0),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => (int) ($query['per_page'] ?? 10),
        ];
        $filters['per_page'] = in_array($filters['per_page'], [10, 25, 50], true) ? $filters['per_page'] : 10;
        $filters['period'] = $filters['period'] === 'all' ? 'all' : 'month';
        $filters['month'] = preg_match('/^\d{4}-\d{2}$/', $filters['month']) ? $filters['month'] : date('Y-m');
        $statusOptions = ['completed', 'pending', 'partial', 'refunded'];
        if ($this->showCancelled) {
            $statusOptions[] = 'cancelled';
        }
        $filters['status'] = in_array($filters['status'], $statusOptions, true) ? $filters['status'] : '';
        $filters['show_cancelled'] = $this->showCancelled;

        return $filters;
    }

    public function paginated(array $filters): array
    {
        $total = $this->payments->count($filters);
        $pageCount = max(1, (int) ceil($total / $filters['per_page']));
        $filters['page'] = min($filters['page'], $pageCount);

        return [
            'payments' => $this->payments->list($filters),
            'summary' => $this->payments->summary($filters),
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => $filters,
        ];
    }

    public function find(int $id): ?array
    {
        return $this->payments->find($id);
    }

    public function create(array $input, array $files = []): array
    {
        $receipt = null;
        $errors = $this->validator->validateCreate($input);
        if ($errors !== []) {
            return $errors;
        }

        try {
            $subscription = $this->payments->subscriptionById((int) ($input['subscription_id'] ?? 0));
            if ($subscription === null) {
                return ['Wybierz prawidłowy abonament.'];
            }

            $data = $this->normalizeCreateData($input, $subscription);

            if (
                (string) ($subscription['billing_period'] ?? '') !== 'once'
                && $this->payments->hasStudentPaymentForSubscriptionPeriod(
                    $data['student_id'],
                    $data['subscription_id'],
                    $data['period_start'],
                    $data['period_end']
                )
            ) {
                return ['Ta uczennica ma już zapisaną płatność za ten abonament w wybranym okresie.'];
            }

            $data['payment_number'] = $this->payments->nextPaymentNumber((int) date('Y', strtotime($data['payment_date'])));
            $receipt = (new PaymentReceiptStorage())->store($files['payment_receipt'] ?? null);
            $data['receipt_path'] = $receipt;
            $this->payments->create($data);

            return [];
        } catch (Throwable $exception) {
            (new PaymentReceiptStorage())->discard($receipt);
            return ['Nie udało się zapisać płatności: ' . $exception->getMessage()];
        }
    }

    public function update(int $id, array $input, array $files = []): array
    {
        $receipt = null;
        if ($id <= 0) {
            return ['Brak ID płatności.'];
        }

        try {
            $existing = $this->payments->find($id);
            if ($existing === null) {
                return ['Nie znaleziono płatności.'];
            }

            // Detail edits may omit the date; only an explicitly supplied date replaces it.
            if (!array_key_exists('payment_date', $input)) {
                $input['payment_date'] = $existing['payment_date'];
            }

            $errors = $this->validator->validateUpdate($input);
            if ($errors !== []) {
                return $errors;
            }

            $receipt = (new PaymentReceiptStorage())->store($files['payment_receipt'] ?? null);
            $this->payments->update($id, [
                'receipt_path' => $receipt ?? ($existing['receipt_path'] ?? null),
                'payment_method_id' => (int) $input['payment_method_id'],
                'amount' => number_format((float) str_replace(',', '.', (string) $input['amount']), 2, '.', ''),
                'payment_date' => (string) $input['payment_date'],
                'status' => (string) ($input['status'] ?? 'completed'),
                'comment' => trim((string) ($input['comment'] ?? '')) ?: null,
            ]);

            return [];
        } catch (Throwable $exception) {
            (new PaymentReceiptStorage())->discard($receipt);
            return ['Nie udało się zaktualizować płatności: ' . $exception->getMessage()];
        }
    }

    public function cancel(int $id): array
    {
        if ($id <= 0) {
            return ['Brak ID płatności.'];
        }

        $this->payments->cancel($id);

        return [];
    }

    public function monthlyRevenue(string $from, string $to): array
    {
        return $this->payments->monthlyRevenue($from, $to);
    }

    public function revenueByGroup(string $from, string $to): array
    {
        return $this->payments->revenueByGroup($from, $to);
    }

    public function recentPayments(int $limit = 5): array
    {
        return $this->payments->recent($limit);
    }

    private function normalizeCreateData(array $input, array $subscription): array
    {
        $paymentDate = new DateTimeImmutable((string) $input['payment_date']);
        $periodMonth = (string) ($input['period_month'] ?? $paymentDate->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            $periodMonth = $paymentDate->format('Y-m');
        }

        $periodStart = new DateTimeImmutable($periodMonth . '-01');
        $periodEnd = $periodStart->modify('last day of this month');
        $billingPeriod = (string) ($subscription['billing_period'] ?? 'monthly');
        $subscriptionSlug = (string) ($subscription['slug'] ?? '');

        if ($billingPeriod === 'once') {
            $periodStart = $paymentDate;
            $periodEnd = $paymentDate;
        } elseif ($billingPeriod === 'custom' || $subscriptionSlug === 'indywidualne-4-zajecia') {
            $periodStart = $paymentDate;
            $periodEnd = $paymentDate->modify('+29 days');
        }

        return [
            'student_id' => (int) $input['student_id'],
            'subscription_id' => (int) ($input['subscription_id'] ?? 0),
            'payment_method_id' => (int) $input['payment_method_id'],
            'amount' => number_format((float) str_replace(',', '.', (string) $input['amount']), 2, '.', ''),
            'payment_date' => (string) $input['payment_date'],
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'status' => (string) ($input['status'] ?? 'completed'),
            'comment' => trim((string) ($input['comment'] ?? '')) ?: null,
        ];
    }
}
