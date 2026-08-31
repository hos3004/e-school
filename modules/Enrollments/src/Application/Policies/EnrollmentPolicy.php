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
    public function viewAny(mixed $user): bool
    {
        return $user->can('enrollment.view');
    }

    public function view(mixed $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.view')
            && $enrollment->organization_id === $user->organization_id;
    }

    public function create(mixed $user): bool
    {
        return $user->can('enrollment.create');
    }

    public function update(mixed $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.create')
            && $enrollment->organization_id === $user->organization_id;
    }

    public function delete(mixed $user, Enrollment $enrollment): bool
    {
        return false;
    }

    public function pause(mixed $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.pause')
            && $enrollment->organization_id === $user->organization_id;
    }

    public function freeze(mixed $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.freeze')
            && $enrollment->organization_id === $user->organization_id;
    }

    /** طلب فك التجميد: الطالب صاحب القيد أو من يملك الصلاحية. */
    public function requestReactivation(mixed $user, Enrollment $enrollment): bool
    {
        return $enrollment->organization_id === $user->organization_id
            && (($enrollment->student_profile_id === data_get($user, 'profile.id'))
                || $user->can('enrollment.pause'));
    }

    /** الاعتماد النهائي لفك التجميد — بصلاحية enrollment.reactivate حصريًا. */
    public function reactivate(mixed $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollment.reactivate')
            && $enrollment->organization_id === $user->organization_id;
    }
}
