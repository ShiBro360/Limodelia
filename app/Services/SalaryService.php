<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SalaryRepository;
use App\Validators\SalaryValidator;
use DateTimeImmutable;
use Throwable;

final class SalaryService
{
    public const EMPLOYEES = [
        'teacher' => 'Teacher',
        'accountant' => 'Accountant',
        'marketing' => 'Marketing specialist',
    ];

    public function __construct(
        private readonly SalaryRepository $salaries,
        private readonly SalaryValidator $validator
    ) {
    }

    public function filters(array $query): array
    {
        $filters = [
            'month' => (string) ($query['month'] ?? date('Y-m')),
            'employee' => (string) ($query['employee'] ?? ''),
            'status' => (string) ($query['status'] ?? ''),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => (int) ($query['per_page'] ?? 10),
        ];
        $filters['month'] = preg_match('/^\d{4}-\d{2}$/', $filters['month']) ? $filters['month'] : date('Y-m');
        $filters['employee'] = array_key_exists($filters['employee'], self::EMPLOYEES) ? $filters['employee'] : '';
        $filters['status'] = in_array($filters['status'], ['draft', 'calculated', 'approved', 'paid', 'cancelled'], true) ? $filters['status'] : '';
        $filters['per_page'] = in_array($filters['per_page'], [10, 25, 50], true) ? $filters['per_page'] : 10;

        return $filters;
    }

    public function paginated(array $filters): array
    {
        $total = $this->salaries->count($filters);
        $pageCount = max(1, (int) ceil($total / $filters['per_page']));
        $filters['page'] = min($filters['page'], $pageCount);

        return [
            'payouts' => $this->salaries->list($filters),
            'summary' => $this->salaries->summary($filters),
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => $filters,
        ];
    }

    public function preview(string $month): array
    {
        [$from, $to] = $this->monthRange($month);
        $revenue = $this->salaries->currentMonthRevenue($from, $to);
        $nonSalaryExpenses = $this->salaries->currentMonthNonSalaryExpenses($from, $to);
        $profitBase = max(0, $revenue - $nonSalaryExpenses);
        $accountantRule = $this->salaries->ruleForRole('accountant');
        $teacherRule = $this->salaries->ruleForRole('teacher');
        $marketingRule = $this->salaries->ruleForRole('marketing');
        $marketingStudents = $this->salaries->marketingNewStudents($from, $to);
        $accountantPriority = $this->calculatePriorityAmount($accountantRule, $marketingStudents);
        $marketingPriority = $this->calculatePriorityAmount($marketingRule, $marketingStudents);
        $priorityObligations = min($profitBase, $accountantPriority + $marketingPriority);
        $distributionPool = max(0, $profitBase - $priorityObligations);

        $accountant = $accountantPriority;
        if (($accountantRule['rule_type'] ?? '') === 'percentage_net_profit') {
            $accountant = $this->calculatePercentageAmount($accountantRule, $distributionPool);
        }

        $marketing = $marketingPriority;
        if (($marketingRule['rule_type'] ?? '') === 'percentage_net_profit') {
            $marketing = $this->calculatePercentageAmount(
                $marketingRule,
                max(0, $distributionPool - $accountant)
            );
        }

        $teacherProfitBase = max(0, $profitBase - $accountant - $marketing);
        $teacher = ($teacherRule['rule_type'] ?? '') === 'remaining_profit'
            ? $teacherProfitBase
            : $this->calculateRuleAmount($teacherRule, $teacherProfitBase, $marketingStudents);
        $teacherPercent = (float) ($teacherRule['percentage'] ?? 0);
        $marketingRate = (float) ($marketingRule['amount'] ?? 0);

        return [
            'revenue' => $revenue,
            'non_salary_expenses' => $nonSalaryExpenses,
            'profit_base' => $profitBase,
            'priority_obligations' => $priorityObligations,
            'distribution_pool' => $distributionPool,
            'teacher_profit_base' => $teacherProfitBase,
            'teacher' => $teacher,
            'teacher_percent' => $teacherPercent,
            'accountant' => $accountant,
            'marketing' => $marketing,
            'marketing_rate' => $marketingRate,
            'marketing_new_students' => $marketingStudents,
            'accountant_label' => $this->ruleLabel('Księgowa', $accountantRule),
            'teacher_label' => $this->ruleLabel('Nauczycielka', $teacherRule, true),
            'marketing_label' => $this->ruleLabel('Marketing', $marketingRule),
            'accountant_rule_type' => (string) ($accountantRule['rule_type'] ?? ''),
            'teacher_rule_type' => (string) ($teacherRule['rule_type'] ?? ''),
            'marketing_rule_type' => (string) ($marketingRule['rule_type'] ?? ''),
            'total' => $teacher + $accountant + $marketing,
        ];
    }

    public function activeRules(): array
    {
        return $this->salaries->activeRules();
    }

    public function createPayout(array $input): array
    {
        $errors = $this->validator->validatePayout($input);
        if ($errors !== []) {
            return $errors;
        }

        try {
            $employee = (string) $input['employee'];
            $month = (string) $input['month'];
            [$from, $to] = $this->monthRange($month);
            $preview = $this->preview($month);
            $rule = $this->salaries->ruleForRole($employee);

            if (!$rule) {
                return ['Brak aktywnej reguły wynagrodzenia dla wybranego pracownika.'];
            }

            $amount = match ($employee) {
                'accountant' => $preview['accountant'],
                'marketing' => $preview['marketing'],
                default => $preview['teacher'],
            };
            $userId = $this->salaries->ensureEmployeeUser($employee);
            $status = (string) ($input['status'] ?? 'paid');
            $paidAt = (string) $input['paid_at'] . ' 00:00:00';
            $employeeLabel = self::EMPLOYEES[$employee];

            if ($status === 'paid' && $amount <= 0) {
                return ['Kwota wynagrodzenia musi być większa od zera przed utworzeniem kosztu.'];
            }

            $this->salaries->beginTransaction();
            $payoutId = $this->salaries->createPayout([
                'user_id' => $userId,
                'salary_rule_id' => (int) $rule['id'],
                'period_start' => $from,
                'period_end' => $to,
                'base_amount' => number_format($amount, 2, '.', ''),
                'net_profit_base' => number_format(
                    match ($employee) {
                        'teacher' => $preview['teacher_profit_base'],
                        'accountant' => $preview['distribution_pool'],
                        default => $preview['profit_base'],
                    },
                    2,
                    '.',
                    ''
                ),
                'total_amount' => number_format($amount, 2, '.', ''),
                'status' => $status,
                'paid_at' => $paidAt,
                'notes' => trim((string) ($input['notes'] ?? '')) ?: ($employee === 'marketing' ? 'Nowe uczennice z reklamy: ' . $preview['marketing_new_students'] . ' x ' . number_format($preview['marketing_rate'], 2, '.', '') . ' PLN' : null),
            ]);

            if ($status === 'paid') {
                $this->salaries->createLinkedExpense($payoutId, [
                    'total_amount' => number_format($amount, 2, '.', ''),
                    'paid_at' => $paidAt,
                    'expense_description' => 'Wynagrodzenie - ' . $employeeLabel . ' - ' . $month,
                ]);
            }

            $this->salaries->commit();

            return [];
        } catch (Throwable $exception) {
            $this->salaries->rollBackIfActive();

            return ['Nie udało się zapisać wypłaty: ' . $exception->getMessage()];
        }
    }

    public function cancel(int $id): array
    {
        if ($id <= 0) {
            return ['Brak ID wypłaty.'];
        }

        try {
            $this->salaries->cancel($id);

            return [];
        } catch (Throwable $exception) {
            return ['Nie udało się anulować wypłaty: ' . $exception->getMessage()];
        }
    }

    private function monthRange(string $month): array
    {
        $date = new DateTimeImmutable($month . '-01');

        return [
            $date->modify('first day of this month')->format('Y-m-d'),
            $date->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    private function calculateRuleAmount(?array $rule, float $profitBase, int $newStudents): float
    {
        if ($rule === null) {
            return 0.0;
        }

        return match ((string) ($rule['rule_type'] ?? '')) {
            'fixed' => max(0, (float) ($rule['amount'] ?? 0)),
            'percentage_net_profit' => max(0, $profitBase) * (max(0, (float) ($rule['percentage'] ?? 0)) / 100),
            'per_student' => max(0, $newStudents) * max(0, (float) ($rule['amount'] ?? 0)),
            'remaining_profit' => max(0, $profitBase),
            default => 0.0,
        };
    }

    private function calculatePriorityAmount(?array $rule, int $newStudents): float
    {
        if ($rule === null) {
            return 0.0;
        }

        return match ((string) ($rule['rule_type'] ?? '')) {
            'fixed' => max(0, (float) ($rule['amount'] ?? 0)),
            'per_student' => max(0, $newStudents) * max(0, (float) ($rule['amount'] ?? 0)),
            default => 0.0,
        };
    }

    private function calculatePercentageAmount(?array $rule, float $pool): float
    {
        $percentage = min(100, max(0, (float) ($rule['percentage'] ?? 0)));

        return max(0, $pool) * ($percentage / 100);
    }

    private function ruleLabel(string $employee, ?array $rule, bool $usesRemainingProfit = false): string
    {
        if ($rule === null) {
            return $employee;
        }

        return match ((string) ($rule['rule_type'] ?? '')) {
            'fixed' => $employee . ' (stała kwota)',
            'percentage_net_profit' => sprintf(
                '%s (%s%% %s)',
                $employee,
                $this->compactNumber((float) ($rule['percentage'] ?? 0)),
                $usesRemainingProfit ? 'pozostałego zysku' : 'puli do podziału'
            ),
            'per_student' => sprintf(
                '%s (%s zł / nowa uczennica)',
                $employee,
                $this->compactNumber((float) ($rule['amount'] ?? 0))
            ),
            'remaining_profit' => $employee . ' (pozostały zysk)',
            default => $employee,
        };
    }

    private function compactNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
