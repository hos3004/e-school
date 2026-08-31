<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Reporting\Domain\Exceptions\InvalidReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Throwable;

final readonly class OperationalReportCriteriaFactory
{
    public function __construct(private StaffQueries $staffQueries) {}

    /** @param array<string, mixed> $input */
    public function fromInput(array $input, Authenticatable $user): OperationalReportCriteria
    {
        $organizationId = data_get($user, 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            throw new InvalidReportCriteria(__('reporting::messages.organization_required'));
        }

        $timezone = $this->timezone((string) (data_get($user, 'timezone') ?: config('app.timezone')));
        $preset = $this->preset($input['preset'] ?? null);
        [$fromLocal, $untilLocalExclusive] = $this->period($preset, $input, $timezone);

        $maxDays = max(1, (int) config('reporting.operational.max_period_days'));
        if ($fromLocal->greaterThanOrEqualTo($untilLocalExclusive)
            || $fromLocal->diffInDays($untilLocalExclusive) > $maxDays) {
            throw new InvalidReportCriteria(__('reporting::messages.invalid_period', ['days' => $maxDays]));
        }

        $staffProfileId = $this->nullableString($input['staff_profile_id'] ?? null);
        $forcedToOwnTeacher = !$user->can('student.view.any') && !$user->can('staff.view.any');

        if ($forcedToOwnTeacher) {
            $profile = $this->staffQueries->findActiveProfileForUser((string) $user->getAuthIdentifier());
            $staffProfileId = is_array($profile) && isset($profile['id'])
                ? (string) $profile['id']
                : '__no_accessible_teacher_profile__';
        }

        return new OperationalReportCriteria(
            organizationId: $organizationId,
            fromUtc: $fromLocal->utc(),
            untilUtcExclusive: $untilLocalExclusive->utc(),
            timezone: $timezone,
            preset: $preset,
            fromDate: $fromLocal->toDateString(),
            untilDate: $untilLocalExclusive->subDay()->toDateString(),
            statuses: $this->allowedValues($input['statuses'] ?? [], array_column(SessionStatus::cases(), 'value')),
            attendanceStatuses: $this->allowedValues($input['attendance_statuses'] ?? [], array_column(AttendanceStatus::cases(), 'value')),
            sessionTypes: $this->allowedValues($input['session_types'] ?? [], array_keys((array) config('academic.session_types', []))),
            studentProfileId: $this->nullableString($input['student_profile_id'] ?? null),
            staffProfileId: $staffProfileId,
            groupId: $this->nullableString($input['group_id'] ?? null),
            courseId: $this->nullableString($input['course_id'] ?? null),
            originalStaffProfileId: $this->nullableString($input['original_staff_profile_id'] ?? null),
            reportStatus: $this->allowedValue($input['report_status'] ?? null, ['submitted', 'late', 'missing']),
            search: mb_substr(trim((string) ($input['search'] ?? '')), 0, (int) config('reporting.operational.search_max_chars')),
            forcedToOwnTeacher: $forcedToOwnTeacher,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(string $preset, array $input, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $weekStartsAt = (int) config('reporting.operational.week_starts_at');

        return match ($preset) {
            'today' => [$today, $today->addDay()],
            'yesterday' => [$today->subDay(), $today],
            'this_week' => [$today->startOfWeek($weekStartsAt), $today->startOfWeek($weekStartsAt)->addWeek()],
            'previous_week' => [$today->startOfWeek($weekStartsAt)->subWeek(), $today->startOfWeek($weekStartsAt)],
            'this_month' => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
            default => $this->customPeriod($input, $timezone),
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function customPeriod(array $input, string $timezone): array
    {
        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($input['from'] ?? ''), $timezone);
            $until = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($input['until'] ?? ''), $timezone);
        } catch (Throwable) {
            $from = false;
            $until = false;
        }

        if (!$from instanceof CarbonImmutable || !$until instanceof CarbonImmutable) {
            throw new InvalidReportCriteria(__('reporting::messages.invalid_period_dates'));
        }

        return [$from->startOfDay(), $until->startOfDay()->addDay()];
    }

    private function preset(mixed $value): string
    {
        $preset = is_string($value) ? $value : 'this_week';

        return in_array($preset, ['today', 'yesterday', 'this_week', 'previous_week', 'this_month', 'custom'], true)
            ? $preset
            : 'this_week';
    }

    private function timezone(string $timezone): string
    {
        try {
            return (new DateTimeZone($timezone))->getName();
        } catch (Throwable) {
            return (string) config('app.timezone');
        }
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private function allowedValues(mixed $values, array $allowed): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && in_array($value, $allowed, true),
        )));
    }

    /** @param list<string> $allowed */
    private function allowedValue(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
