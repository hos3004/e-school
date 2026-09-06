<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Carbon\CarbonImmutable;
use Modules\Reporting\Domain\ValueObjects\OperationalReportData;
use Modules\Sessions\Domain\Enums\SessionStatus;

/** Presentation totals use the same scoped result as the table and PDF, before pagination. */
final readonly class ConsoleReportPresentation
{
    /** @return array<string, mixed> */
    public function summarize(OperationalReportData $report): array
    {
        $days = [];
        $groups = [];
        $timezone = $report->criteria->timezone;
        for ($day = $report->criteria->fromUtc->setTimezone($timezone)->startOfDay();
            $day->lessThan($report->criteria->untilUtcExclusive);
            $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[$key] = ['date' => $key, 'planned' => 0, 'completed' => 0, 'pending' => 0];
        }
        $live = 0;
        $review = 0;
        foreach ($report->rows as $row) {
            $day = CarbonImmutable::parse($row->scheduledStart)->setTimezone($timezone)->toDateString();
            $completed = $row->status === SessionStatus::Completed->value;
            $needsReview = $row->status === SessionStatus::AwaitingReview->value || in_array($row->reportStatus, ['missing', 'late'], true);
            $live += (int) ($row->status === SessionStatus::InProgress->value);
            $review += (int) $needsReview;
            if (isset($days[$day])) {
                $days[$day]['planned']++;
                $days[$day]['completed'] += (int) $completed;
                $days[$day]['pending'] += (int) $needsReview;
            }
            $key = $row->groupId !== '' ? 'group:'.$row->groupId : 'individual:'.$row->courseId;
            $groups[$key] ??= [
                'id' => $row->groupId, 'courseId' => $row->courseId,
                'label' => $row->group !== '' ? $row->group : ($row->course.' · '.$row->sessionTypeLabel),
                'planned' => 0, 'completed' => 0, 'pending' => 0, 'absent' => 0,
            ];
            $groups[$key]['planned']++;
            $groups[$key]['completed'] += (int) $completed;
            $groups[$key]['pending'] += (int) $needsReview;
            $groups[$key]['absent'] += $row->absentCount;
        }

        return ['days' => array_values($days), 'groups' => array_values($groups), 'live' => $live, 'review' => $review];
    }
}
