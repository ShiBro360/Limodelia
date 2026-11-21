<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\FormatHelper;
use App\Repositories\StudentRepository;
use App\Validators\StudentValidator;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class StudentService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly StudentValidator $validator,
        private readonly ?StorageService $storage = null,
        private readonly array $lessonSchedule = []
    ) {
    }

    public function filters(array $query): array
    {
        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'group' => (int) ($query['group'] ?? 0),
            'subscription' => (int) ($query['subscription'] ?? 0),
            'status' => (string) ($query['status'] ?? ''),
            'sort' => (string) ($query['sort'] ?? 'newest'),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => (int) ($query['per_page'] ?? 10),
        ];
        $filters['per_page'] = in_array($filters['per_page'], [10, 25, 50], true) ? $filters['per_page'] : 10;

        return $filters;
    }

    public function paginated(array $filters): array
    {
        $total = $this->students->count($filters);
        $pageCount = max(1, (int) ceil($total / $filters['per_page']));
        $filters['page'] = min($filters['page'], $pageCount);
        $students = $this->students->list($filters);

        foreach ($students as &$student) {
            $student = array_merge($student, $this->subscriptionUsage((int) $student['id'], $student));
            $student['subscription_end_date'] = $student['next_payment_lesson_date'] ?? null;
        }
        unset($student);

        return [
            'students' => $students,
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => $filters,
        ];
    }

    public function findForEdit(int $id): ?array
    {
        return $this->students->findForEdit($id);
    }

    public function profile(int $id): array
    {
        $student = $id > 0 ? $this->students->findProfile($id) : null;
        $payments = $student ? $this->students->payments($id) : [];

        if ($student) {
            $student = array_merge($student, $this->subscriptionUsage($id, $student));
        }

        return [
            'student' => $student,
            'payments' => $payments,
            'subscription_history' => $student ? $this->students->subscriptionHistory($id) : [],
            'attendance' => $student ? $this->students->attendance($id) : [],
        ];
    }

    public function save(array $input, ?int $studentId = null, ?callable $afterSave = null): array
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        $data = $this->normalizeStudentData($input);

        try {
            $this->students->beginTransaction();
            $savedStudentId = $studentId;

            if ($studentId === null) {
                $savedStudentId = $this->students->create($data);
            } else {
                $this->students->update($studentId, $data);
            }

            $savedStudentId = (int) $savedStudentId;
            $photoPath = $this->savePhoto((string) ($input['photo_data'] ?? ''), $savedStudentId);
            if ($photoPath !== null) {
                $this->students->updatePhoto($savedStudentId, $photoPath);
            }

            if ($this->students->activeSubscriptionId($savedStudentId) !== $data['subscription_id']) {
                $this->students->closeActiveSubscriptions($savedStudentId);
                $this->students->createSubscription($savedStudentId, $data['subscription_id'], $data['enrollment_date']);
            }

            if ($afterSave !== null) { $afterSave($savedStudentId); }
            $this->students->commit();

            return [];
        } catch (Throwable $exception) {
            $this->students->rollBackIfActive();

            return ['Nie udało się zapisać uczennicy: ' . $exception->getMessage()];
        }
    }

    public function softDelete(int $id): array
    {
        if ($id <= 0) {
            return ['Brak ID uczennicy do archiwizacji.'];
        }

        $this->students->softDelete($id);

        return [];
    }

    public function adjustRemainingLessons(int $studentId, int $remainingLessons, string $reason): array
    {
        $reason = trim($reason);
        if ($studentId <= 0) {
            return ['Nieprawidłowa uczennica.'];
        }
        if ($reason === '' || mb_strlen($reason) < 3) {
            return ['Podaj krótki powód korekty.'];
        }

        $student = $this->students->findProfile($studentId);
        if ($student === null) {
            return ['Nie znaleziono uczennicy.'];
        }

        $rawUsage = $this->subscriptionUsage($studentId, $student, false);
        $currentUsage = $this->subscriptionUsage($studentId, $student, true);
        $payment = $this->students->currentCompletedSubscriptionPayment($studentId, date('Y-m-d'));
        $paidLessons = (int) ($rawUsage['paid_lessons_count'] ?? 0);

        if ($payment === null || !(bool) ($rawUsage['current_subscription_paid'] ?? false)) {
            return ['Uczennica nie ma aktywnego opłaconego abonamentu.'];
        }
        if ($remainingLessons < 0 || $remainingLessons > $paidLessons) {
            return ['Liczba pozostałych zajęć musi mieścić się w zakresie 0-' . $paidLessons . '.'];
        }

        try {
            $this->students->setRemainingLessonsOverride(
                $studentId,
                (int) $payment['id'],
                (int) ($currentUsage['remaining_lessons'] ?? 0),
                $remainingLessons,
                (int) ($rawUsage['used_lessons'] ?? 0),
                $reason
            );
        } catch (Throwable $exception) {
            return ['Nie udało się zapisać korekty: ' . $exception->getMessage()];
        }

        return [];
    }

    private function normalizeStudentData(array $input): array
    {
        return [
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'birth_date' => ($input['birth_date'] ?? '') !== '' ? (string) $input['birth_date'] : null,
            'phone' => FormatHelper::normalizePhone($input['phone'] ?? null),
            'telegram_username' => FormatHelper::normalizeTelegram($input['telegram_username'] ?? null),
            'guardian_name' => trim((string) ($input['guardian_name'] ?? '')) ?: null,
            'guardian_phone' => FormatHelper::normalizePhone($input['guardian_phone'] ?? null),
            'guardian_telegram_username' => FormatHelper::normalizeTelegram($input['guardian_telegram_username'] ?? null),
            'enrollment_date' => ($input['enrollment_date'] ?? '') !== '' ? (string) $input['enrollment_date'] : date('Y-m-d'),
            'student_group_id' => (int) $input['student_group_id'],
            'subscription_id' => (int) $input['subscription_id'],
            'status' => (string) $input['status'],
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
        ];
    }

    private function savePhoto(string $dataUrl, int $studentId): ?string
    {
        if ($dataUrl === '') {
            return null;
        }

        if (!preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            throw new RuntimeException('Nieprawidłowy format zdjęcia.');
        }

        $binary = base64_decode($matches[1], true);
        if ($binary === false || strlen($binary) > 2_500_000) {
            throw new RuntimeException('Zdjęcie jest nieprawidłowe albo zbyt duże.');
        }

        $storage = $this->storage ?? new StorageService(new SettingsService(new \App\Repositories\SettingsRepository(db())));
        $uploadDir = $storage->localStudentAvatarDirectory();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Nie udało się utworzyć folderu na zdjęcia.');
        }

        $fileName = 'student_' . $studentId . '_' . time() . '.jpg';
        $absolutePath = $uploadDir . '/' . $fileName;

        if (file_put_contents($absolutePath, $binary) === false) {
            throw new RuntimeException('Nie udało się zapisać zdjęcia.');
        }

        return $storage->localStudentAvatarPath($fileName);
    }

    private function subscriptionUsage(int $studentId, array $student, bool $applyOverride = true): array
    {
        $today = new DateTimeImmutable('today');
        $payment = $this->students->currentCompletedSubscriptionPayment($studentId, $today->format('Y-m-d'));
        $defaultLessons = (int) ($student['lessons_count'] ?? 0);

        if ($payment === null) {
            $currentMonthStart = $today->modify('first day of this month');

            return [
                'current_subscription_paid' => false,
                'paid_lessons_count' => $defaultLessons,
                'used_lessons' => 0,
                'remaining_lessons' => 0,
                'current_period_start' => $currentMonthStart->format('Y-m-d'),
                'current_period_end' => $currentMonthStart->modify('last day of this month')->format('Y-m-d'),
                'last_allowed_lesson_date' => null,
                'next_payment_month' => $currentMonthStart->format('Y-m-d'),
            ];
        }

        $periodStart = new DateTimeImmutable((string) $payment['payment_date']);
        $lessonsCount = max(0, (int) ($payment['lessons_count'] ?? $defaultLessons));
        $weekdays = $this->subscriptionWeekdays($lessonsCount, $periodStart);
        $attendanceUsage = $this->subscriptionUsageFromAttendance((int) $payment['id'], $periodStart, $lessonsCount, $weekdays, $today);
        if ($attendanceUsage !== null) {
            return $applyOverride ? $this->applyRemainingLessonsOverride($payment, $attendanceUsage, $weekdays, $today) : $attendanceUsage;
        }

        $lessonDates = $this->subscriptionLessonDates($periodStart, $lessonsCount, $weekdays);
        $lastAllowedLessonDate = $lessonDates !== [] ? end($lessonDates) : null;
        $nextPaymentLessonDate = $this->nextLessonDateAfter($lastAllowedLessonDate ?? $periodStart, $weekdays);
        $usedLessons = $this->countUsedLessonDates($lessonDates, $today);
        $isCurrent = $nextPaymentLessonDate === null || $today < $nextPaymentLessonDate;

        $usage = [
            'current_subscription_paid' => $isCurrent,
            'paid_lessons_count' => $lessonsCount,
            'used_lessons' => $usedLessons,
            'remaining_lessons' => $isCurrent ? max(0, $lessonsCount - $usedLessons) : 0,
            'current_period_start' => $periodStart->format('Y-m-d'),
            'current_period_end' => $lastAllowedLessonDate?->format('Y-m-d'),
            'last_allowed_lesson_date' => $lastAllowedLessonDate?->format('Y-m-d'),
            'next_payment_lesson_date' => $nextPaymentLessonDate?->format('Y-m-d'),
            'next_payment_month' => ($nextPaymentLessonDate ?? $periodStart)->format('Y-m-d'),
        ];

        return $applyOverride ? $this->applyRemainingLessonsOverride($payment, $usage, $weekdays, $today) : $usage;
    }

    private function applyRemainingLessonsOverride(array $payment, array $usage, array $weekdays, DateTimeImmutable $today): array
    {
        if ($payment['remaining_lessons_override'] === null) {
            return $usage;
        }

        $paidLessons = max(0, (int) ($usage['paid_lessons_count'] ?? 0));
        $baseUsed = max(0, (int) ($usage['used_lessons'] ?? 0));
        $baseline = max(0, (int) ($payment['remaining_lessons_override_used_baseline'] ?? $baseUsed));
        $additionalUsed = max(0, $baseUsed - $baseline);
        $remaining = max(0, min($paidLessons, (int) $payment['remaining_lessons_override'] - $additionalUsed));

        $usage['remaining_lessons'] = $remaining;
        $usage['used_lessons'] = max(0, $paidLessons - $remaining);
        $usage['remaining_lessons_adjusted'] = true;

        $nextPaymentDate = $this->nextPaymentDateFromBalance($today, $weekdays, $remaining);
        $usage['next_payment_lesson_date'] = $nextPaymentDate?->format('Y-m-d');
        $usage['next_payment_month'] = ($nextPaymentDate ?? $today)->format('Y-m-d');

        return $usage;
    }

    private function nextPaymentDateFromBalance(DateTimeImmutable $today, array $weekdays, int $remaining): ?DateTimeImmutable
    {
        if ($weekdays === []) {
            return null;
        }

        $lessonsLeft = $remaining;
        for ($date = $today; $date <= $today->modify('+1 year'); $date = $date->modify('+1 day')) {
            if (!in_array((int) $date->format('N'), $weekdays, true)) {
                continue;
            }
            if ($lessonsLeft === 0) {
                return $date;
            }
            $lessonsLeft--;
        }

        return null;
    }

    private function countUsedLessonDates(array $lessonDates, DateTimeImmutable $today): int
    {
        $used = 0;

        foreach ($lessonDates as $date) {
            if ($date <= $today) {
                $used++;
            }
        }

        return $used;
    }

    private function subscriptionUsageFromAttendance(int $paymentId, DateTimeImmutable $periodStart, int $lessonsCount, array $weekdays, DateTimeImmutable $today): ?array
    {
        if ($lessonsCount <= 0 || $weekdays === []) {
            return null;
        }

        $attendance = $this->students->attendanceForPayment($paymentId);
        if ($attendance === []) {
            return null;
        }

        $attendanceByDate = [];
        foreach ($attendance as $record) {
            $attendanceByDate[(string) $record['lesson_date']] = $record;
        }

        $projectedUsed = 0;
        $actualUsed = 0;
        $lastAllowedLessonDate = null;
        $maxDate = $periodStart->modify('+1 year');

        for ($date = $periodStart; $date <= $maxDate && $projectedUsed < $lessonsCount; $date = $date->modify('+1 day')) {
            if (!in_array((int) $date->format('N'), $weekdays, true)) {
                continue;
            }

            $dateKey = $date->format('Y-m-d');
            $record = $attendanceByDate[$dateKey] ?? null;
            $countsAsUsed = true;

            if ($record !== null) {
                $countsAsUsed = (int) ($record['counts_as_used_lesson'] ?? 1) === 1;
            }

            if ($countsAsUsed) {
                $projectedUsed++;
                if ($date < $today || ($date->format('Y-m-d') === $today->format('Y-m-d') && $record !== null)) {
                    $actualUsed++;
                }
            }

            $lastAllowedLessonDate = $date;
        }

        $nextPaymentLessonDate = $lastAllowedLessonDate !== null ? $this->nextLessonDateAfter($lastAllowedLessonDate, $weekdays) : null;
        $isCurrent = $nextPaymentLessonDate === null || $today < $nextPaymentLessonDate;

        return [
            'current_subscription_paid' => $isCurrent,
            'paid_lessons_count' => $lessonsCount,
            'used_lessons' => min($lessonsCount, $actualUsed),
            'remaining_lessons' => $isCurrent ? max(0, $lessonsCount - min($lessonsCount, $actualUsed)) : 0,
            'current_period_start' => $periodStart->format('Y-m-d'),
            'current_period_end' => $lastAllowedLessonDate?->format('Y-m-d'),
            'last_allowed_lesson_date' => $lastAllowedLessonDate?->format('Y-m-d'),
            'next_payment_lesson_date' => $nextPaymentLessonDate?->format('Y-m-d'),
            'next_payment_month' => ($nextPaymentLessonDate ?? $periodStart)->format('Y-m-d'),
        ];
    }

    private function subscriptionLessonDates(DateTimeImmutable $paymentDate, int $lessonsCount, array $weekdays): array
    {
        if ($lessonsCount <= 0 || $weekdays === []) {
            return [];
        }

        $dates = [];
        for ($date = $paymentDate; count($dates) < $lessonsCount; $date = $date->modify('+1 day')) {
            if (!in_array((int) $date->format('N'), $weekdays, true)) {
                continue;
            }

            $dates[] = $date;
        }

        return $dates;
    }

    private function nextLessonDateAfter(DateTimeImmutable $date, array $weekdays): ?DateTimeImmutable
    {
        if ($weekdays === []) {
            return null;
        }

        for ($nextDate = $date->modify('+1 day'); $nextDate <= $date->modify('+40 days'); $nextDate = $nextDate->modify('+1 day')) {
            if (in_array((int) $nextDate->format('N'), $weekdays, true)) {
                return $nextDate;
            }
        }

        return null;
    }

    private function subscriptionWeekdays(int $lessonsCount, ?DateTimeImmutable $paymentDate = null): array
    {
        $schedule = $this->lessonSchedule;
        if ($schedule === []) {
            $schedule = [
                ['weekday' => 1],
                ['weekday' => 3],
            ];
        }

        $weekdays = [];
        foreach ($schedule as $slot) {
            $weekday = (int) ($slot['weekday'] ?? 0);
            if ($weekday >= 1 && $weekday <= 7 && !in_array($weekday, $weekdays, true)) {
                $weekdays[] = $weekday;
            }
        }

        sort($weekdays);

        if ($weekdays === []) {
            return [];
        }

        $weeklyLessons = max(1, (int) ceil($lessonsCount / 4));
        if ($weeklyLessons === 1 && $paymentDate !== null) {
            $paymentWeekday = (int) $paymentDate->format('N');
            if (in_array($paymentWeekday, $weekdays, true)) {
                return [$paymentWeekday];
            }

            for ($date = $paymentDate->modify('+1 day'); $date <= $paymentDate->modify('+7 days'); $date = $date->modify('+1 day')) {
                $weekday = (int) $date->format('N');
                if (in_array($weekday, $weekdays, true)) {
                    return [$weekday];
                }
            }
        }

        return array_slice($weekdays, 0, min($weeklyLessons, count($weekdays)));
    }
}
