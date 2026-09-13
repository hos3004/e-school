<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;

/**
 * إتاحة أسبوع كامل لكل معلم عامل في المؤسسة — قرار إداري صريح.
 *
 * النوافذ القائمة تُستبدل كلها بسبعة أيام كاملة. سحب النافذة لا يمس أي حصة
 * مجدولة ولا تسكينًا قائمًا؛ أثره على الترشيحات القادمة فقط، فلا يعيد هذا
 * الإجراء جدولة شيء ولا يلغي حصة.
 *
 * كل معلم في معاملة مستقلة: تعذّر معلم لا يُسقط بقية المعلمين ويُبلَّغ عنه.
 */
final readonly class SetAllTeachersFullAvailability
{
    private const DAY_START = '00:00:00';

    private const DAY_END = '23:59:59';

    public function __construct(
        private SetTeacherAvailability $set,
        private RemoveTeacherAvailability $remove,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array{teachers: int, slots: int, removed: int, skipped: list<array{staff_profile_id: string, error: string}>}
     */
    public function execute(
        string $organizationId,
        string $reason,
        ?string $actorId = null,
        ?string $timezone = null,
    ): array {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'staff.availability_reason_required',
                'staff::errors.availability_reason_required',
            );
        }

        $zone = $timezone ?? (string) config('app.timezone');

        if (!in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw BusinessRuleViolation::make(
                'staff.availability_timezone_invalid',
                'staff::errors.availability_timezone_invalid',
                ['timezone' => $zone],
            );
        }

        $from = CarbonImmutable::now($zone)->startOfDay();
        $teachers = 0;
        $slots = 0;
        $removed = 0;
        $skipped = [];

        foreach (StaffProfile::query()->forOrganization($organizationId)->active()->orderBy('id')->lazyById() as $profile) {
            try {
                [$created, $dropped] = $this->replaceWeek($profile, $zone, $from, $actorId, $reason);
                $teachers++;
                $slots += $created;
                $removed += $dropped;
            } catch (BusinessRuleViolation $violation) {
                $skipped[] = ['staff_profile_id' => (string) $profile->getKey(), 'error' => $violation->rule];
            }
        }

        $summary = ['teachers' => $teachers, 'slots' => $slots, 'removed' => $removed, 'skipped' => $skipped];

        $this->audit->record(
            organizationId: $organizationId,
            actorId: $actorId,
            actorType: $actorId === null ? 'system' : 'user',
            action: 'staff.availability_bulk_set_all',
            auditableType: 'staff_profiles',
            auditableId: null,
            oldValues: null,
            newValues: [
                'teachers' => $teachers, 'slots' => $slots, 'removed' => $removed,
                'skipped' => count($skipped), 'timezone' => $zone,
                'effective_from' => $from->toDateString(),
                'window' => self::DAY_START.'-'.self::DAY_END,
            ],
            reason: trim($reason),
        );

        return $summary;
    }

    /** @return array{0: int, 1: int} */
    private function replaceWeek(
        StaffProfile $profile,
        string $zone,
        CarbonImmutable $from,
        ?string $actorId,
        string $reason,
    ): array {
        return DB::transaction(function () use ($profile, $zone, $from, $actorId, $reason): array {
            $dropped = 0;
            $existing = TeacherAvailability::query()
                ->forProfile((string) $profile->getKey())
                ->orderBy('id')
                ->get();

            foreach ($existing as $slot) {
                $this->remove->execute($slot, $actorId, $reason);
                $dropped++;
            }

            $created = 0;
            for ($weekday = 0; $weekday <= 6; $weekday++) {
                $this->set->execute(
                    $profile, $weekday, self::DAY_START, self::DAY_END,
                    $zone, $from, null, $actorId, $reason,
                );
                $created++;
            }

            return [$created, $dropped];
        });
    }
}
