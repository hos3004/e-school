<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Policies;

use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;

/**
 * سياسة سجل تغيّر حالات القيد — قراءة فقط.
 *
 * السجل التاريخي وثيقة تدقيق: لا إنشاء ولا تعديل ولا حذف من الواجهة إطلاقًا.
 * الكتابة تحدث حصريًا داخل إجراءات الانتقال عبر TransitionsEnrollmentStatus.
 */
final class EnrollmentStatusHistoryPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('enrollments.status_history.view_any');
    }

    public function view($user, EnrollmentStatusHistory $history): bool
    {
        return $user->can('enrollments.status_history.view')
            && $history->enrollment()->first()?->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, EnrollmentStatusHistory $history): bool
    {
        return false;
    }

    public function delete($user, EnrollmentStatusHistory $history): bool
    {
        return false;
    }
}
