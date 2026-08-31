<?php

declare(strict_types=1);

namespace Modules\Students\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Students\Domain\Models\RegistrationApplication;

/** تفويض طلبات التسجيل مع عزل المؤسسة ومنع أي اعتماد مبني على اسم الدور. */
final class RegistrationApplicationPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function view(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->sameOrganization($user, $application)
            && ($user->can('student.create')
                || (string) $user->getAuthIdentifier() === (string) $application->user_id);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create')
            || (bool) config('admission.self_registration.enabled', true);
    }

    public function submit(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->sameOrganization($user, $application)
            && ($user->can('student.create')
                || (string) $user->getAuthIdentifier() === (string) $application->user_id);
    }

    public function review(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->canDecide($user, $application);
    }

    public function accept(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->canDecide($user, $application);
    }

    public function reject(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->canDecide($user, $application);
    }

    /**
     * تسكين الطالب في مجموعة.
     *
     * ثلاث صلاحيات مجتمعة لأن العملية تمس ثلاثة موارد: قراءة ملف الطالب،
     * إنشاء القيد الدراسي، وإدارة عضوية المجموعة. من يملك واحدة فقط لا يملك
     * الرحلة كاملة.
     */
    public function assign(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->sameOrganization($user, $application)
            && $application->status->isClearedForAssignment()
            && $user->can('student.view')
            && $user->can('enrollment.create')
            && $user->can('group.manage');
    }

    /**
     * فتح الإجراء الجماعي من شريط الأدوات — تفويض على مستوى الشاشة.
     *
     * التفويض على مستوى كل طالب على حدة يبقى إلزاميًا ويُنفَّذ عبر `assign`.
     */
    public function assignAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.view.any')
            && $user->can('enrollment.create')
            && $user->can('group.manage');
    }

    public function update(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return false;
    }

    private function canDecide(Authenticatable&Authorizable $user, RegistrationApplication $application): bool
    {
        return $this->sameOrganization($user, $application) && $user->can('student.create');
    }

    private function sameOrganization(Authenticatable $user, RegistrationApplication $application): bool
    {
        return (string) data_get($user, 'organization_id') === (string) $application->organization_id;
    }
}
