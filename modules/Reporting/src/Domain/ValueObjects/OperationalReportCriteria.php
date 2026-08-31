<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/** المعايير النهائية بعد تحويل فترة المستخدم المحلية إلى UTC وتطبيق نطاق الصلاحية. */
final readonly class OperationalReportCriteria
{
    /**
     * @param list<string> $statuses
     * @param list<string> $attendanceStatuses
     * @param list<string> $sessionTypes
     */
    public function __construct(
        public string $organizationId,
        public CarbonImmutable $fromUtc,
        public CarbonImmutable $untilUtcExclusive,
        public string $timezone,
        public string $preset,
        public ?string $fromDate,
        public ?string $untilDate,
        public array $statuses = [],
        public array $attendanceStatuses = [],
        public array $sessionTypes = [],
        public ?string $studentProfileId = null,
        public ?string $staffProfileId = null,
        public ?string $groupId = null,
        public ?string $courseId = null,
        public ?string $originalStaffProfileId = null,
        public ?string $reportStatus = null,
        public string $search = '',
        public bool $forcedToOwnTeacher = false,
    ) {}

    /** @return array<string, mixed> */
    public function toQueryParameters(): array
    {
        return array_filter([
            'preset' => $this->preset,
            'from' => $this->fromDate,
            'until' => $this->untilDate,
            'statuses' => $this->statuses,
            'attendance_statuses' => $this->attendanceStatuses,
            'session_types' => $this->sessionTypes,
            'student_profile_id' => $this->studentProfileId,
            'staff_profile_id' => $this->forcedToOwnTeacher ? null : $this->staffProfileId,
            'group_id' => $this->groupId,
            'course_id' => $this->courseId,
            'original_staff_profile_id' => $this->originalStaffProfileId,
            'report_status' => $this->reportStatus,
            'search' => $this->search !== '' ? $this->search : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    public function cacheKey(): string
    {
        return hash('sha256', json_encode([
            $this->organizationId,
            $this->fromUtc->toIso8601String(),
            $this->untilUtcExclusive->toIso8601String(),
            $this->timezone,
            $this->statuses,
            $this->attendanceStatuses,
            $this->sessionTypes,
            $this->studentProfileId,
            $this->staffProfileId,
            $this->groupId,
            $this->courseId,
            $this->originalStaffProfileId,
            $this->reportStatus,
            $this->search,
        ], JSON_THROW_ON_ERROR));
    }
}
