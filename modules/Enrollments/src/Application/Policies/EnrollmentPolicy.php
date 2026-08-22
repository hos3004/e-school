<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Policies;

use Modules\Enrollments\Domain\Models\Enrollment;

/**
 * سياسة القيود — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات enrollments.enrollment.<action>
 * مع مقارنة ملكية السجل على مستوى المنظمة.
 *
 * الحذف غير موجود هنا عمدًا: الحساب لا يُحذف أبدًا مهما كان السبب —
 * المسار الوحيد هو الانتقال إلى حالة نهائية (Withdrawn/Completed/Rejected).
 */
final class EnrollmentPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('enrollments.enrollment.view_any');
    }

    public function view($user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.enrollment.view')
            && $enrollment->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('enrollments.enrollment.create');
    }

    public function update($user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.enrollment.update')
            && $enrollment->organization_id === $user->organization_id;
    }

    public function delete($user, Enrollment $enrollment): bool
    {
        return false;
    }

    public function pause($user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.enrollment.pause')
            && $enrollment->organization_id === $user->organization_id;
    }

    public function freeze($user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.enrollment.freeze')
            && $enrollment->organization_id === $user->organization_id;
    }

    /** طلب فك التجميد: الطالب صاحب القيد أو من يملك الصلاحية. */
    public function requestReactivation($user, Enrollment $enrollment): bool
    {
        return ($enrollment->student_profile_id === $user->profile?->id)
            || $user->can('enrollments.enrollment.request_reactivation');
    }

    /** الاعتماد النهائي لفك التجميد — بصلاحية enrollment.reactivate حصريًا. */
    public function reactivate($user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.reactivate')
            && $enrollment->organization_id === $user->organization_id;
    }
}
