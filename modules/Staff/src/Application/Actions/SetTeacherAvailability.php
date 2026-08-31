<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;

final readonly class SetTeacherAvailability
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function execute(
        StaffProfile $profile,
        int $weekday,
        string $startTime,
        string $endTime,
        string $timezone,
        CarbonImmutable|string $effectiveFrom,
        CarbonImmutable|string|null $effectiveTo = null,
        ?string $actorId = null,
        ?string $reason = null,
    ): TeacherAvailability {
        if ($weekday < 0 || $weekday > 6) {
            throw BusinessRuleViolation::make(
                'staff.availability_weekday_invalid',
                'staff::errors.availability_weekday_invalid',
                ['weekday' => (string) $weekday],
            );
        }

        if ($startTime >= $endTime) {
            throw BusinessRuleViolation::make(
                'staff.availability_time_invalid',
                'staff::errors.availability_time_invalid',
                ['start_time' => $startTime, 'end_time' => $endTime],
            );
        }

        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw BusinessRuleViolation::make(
                'staff.availability_timezone_invalid',
                'staff::errors.availability_timezone_invalid',
                ['timezone' => $timezone],
            );
        }

        $from = $effectiveFrom instanceof CarbonImmutable ? $effectiveFrom : CarbonImmutable::parse($effectiveFrom);
        $to = $effectiveTo === null ? null : ($effectiveTo instanceof CarbonImmutable ? $effectiveTo : CarbonImmutable::parse($effectiveTo));

        if ($to !== null && $to->lt($from)) {
            throw BusinessRuleViolation::make(
                'staff.availability_period_invalid',
                'staff::errors.contract_period_invalid',
                ['effective_from' => $from->toDateString(), 'effective_to' => $to->toDateString()],
            );
        }

        $this->assertNoTimeOverlap($profile, $weekday, $startTime, $endTime, $from, $to);

        $availability = DB::transaction(function () use ($profile, $weekday, $startTime, $endTime, $timezone, $from, $to): TeacherAvailability {
            return TeacherAvailability::query()->create([
                'staff_profile_id' => $profile->id,
                'weekday' => $weekday,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $timezone,
                'effective_from' => $from,
                'effective_to' => $to,
            ]);
        });

        if ($actorId !== null) {
            $this->audit->record(
                organizationId: (string) $profile->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'staff.availability_set',
                auditableType: 'teacher_availability',
                auditableId: (string) $availability->getKey(),
                oldValues: null,
                newValues: [
                    'weekday' => $weekday,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'effective_from' => $from->toDateString(),
                    'effective_to' => $to?->toDateString(),
                ],
                reason: trim((string) $reason) === '' ? null : trim((string) $reason),
            );
        }

        return $availability;
    }

    /**
     * لا يجوز أن تتقاطع فترتان بنفس اليوم الزمني للمعلم — المرفوضة
     * والمنتهية لا تُحتسب في التداخل.
     */
    private function assertNoTimeOverlap(
        StaffProfile $profile,
        int $weekday,
        string $startTime,
        string $endTime,
        CarbonImmutable $from,
        ?CarbonImmutable $to,
    ): void {
        $overlap = TeacherAvailability::query()
            ->forProfile((string) $profile->getKey())
            ->where('weekday', $weekday)
            ->where('approval_status', '!=', TeacherAvailabilityApprovalStatus::Rejected->value)
            ->whereDate('effective_from', '<=', $to ?? $from)
            ->where(
                fn ($query) => $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from),
            )
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($overlap) {
            throw BusinessRuleViolation::make(
                'staff.availability_overlaps',
                'staff::errors.availability_overlaps',
                ['weekday' => (string) $weekday, 'start_time' => $startTime, 'end_time' => $endTime],
            );
        }
    }
}
