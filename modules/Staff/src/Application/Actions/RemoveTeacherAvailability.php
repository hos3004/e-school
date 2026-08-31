<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Shared\Support\BusinessRuleViolation;

/**
 * حذف نافذة إتاحة لم تُعتمد بعد.
 *
 * الإتاحة المعتمدة داخلة في التسكين والجدولة، فسحبها قرار إداري لا تراجع
 * ذاتي من المعلم؛ لذلك يُمنع حذفها من هنا ويُترك للمشرف عبر مسار الاعتماد.
 */
final readonly class RemoveTeacherAvailability
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function execute(
        TeacherAvailability $availability,
        ?string $actorId = null,
        ?string $reason = null,
    ): void {
        if ($availability->approval_status === TeacherAvailabilityApprovalStatus::Approved) {
            throw BusinessRuleViolation::make(
                'staff.availability_approved_not_removable',
                'staff::errors.availability_approved_not_removable',
            );
        }

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find((string) $availability->staff_profile_id);
        $organizationId = $profile instanceof StaffProfile ? (string) $profile->organization_id : '';
        $payload = [
            'weekday' => $availability->weekday,
            'start_time' => $availability->start_time,
            'end_time' => $availability->end_time,
        ];
        $auditableId = (string) $availability->getKey();

        DB::transaction(function () use ($availability): void {
            $availability->delete();
        });

        if ($actorId !== null && $organizationId !== '') {
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'staff.availability_removed',
                auditableType: 'teacher_availability',
                auditableId: $auditableId,
                oldValues: $payload,
                newValues: null,
                reason: trim((string) $reason) === '' ? null : trim((string) $reason),
            );
        }
    }
}
