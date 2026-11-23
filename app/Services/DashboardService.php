<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;
use DateTimeImmutable;

final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboard,
        private readonly SalaryService $salaryService
    ) {
    }

    public function data(?DateTimeImmutable $date = null, ?int $chartYear = null): array
    {
        $date ??= new DateTimeImmutable();
        $monthStart = $date->modify('first day of this month')->format('Y-m-d');
        $monthEnd = $date->modify('last day of this month')->format('Y-m-d');
        $year = (int) $date->format('Y');
        $chartYear ??= $year;

        $revenue = $this->dashboard->currentMonthRevenue($monthStart, $monthEnd);
        $expenses = $this->dashboard->currentMonthExpenses($monthStart, $monthEnd);
        $profit = $revenue - $expenses;
        $yearIncome = $this->dashboard->yearIncome($year);
        $limit = (float) ($this->dashboard->setting('non_registered_activity_limit') ?? 75000);
        $limit = $limit > 0 ? $limit : 75000;
        $instagramSpend = $this->dashboard->instagramSpending($monthStart, $monthEnd);

        return [
            'period' => [
                'from' => $monthStart,
                'to' => $monthEnd,
                'label' => $this->periodLabel($date),
            ],
            'kpis' => [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => $profit,
                'active_students' => $this->dashboard->activeStudentsCount(),
            ],
            'monthly_net_profit' => $this->normalizeMonthlyProfit($this->dashboard->monthlyNetProfit($chartYear)),
            'revenue_by_group' => $this->normalizeGroups($this->dashboard->revenueByGroup($monthStart, $monthEnd)),
            'recent_payments' => $this->dashboard->recentPayments(5),
            'recent_expenses' => $this->dashboard->recentExpenses(5),
            'activity_limit' => [
                'used' => $yearIncome,
                'limit' => $limit,
                'remaining' => max(0, $limit - $yearIncome),
                'percent' => min(100, max(0, ($yearIncome / $limit) * 100)),
            ],
            'salaries' => $this->salaryService->preview($date->format('Y-m')),
            'lesson_schedule' => $this->lessonSchedule(),
            'rental_expenses' => $this->normalizeRentalExpenseStatuses($this->dashboard->rentalExpenseStatuses($monthStart, $monthEnd)),
            'instagram' => [
                'spending' => $instagramSpend,
                'leads' => 0,
                'new_students' => 0,
                'cost_per_acquisition' => 0,
            ],
            'trips' => [
                'month_count' => $this->dashboard->tripsThisMonth($monthStart, $monthEnd),
                'expenses' => $this->dashboard->tripExpenses($monthStart, $monthEnd),
            ],
        ];
    }

    /**
     * Twelve values, January first. Months without a payment or an expense stay
     * null so the chart can show a gap instead of a zero-profit month.
     *
     * @return array<int, float|null>
     */
    private function normalizeMonthlyProfit(array $rows): array
    {
        $values = array_fill(1, 12, null);
        foreach ($rows as $row) {
            $values[(int) $row['month']] = (float) $row['profit'];
        }

        return $values;
    }

    private function normalizeGroups(array $rows): array
    {
        $total = array_sum(array_map(static fn (array $row): float => (float) $row['revenue'], $rows));

        return [
            'total' => $total,
            'items' => array_map(static function (array $row) use ($total): array {
                $revenue = (float) $row['revenue'];
                return [
                    'name' => $row['group_name'] ?: 'Brak grupy',
                    'revenue' => $revenue,
                    'percent' => $total > 0 ? ($revenue / $total) * 100 : 0,
                ];
            }, $rows),
        ];
    }

    private function normalizeRentalExpenseStatuses(array $rows): array
    {
        $statuses = [];

        foreach ($rows as $row) {
            $date = $this->rentalDateFromDescription((string) ($row['description'] ?? ''))
                ?? (string) ($row['expense_date'] ?? '');
            $status = (string) ($row['status'] ?? '');
            if ($date === '' || $status === '') {
                continue;
            }

            $categorySlug = (string) ($row['category_slug'] ?? '');
            $isGroup = $categorySlug === 'wynajem';
            $isIndividual = $categorySlug === 'wynajem-indywidualny';
            $isPaid = $status === 'paid';
            $isCancelled = in_array($status, ['cancelled', 'rejected'], true);
            $isUnpaid = in_array($status, ['draft', 'pending'], true);
            $comment = $this->expenseCommentFromDescription((string) ($row['description'] ?? ''));
            $hasComment = $comment !== '';
            $expense = [
                'status' => $status,
                'amount' => (float) ($row['amount'] ?? 0),
                'id' => (int) ($row['id'] ?? 0),
                'expense_date' => (string) ($row['expense_date'] ?? ''),
                'category_slug' => $categorySlug,
            ];

            $statuses[$date] ??= [
                'status' => 'available',
                'amount' => 0.0,
                'id' => 0,
                'expense_date' => '',
                'category_slug' => '',
                'group' => null,
                'individual' => null,
                'has_group_paid' => false,
                'has_individual_paid' => false,
                'has_unpaid' => false,
                'has_cancelled' => false,
                'has_comment' => false,
                'comments' => [],
            ];

            if ($isGroup && ($statuses[$date]['group'] === null || $isPaid)) {
                $statuses[$date]['group'] = $expense;
            }

            if ($isIndividual && ($statuses[$date]['individual'] === null || $isPaid)) {
                $statuses[$date]['individual'] = $expense;
            }

            $statuses[$date]['has_group_paid'] = $statuses[$date]['has_group_paid'] || ($isGroup && $isPaid);
            $statuses[$date]['has_individual_paid'] = $statuses[$date]['has_individual_paid'] || ($isIndividual && $isPaid);
            $statuses[$date]['has_unpaid'] = $statuses[$date]['has_unpaid'] || $isUnpaid;
            $statuses[$date]['has_cancelled'] = $statuses[$date]['has_cancelled'] || $isCancelled;
            $statuses[$date]['has_comment'] = $statuses[$date]['has_comment'] || $hasComment;
            if ($hasComment && !in_array($comment, $statuses[$date]['comments'], true)) {
                $statuses[$date]['comments'][] = $comment;
            }

            if ($isPaid && ($statuses[$date]['id'] === 0 || $isGroup)) {
                $statuses[$date]['status'] = 'paid';
                $statuses[$date]['amount'] = (float) ($row['amount'] ?? 0);
                $statuses[$date]['id'] = (int) ($row['id'] ?? 0);
                $statuses[$date]['expense_date'] = (string) ($row['expense_date'] ?? '');
                $statuses[$date]['category_slug'] = $categorySlug;
            } elseif (($statuses[$date]['status'] ?? '') !== 'paid' && $isUnpaid) {
                $statuses[$date]['status'] = $status;
                $statuses[$date]['amount'] = (float) ($row['amount'] ?? 0);
                $statuses[$date]['id'] = (int) ($row['id'] ?? 0);
                $statuses[$date]['expense_date'] = (string) ($row['expense_date'] ?? '');
                $statuses[$date]['category_slug'] = $categorySlug;
            } elseif (($statuses[$date]['status'] ?? '') === 'available' && $isCancelled) {
                $statuses[$date]['status'] = $status;
                $statuses[$date]['amount'] = (float) ($row['amount'] ?? 0);
                $statuses[$date]['id'] = (int) ($row['id'] ?? 0);
                $statuses[$date]['expense_date'] = (string) ($row['expense_date'] ?? '');
                $statuses[$date]['category_slug'] = $categorySlug;
            }
        }

        return $statuses;
    }

    private function rentalDateFromDescription(string $description): ?string
    {
        if (preg_match('/Rezerwacja sali(?: indywidualna)?:\s*(\d{4}-\d{2}-\d{2})/u', $description, $match)) {
            return $match[1];
        }

        return null;
    }

    private function expenseCommentFromDescription(string $description): string
    {
        if (preg_match('/(?:^|\R)Komentarz:\s*(.+)$/mu', $description, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    private function lessonSchedule(): array
    {
        $value = $this->dashboard->setting('schedule.lesson_days');
        if ($value === null || trim($value) === '') {
            return [
                ['weekday' => 1, 'label' => 'Poniedziałek', 'start' => '17:00', 'end' => '18:00'],
                ['weekday' => 3, 'label' => 'Środa', 'start' => '19:00', 'end' => '20:00'],
            ];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (array $item): ?array {
            $weekday = (int) ($item['weekday'] ?? 0);
            $start = (string) ($item['start'] ?? '');
            $end = (string) ($item['end'] ?? '');
            if ($weekday < 1 || $weekday > 7 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                return null;
            }

            return [
                'weekday' => $weekday,
                'label' => (string) ($item['label'] ?? ''),
                'start' => $start,
                'end' => $end,
            ];
        }, $decoded)));
    }

    private function periodLabel(DateTimeImmutable $date): string
    {
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

        return '1 - ' . $date->modify('last day of this month')->format('d') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
