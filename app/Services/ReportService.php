<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReportRepository;
use App\Validators\ReportValidator;
use DateTimeImmutable;
use Throwable;

final class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly ReportValidator $validator,
        private readonly DashboardService $dashboard,
        private readonly StudentService $students,
        private readonly PaymentService $payments,
        private readonly ExpenseService $expenses,
        private readonly SalaryService $salaries,
        private readonly TripService $trips,
        private readonly StorageService $storage
    ) {
    }

    public function filters(array $query): array
    {
        $month = (string) ($query['month'] ?? date('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $date = new DateTimeImmutable($month . '-01');
        $from = (string) ($query['from'] ?? $date->modify('first day of this month')->format('Y-m-d'));
        $to = (string) ($query['to'] ?? $date->modify('last day of this month')->format('Y-m-d'));
        $type = (string) ($query['type'] ?? 'financial_summary');

        if (!in_array($type, ReportValidator::TYPES, true)) {
            $type = 'financial_summary';
        }

        $errors = $this->validator->validate(['type' => $type, 'from' => $from, 'to' => $to]);
        if ($errors !== []) {
            $from = $date->modify('first day of this month')->format('Y-m-d');
            $to = $date->modify('last day of this month')->format('Y-m-d');
        }

        return [
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'type' => $type,
        ];
    }

    public function generate(array $filters, bool $store = false): array
    {
        $errors = $this->validator->validate($filters);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $from = $filters['from'];
        $to = $filters['to'];
        $type = $filters['type'];
        $financial = $this->reports->financialSummary($from, $to);
        $financial['net_profit'] = (float) $financial['income'] - (float) $financial['expenses'];
        $year = (int) substr($from, 0, 4);

        $data = [
            'errors' => [],
            'stored_report_id' => null,
            'filters' => $filters,
            'financial' => $financial,
            'monthly_chart' => $this->reports->monthlyIncomeExpenses($year),
            'detail' => match ($type) {
                'payments_report' => $this->reports->paymentsReport($from, $to),
                'expenses_report' => $this->reports->expensesReport($from, $to),
                'salaries_report' => $this->reports->salariesReport($from, $to),
                'trips_report' => $this->reports->tripsReport($from, $to),
                'students_report' => $this->reports->studentsReport($from, $to),
                default => [
                    'payments' => $this->reports->paymentsReport($from, $to),
                    'expenses' => $this->reports->expensesReport($from, $to),
                    'salaries' => $this->reports->salariesReport($from, $to),
                    'trips' => $this->reports->tripsReport($from, $to),
                    'students' => $this->reports->studentsReport($from, $to),
                ],
            },
        ];

        if ($store) {
            try {
                $data['stored_report_id'] = $this->reports->storeGenerated($type, $from, $to, $filters + [
                    'exports_folder' => $this->storage->exportsFolder(),
                ]);
            } catch (Throwable $exception) {
                $data['errors'][] = 'Nie udało się zapisać metadanych raportu: ' . $exception->getMessage();
            }
        }

        return $data;
    }

    public function typeLabels(): array
    {
        return [
            'financial_summary' => 'Podsumowanie finansowe',
            'payments_report' => 'Raport płatności',
            'expenses_report' => 'Raport wydatków',
            'salaries_report' => 'Raport wynagrodzeń',
            'trips_report' => 'Raport wyjazdów',
            'students_report' => 'Raport uczennic',
        ];
    }
}
