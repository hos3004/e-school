<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
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
    public function execute(TeacherAvailability $availability): void
    {
        if ($availability->approval_status === TeacherAvailabilityApprovalStatus::Approved) {
            throw BusinessRuleViolation::make(
                'staff.availability_approved_not_removable',
                'staff::errors.availability_approved_not_removable',
            );
        }

        $availability->delete();
    }
}
