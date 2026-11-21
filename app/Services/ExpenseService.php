<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExpenseRepository;
use App\Repositories\MarketingRepository;
use App\Validators\ExpenseValidator;
use Throwable;

final class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly ExpenseValidator $validator,
        private readonly bool $showCancelled = false,
        private readonly ?MarketingRepository $marketing = null,
        private readonly array $lessonSchedule = [],
        private readonly float $rentalHourlyRate = 32.0,
        private readonly int $individualRentalDurationMinutes = 60
    ) {
    }

    public function filters(array $query): array
    {
        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'month' => (string) ($query['month'] ?? date('Y-m')),
            'category' => (int) ($query['category'] ?? 0),
            'method' => (int) ($query['method'] ?? 0),
            'highlight_expense' => (int) ($query['highlight_expense'] ?? 0),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => (int) ($query['per_page'] ?? 10),
        ];
        $filters['month'] = preg_match('/^\d{4}-\d{2}$/', $filters['month']) ? $filters['month'] : date('Y-m');
        $filters['per_page'] = in_array($filters['per_page'], [10, 25, 50], true) ? $filters['per_page'] : 10;
        $filters['show_cancelled'] = $this->showCancelled;

        return $filters;
    }

    public function paginated(array $filters): array
    {
        $total = $this->expenses->count($filters);
        $pageCount = max(1, (int) ceil($total / $filters['per_page']));
        $filters['page'] = min($filters['page'], $pageCount);

        return [
            'expenses' => $this->expenses->list($filters),
            'summary' => $this->expenses->summary($filters),
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => $filters,
        ];
    }

    public function find(int $id): ?array
    {
        return $this->expenses->find($id);
    }

    public function create(array $input, array $files = []): array
    {
        $input = $this->applyAutoRentalAmount($input);
        $errors = $this->validator->validate($input);
        $errors = array_merge($errors, $this->validateRentalSchedule($input));
        if ($errors !== []) {
            return $errors;
        }

        $receipt = null;
        try {
            $receipt = (new ExpenseReceiptStorage())->store($files['expense_receipt'] ?? null);
            $data = $this->normalizeData($input);
            $data['receipt_path'] = $receipt;
            $data = $this->syncMarketingCampaign($data, $input, null);
            $this->expenses->create($this->stripInternalFields($data));

            return [];
        } catch (Throwable $exception) {
            (new ExpenseReceiptStorage())->discard($receipt);
            return ['Nie udało się zapisać wydatku: ' . $exception->getMessage()];
        }
    }

    public function update(int $id, array $input, array $files = []): array
    {
        if ($id <= 0) {
            return ['Brak ID wydatku.'];
        }

        $input = $this->applyAutoRentalAmount($input);
        $errors = $this->validator->validate($input);
        $errors = array_merge($errors, $this->validateRentalSchedule($input));
        if ($errors !== []) {
            return $errors;
        }

        $receipt = null;
        try {
            $existing = $this->expenses->find($id);
            if (!$existing) { return ['Nie znaleziono wydatku.']; }
            $receipt = (new ExpenseReceiptStorage())->store($files['expense_receipt'] ?? null);
            $data = $this->normalizeData($input);
            $data['receipt_path'] = $receipt ?? ($existing['receipt_path'] ?? null);
            $data = $this->syncMarketingCampaign($data, $input, $existing);
            $this->expenses->update($id, $this->stripInternalFields($data));

            return [];
        } catch (Throwable $exception) {
            (new ExpenseReceiptStorage())->discard($receipt);
            return ['Nie udało się zaktualizować wydatku: ' . $exception->getMessage()];
        }
    }

    public function cancel(int $id): array
    {
        if ($id <= 0) {
            return ['Brak ID wydatku.'];
        }

        try {
            $existing = $this->expenses->find($id);
            $this->expenses->cancel($id);
            if ($this->marketing !== null && !empty($existing['marketing_campaign_id'])) {
                $this->marketing->cancel((int) $existing['marketing_campaign_id']);
            }

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się anulować wydatku: ' . $exception->getMessage()];
        }
    }

    public function yearTotal(int $year): float
    {
        return $this->expenses->yearTotal($year);
    }

    public function byCategory(string $month): array
    {
        return $this->expenses->byCategory($month);
    }

    public function largest(string $month): ?array
    {
        return $this->expenses->largest($month);
    }

    public function reservedRentalDates(?int $exceptExpenseId = null): array
    {
        return $this->expenses->reservedRentalDates($exceptExpenseId);
    }

    private function normalizeData(array $input): array
    {
        $categoryId = (int) $input['expense_category_id'];
        $categorySlug = $this->expenses->categorySlugById($categoryId) ?? '';
        $description = $this->cleanGeneratedDescription((string) ($input['description'] ?? ''));
        $comment = trim((string) ($input['comment'] ?? ''));
        $comment = preg_replace('/\s+/u', ' ', $comment) ?? $comment;

        $details = [];

        if ($categorySlug === 'wynajem' && !empty($input['rental_date'])) {
            $slot = $this->scheduleSlotForDate((string) $input['rental_date']);
            $details[] = 'Rezerwacja sali: ' . (string) $input['rental_date'];

        }

        if ($categorySlug === 'wynajem-indywidualny' && !empty($input['individual_rental_date'])) {
            $start = (string) ($input['individual_rental_start'] ?? '');
            $timeRange = $start !== '' ? ' ' . $start : '';
            $details[] = 'Rezerwacja sali indywidualna: ' . (string) $input['individual_rental_date'] . $timeRange;

        }

        if ($categorySlug === 'marketing' && !empty($input['instagram_campaign'])) {
            $details[] = 'Instagram: ' . trim((string) $input['instagram_campaign']);
        }

        if ($comment !== '') {
            $details[] = 'Komentarz: ' . $comment;
        }

        if ($details !== []) {
            $description = trim($description . "\n" . implode("\n", $details));
        }

        return [
            'expense_category_id' => $categoryId,
            'payment_method_id' => (int) $input['payment_method_id'],
            'amount' => number_format((float) str_replace(',', '.', (string) $input['amount']), 2, '.', ''),
            'expense_date' => (string) $input['expense_date'],
            'description' => $description !== '' ? $description : null,
            'status' => (string) ($input['status'] ?? 'draft'),
            'trip_id' => !empty($input['trip_id']) ? (int) $input['trip_id'] : null,
            'marketing_campaign_id' => !empty($input['marketing_campaign_id']) ? (int) $input['marketing_campaign_id'] : null,
            '_category_slug' => $categorySlug,
        ];
    }

    private function syncMarketingCampaign(array $data, array $input, ?array $existing): array
    {
        if ((string) ($data['_category_slug'] ?? '') !== 'marketing') {
            if ($this->marketing !== null && !empty($existing['marketing_campaign_id'])) {
                $this->marketing->cancel((int) $existing['marketing_campaign_id']);
            }
            $data['marketing_campaign_id'] = null;

            return $data;
        }

        if (!empty($input['marketing_campaign_id']) || $this->marketing === null) {
            return $data;
        }

        $campaignId = !empty($existing['marketing_campaign_id']) ? (int) $existing['marketing_campaign_id'] : null;
        $campaign = $this->campaignPayloadFromExpense($data, $input);

        $data['marketing_campaign_id'] = $campaignId
            ? $this->updateMarketingCampaign($campaignId, $campaign)
            : $this->marketing->create($campaign);

        return $data;
    }

    private function updateMarketingCampaign(int $campaignId, array $campaign): int
    {
        $this->marketing?->update($campaignId, $campaign);

        return $campaignId;
    }

    private function campaignPayloadFromExpense(array $data, array $input): array
    {
        $campaignName = trim((string) ($input['instagram_campaign'] ?? ''));
        if ($campaignName === '') {
            $description = trim((string) ($data['description'] ?? ''));
            $campaignName = strtok($description, "\r\n") ?: '';
        }
        if ($campaignName === '') {
            $campaignName = 'Marketing Instagram - ' . (string) $data['expense_date'];
        }

        return [
            'name' => $campaignName,
            'instagram_post_url' => null,
            'campaign_url' => null,
            'status' => $this->marketingStatusFromExpenseStatus((string) $data['status']),
            'budget' => $data['amount'],
            'leads_count' => 0,
            'new_students_count' => 0,
            'start_date' => $data['expense_date'],
            'end_date' => $data['expense_date'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ];
    }

    private function marketingStatusFromExpenseStatus(string $status): string
    {
        return match ($status) {
            'cancelled', 'rejected' => 'cancelled',
            'paid', 'approved' => 'completed',
            default => 'planned',
        };
    }

    private function stripInternalFields(array $data): array
    {
        unset($data['_category_slug']);

        return $data;
    }

    private function cleanGeneratedDescription(string $description): string
    {
        $lines = preg_split('/\R/u', trim($description)) ?: [];
        $manualLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(Komentarz|Rezerwacja sali|Rezerwacja sali indywidualna|Liczba godzin|Stawka godzinowa|Instagram):/u', $line)) {
                continue;
            }

            $manualLines[] = $line;
        }

        return implode("\n", $manualLines);
    }

    private function validateRentalSchedule(array $input): array
    {
        $categoryId = (int) ($input['expense_category_id'] ?? 0);
        $categorySlug = $categoryId > 0 ? ($this->expenses->categorySlugById($categoryId) ?? '') : '';
        if ($categoryId <= 0 || !in_array($categorySlug, ['wynajem', 'wynajem-indywidualny'], true)) {
            return [];
        }

        if ($categorySlug === 'wynajem') {
            $rentalDate = (string) ($input['rental_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rentalDate)) {
                return ['Wybierz dzień rezerwacji sali.'];
            }

            return [];
        }

        $date = (string) ($input['individual_rental_date'] ?? '');
        $start = (string) ($input['individual_rental_start'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['Wybierz dzień indywidualnej rezerwacji sali.'];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
            return ['Podaj godzinę indywidualnej rezerwacji sali.'];
        }
        if ($this->oneHourEndTime($start) === '') {
            return ['Podaj prawidłową godzinę indywidualnej rezerwacji sali.'];
        }

        return [];
    }

    private function applyAutoRentalAmount(array $input): array
    {
        if (trim((string) ($input['amount'] ?? '')) !== '') { return $input; }
        $categoryId = (int) ($input['expense_category_id'] ?? 0);
        $categorySlug = $categoryId > 0 ? ($this->expenses->categorySlugById($categoryId) ?? '') : '';

        if ($categorySlug === 'wynajem') {
            $slot = !empty($input['rental_date']) ? $this->scheduleSlotForDate((string) $input['rental_date']) : null;
            $input['amount'] = $this->rentalAmountForSlot($slot ?? [
                'start' => '17:00',
                'end' => '18:00',
            ]);
        }

        if ($categorySlug === 'wynajem-indywidualny') {
            $input['amount'] = $this->individualRentalAmount();
        }

        return $input;
    }

    private function scheduleSlotForDate(string $date): ?array
    {
        $dateObject = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObject) {
            return null;
        }

        $weekday = (int) $dateObject->format('N');
        foreach ($this->lessonSchedule as $slot) {
            if ((int) ($slot['weekday'] ?? 0) === $weekday) {
                return $slot;
            }
        }

        return [
            'weekday' => $weekday,
            'start' => '17:00',
            'end' => '18:00',
        ];
    }

    private function rentalAmountForSlot(array $slot): string
    {
        $start = \DateTimeImmutable::createFromFormat('H:i', (string) ($slot['start'] ?? ''));
        $end = \DateTimeImmutable::createFromFormat('H:i', (string) ($slot['end'] ?? ''));
        if (!$start || !$end || $end <= $start) {
            return $this->moneyFromHours(1.0);
        }

        $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        $hours = max(1, $minutes / 60);

        return $this->moneyFromHours($hours);
    }

    private function rentalAmountForTimes(string $start, string $end): string
    {
        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);
        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            return $this->moneyFromHours(1.0);
        }

        $hours = max(1, ($endMinutes - $startMinutes) / 60);

        return $this->moneyFromHours($hours);
    }

    private function individualRentalAmount(): string
    {
        $minutes = max(1, $this->individualRentalDurationMinutes);

        return $this->moneyFromHours($minutes / 60);
    }

    private function moneyFromHours(float $hours): string
    {
        $rate = $this->rentalHourlyRate > 0 ? $this->rentalHourlyRate : 32.0;

        return number_format(max(1 / 60, $hours) * $rate, 2, '.', '');
    }

    private function oneHourEndTime(string $start): string
    {
        $minutes = $this->timeToMinutes($start);
        if ($minutes === null) {
            return '';
        }

        $endMinutes = ($minutes + max(1, $this->individualRentalDurationMinutes)) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($endMinutes, 60), $endMinutes % 60);
    }

    private function timeToMinutes(string $time): ?int
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $match)) {
            return null;
        }

        return ((int) $match[1] * 60) + (int) $match[2];
    }
}
