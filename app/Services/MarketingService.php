<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MarketingRepository;
use App\Validators\MarketingValidator;
use DateTimeImmutable;
use Throwable;

final class MarketingService
{
    public function __construct(
        private readonly MarketingRepository $campaigns,
        private readonly MarketingValidator $validator,
        private readonly ExpenseService $expenses
    ) {
    }

    public function filters(array $query): array
    {
        $period = in_array((string) ($query['period'] ?? 'month'), ['month', 'all'], true) ? (string) $query['period'] : 'month';
        $month = (string) ($query['month'] ?? date('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $date = new DateTimeImmutable($month . '-01');

        return [
            'search' => trim((string) ($query['search'] ?? '')),
            'period' => $period,
            'month' => $month,
            'from' => $period === 'all' ? '' : $date->modify('first day of this month')->format('Y-m-d'),
            'to' => $period === 'all' ? '' : $date->modify('last day of this month')->format('Y-m-d'),
            'status' => in_array((string) ($query['status'] ?? ''), MarketingValidator::STATUSES, true) ? (string) $query['status'] : '',
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => in_array((int) ($query['per_page'] ?? 10), [10, 25, 50], true) ? (int) ($query['per_page'] ?? 10) : 10,
        ];
    }

    public function paginated(array $filters): array
    {
        $this->importExistingMarketingExpenses();

        $total = $this->campaigns->count($filters);
        $pageCount = max(1, (int) ceil($total / $filters['per_page']));
        $filters['page'] = min($filters['page'], $pageCount);

        return [
            'campaigns' => array_map([$this, 'withMetrics'], $this->campaigns->list($filters)),
            'summary' => $this->summary($filters),
            'trend' => $this->campaigns->monthlyTrend((int) substr($filters['month'], 0, 4)),
            'top' => array_map([$this, 'withMetrics'], $this->campaigns->topCampaigns($filters)),
            'sources' => $this->campaigns->sourceBreakdown($filters),
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => $filters,
        ];
    }

    public function find(int $id): ?array
    {
        $campaign = $this->campaigns->find($id);

        return $campaign ? $this->withMetrics($campaign) : null;
    }

    public function create(array $input): array
    {
        return $this->save($input);
    }

    public function update(int $id, array $input): array
    {
        if ($id <= 0) {
            return ['Brak ID kampanii.'];
        }

        return $this->save($input, $id);
    }

    public function cancel(int $id): array
    {
        if ($id <= 0) {
            return ['Brak ID kampanii.'];
        }

        try {
            $this->campaigns->beginTransaction();
            $this->campaigns->cancel($id);
            $expenseId = $this->campaigns->linkedExpenseId($id);
            if ($expenseId) {
                $errors = $this->expenses->cancel($expenseId);
                if ($errors !== []) {
                    throw new \RuntimeException(implode(' ', $errors));
                }
            }
            $this->campaigns->commit();

            return [];
        } catch (Throwable $exception) {
            $this->campaigns->rollBackIfActive();

            return ['Nie udało się anulować kampanii: ' . $exception->getMessage()];
        }
    }

    private function save(array $input, ?int $id = null): array
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        try {
            $data = $this->normalizeData($input);
            $this->campaigns->beginTransaction();
            $campaignId = $id === null ? $this->campaigns->create($data) : $id;
            if ($id !== null) {
                $this->campaigns->update($id, $data);
            }
            $this->syncExpense($campaignId, $data);
            $this->campaigns->commit();

            return [];
        } catch (Throwable $exception) {
            $this->campaigns->rollBackIfActive();

            return ['Nie udało się zapisać kampanii: ' . $exception->getMessage()];
        }
    }

    private function syncExpense(int $campaignId, array $data): void
    {
        $expenseId = $this->campaigns->linkedExpenseId($campaignId);
        $payload = [
            'expense_category_id' => $this->campaigns->marketingCategoryId(),
            'payment_method_id' => $this->campaigns->defaultPaymentMethodId(),
            'marketing_campaign_id' => $campaignId,
            'amount' => $data['budget'],
            'expense_date' => $data['start_date'],
            'description' => 'Marketing Instagram - ' . $data['name'],
            'status' => $data['status'] === 'cancelled' ? 'cancelled' : 'draft',
            'instagram_campaign' => $data['name'],
        ];

        $errors = $expenseId ? $this->expenses->update($expenseId, $payload) : $this->expenses->create($payload);
        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }
    }

    private function importExistingMarketingExpenses(): void
    {
        $expenses = $this->campaigns->orphanMarketingExpenses();
        if ($expenses === []) {
            return;
        }

        try {
            $this->campaigns->beginTransaction();
            foreach ($expenses as $expense) {
                $campaign = $this->campaignFromExpense($expense);
                $campaignId = $this->campaigns->create($campaign);
                $this->campaigns->attachExpenseToCampaign((int) $expense['id'], $campaignId);
            }
            $this->campaigns->commit();
        } catch (Throwable $exception) {
            $this->campaigns->rollBackIfActive();

            throw $exception;
        }
    }

    private function campaignFromExpense(array $expense): array
    {
        $description = trim((string) ($expense['description'] ?? ''));
        $firstLine = strtok($description, "\r\n") ?: '';

        return [
            'name' => $firstLine !== '' ? $firstLine : 'Marketing Instagram - ' . (string) $expense['expense_date'],
            'instagram_post_url' => null,
            'campaign_url' => null,
            'status' => $this->campaignStatusFromExpenseStatus((string) $expense['status']),
            'source' => 'instagram',
            'budget' => number_format((float) $expense['amount'], 2, '.', ''),
            'leads_count' => 0,
            'new_students_count' => 0,
            'start_date' => (string) $expense['expense_date'],
            'end_date' => (string) $expense['expense_date'],
            'description' => $description !== '' ? $description : null,
        ];
    }

    private function campaignStatusFromExpenseStatus(string $status): string
    {
        return match ($status) {
            'paid', 'approved' => 'completed',
            'cancelled', 'rejected' => 'cancelled',
            default => 'planned',
        };
    }

    private function normalizeData(array $input): array
    {
        return [
            'name' => trim((string) $input['name']),
            'source' => in_array((string) ($input['source'] ?? 'posty'), MarketingValidator::SOURCES, true) ? (string) $input['source'] : 'posty',
            'instagram_post_url' => trim((string) ($input['instagram_post_url'] ?? '')) ?: null,
            'campaign_url' => trim((string) ($input['campaign_url'] ?? '')) ?: null,
            'status' => (string) ($input['status'] ?? 'planned'),
            'budget' => number_format((float) str_replace(',', '.', (string) ($input['budget'] ?? 0)), 2, '.', ''),
            'leads_count' => max(0, (int) ($input['leads_count'] ?? 0)),
            'new_students_count' => max(0, (int) ($input['new_students_count'] ?? 0)),
            'start_date' => (string) $input['start_date'],
            'end_date' => !empty($input['end_date']) ? (string) $input['end_date'] : null,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
        ];
    }

    private function summary(array $filters): array
    {
        $summary = $this->campaigns->summary($filters);
        $budget = (float) ($summary['budget'] ?? 0);
        $leads = (int) ($summary['leads'] ?? 0);
        $newStudents = (int) ($summary['new_students'] ?? 0);

        return [
            'budget' => $budget,
            'leads' => $leads,
            'new_students' => $newStudents,
            'cost_per_lead' => $leads > 0 ? $budget / $leads : 0,
            'cost_per_student' => $newStudents > 0 ? $budget / $newStudents : 0,
            'conversion' => $leads > 0 ? ($newStudents / $leads) * 100 : 0,
            'campaigns_count' => (int) ($summary['campaigns_count'] ?? 0),
        ];
    }

    private function withMetrics(array $campaign): array
    {
        $budget = (float) ($campaign['budget'] ?? 0);
        $leads = (int) ($campaign['leads_count'] ?? 0);
        $newStudents = (int) ($campaign['new_students_count'] ?? 0);
        $campaign['cost_per_lead'] = $leads > 0 ? $budget / $leads : 0;
        $campaign['cost_per_student'] = $newStudents > 0 ? $budget / $newStudents : 0;
        $campaign['conversion'] = $leads > 0 ? ($newStudents / $leads) * 100 : 0;

        return $campaign;
    }
}
