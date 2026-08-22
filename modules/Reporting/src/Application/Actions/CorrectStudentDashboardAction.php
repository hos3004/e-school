<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Modules\Reporting\Domain\Models\StudentDashboard;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تصحيح يدوي موثّق لعدّاد في لوحة طالب — لا حذف ولا تعديل صامت.
 *
 * القاعدة: أي تصحيح يتطلب سببًا مكتوبًا بطول معلن في config،
 * والعدّادات لا تقبل قيمًا سالبة أبدًا.
 *
 * الترتيب: حراس ← معاملة ← (لا أحداث — التصحيح يمر عبر التدقيق).
 */
final readonly class CorrectStudentDashboardAction
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    /**
     * @param array<string, mixed> $data enrollment_id · column · value · reason
     */
    public function execute(array $data): StudentDashboard
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        $minChars = (int) config('reporting.correction.reason_min_chars', 5);
        $maxChars = (int) config('reporting.correction.reason_max_chars', 500);

        if (mb_strlen($reason) < $minChars || mb_strlen($reason) > $maxChars) {
            throw BusinessRuleViolation::make(
                'reporting.correction_reason_length',
                'reporting::errors.correction_reason_length',
                ['min' => $minChars, 'max' => $maxChars],
            );
        }

        $allowed = [
            'sessions_total', 'sessions_attended', 'sessions_missed',
            'violations_count', 'freezes_count',
        ];

        $column = (string) ($data['column'] ?? '');

        if (!in_array($column, $allowed, true)) {
            throw BusinessRuleViolation::make(
                'reporting.unknown_correction_column',
                'reporting::errors.unknown_correction_column',
                ['column' => $column],
            );
        }

        $value = (int) ($data['value'] ?? -1);

        if ($value < 0) {
            throw BusinessRuleViolation::make(
                'reporting.negative_counter_value',
                'reporting::errors.negative_counter_value',
                ['value' => $value],
            );
        }

        return $this->transaction->run(function () use ($data, $column, $value): StudentDashboard {
            /** @var StudentDashboard|null $dashboard */
            $dashboard = StudentDashboard::query()
                ->where('enrollment_id', (string) $data['enrollment_id'])
                ->lockForUpdate()
                ->first();

            if ($dashboard === null) {
                throw BusinessRuleViolation::make(
                    'reporting.dashboard_not_found',
                    'reporting::errors.dashboard_not_found',
                    ['enrollment_id' => (string) $data['enrollment_id']],
                );
            }

            $dashboard->{$column} = $value;
            $dashboard->recomputeAttendanceRate();
            $dashboard->save();

            return $dashboard;
        });
    }
}
